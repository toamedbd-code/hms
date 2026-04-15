<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DashboardService;
use App\Models\PharmacyBill;
use App\Models\Billing;
use App\Models\Payment;
use App\Models\DueCollection;
use App\Models\BillItem;
use Illuminate\Support\Facades\Schema;

class CheckPharmacyIncome extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:pharmacy-income {--type=daily : Filter type (daily|monthly|yearly|custom)} {--from=} {--to=} {--details : Show detailed breakdown}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute pharmacy income using DashboardService for quick verification';

    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        parent::__construct();
        $this->dashboardService = $dashboardService;
    }

    public function handle()
    {
        $type = $this->option('type');
        $from = $this->option('from') ?: null;
        $to = $this->option('to') ?: null;

        $filter = $this->dashboardService->resolveDashboardFilter($type, $from, $to);
        $dbRange = $filter['dbRange'];
        $appRange = $filter['appRange'];

        $this->info('Filter type: ' . $filter['type']);
        $this->info('From: ' . $filter['from'] . ' To: ' . $filter['to']);


        $pharmacyIncome = $this->dashboardService->countPharmacyIncome($dbRange, $appRange);

        $this->line('Pharmacy Income: ' . number_format((float)$pharmacyIncome, 2));

        if ($this->option('details')) {
            $this->line('--- Detailed Breakdown ---');

            $pharmacyRows = PharmacyBill::query()
                ->where('status', 'Active')
                ->whereBetween('date', [
                    $dbRange[0]->toDateString(),
                    $dbRange[1]->toDateString(),
                ])
                // only select safe fields; some installations don't have amount columns on pharmacybills
                ->get(['id', 'bill_no', 'date']);

            if ($pharmacyRows->isEmpty()) {
                $this->line('No active PharmacyBill rows found for range.');
                return 0;
            }

            $billNos = $pharmacyRows->pluck('bill_no')->filter()->unique()->values()->toArray();
            $this->line('PharmacyBill count: ' . $pharmacyRows->count());
            $this->line('Bill numbers: ' . implode(', ', $billNos));
            foreach ($pharmacyRows as $r) {
                $this->line(sprintf('PharmacyBill id=%s bill_no=%s total=%s total_amount=%s net_amount=%s paid_amount=%s payable=%s',
                    $r->id,
                    $r->bill_no,
                    number_format($r->total ?? 0, 2),
                    number_format($r->total_amount ?? 0, 2),
                    number_format($r->net_amount ?? 0, 2),
                    number_format($r->paid_amount ?? 0, 2),
                    number_format($r->payable_amount ?? 0, 2)
                ));
            }

            $billings = Billing::whereIn('bill_number', $billNos)->get(['id', 'bill_number', 'total', 'discount', 'discount_type', 'extra_flat_discount']);
            $billingIds = $billings->pluck('id')->toArray();
            $this->line('Matched Billing count: ' . $billings->count());

            $payments = Payment::whereIn('billing_id', $billingIds)->get(['billing_id', 'amount']);
            $dueCollections = DueCollection::whereIn('billing_id', $billingIds)->get(['billing_id', 'collected_amount']);

            $paymentsByBilling = $payments->groupBy('billing_id')->map->sum('amount');
            $dueByBilling = $dueCollections->groupBy('billing_id')->map->sum('collected_amount');

            $this->line('Payments total: ' . number_format($payments->sum('amount'), 2));
            $this->line('Due collections total: ' . number_format($dueCollections->sum('collected_amount'), 2));

            $this->line('Per-billing breakdown:');
            foreach ($billings as $b) {
                $pay = (float) ($paymentsByBilling[$b->id] ?? 0);
                $duec = (float) ($dueByBilling[$b->id] ?? 0);

                $discountAmount = 0;
                if ($b->discount > 0) {
                    if (($b->discount_type ?? '') === 'percentage') {
                        $discountAmount = ($b->total * $b->discount) / 100;
                    } else {
                        $discountAmount = $b->discount;
                    }
                }
                $extraDiscount = max(0, (float) ($b->extra_flat_discount ?? 0));
                $net = max(0, (float) $b->total - $discountAmount - $extraDiscount);
                $pending = max(0, $net - ($pay + $duec));

                $this->line(sprintf('Billing %s: total=%s discount=%s extra=%s net=%s payments=%s due_collected=%s pending=%s',
                    $b->bill_number,
                    number_format($b->total, 2),
                    number_format($discountAmount, 2),
                    number_format($extraDiscount, 2),
                    number_format($net, 2),
                    number_format($pay, 2),
                    number_format($duec, 2),
                    number_format($pending, 2)
                ));
            }

            // Show BillItems for these billings (help identify pharmacy items)
            $this->line('--- BillItems (category breakdown) ---');
            // select only columns that exist in the current schema to avoid errors
            $cols = ['id', 'billing_id', 'category', 'item_id'];
            foreach (['total_amount', 'total', 'amount', 'net_amount', 'price', 'qty'] as $c) {
                if (Schema::hasColumn('bill_items', $c)) {
                    $cols[] = $c;
                }
            }

            $billItems = BillItem::whereIn('billing_id', $billingIds)
                ->get($cols);

            if ($billItems->isEmpty()) {
                $this->line('No BillItem rows found for matched billings.');
            } else {
                $itemsByBilling = $billItems->groupBy('billing_id');
                foreach ($itemsByBilling as $billingId => $items) {
                    $this->line('Billing ID: ' . $billingId . ' - Items: ' . $items->count());
                    foreach ($items as $it) {
                            $computed = 0;
                            if (!empty($it->total_amount)) {
                                $computed = (float)$it->total_amount;
                            } elseif (!empty($it->total)) {
                                $computed = (float)$it->total;
                            } elseif (!empty($it->amount)) {
                                $computed = (float)$it->amount;
                            } elseif (!empty($it->net_amount)) {
                                $computed = (float)$it->net_amount;
                            } elseif (isset($it->price) && isset($it->qty)) {
                                $computed = (float)$it->price * (float)$it->qty;
                            } elseif (!empty($it->price)) {
                                $computed = (float)$it->price;
                            }

                            $this->line(sprintf('  Item id=%s category=%s item_id=%s qty=%s price=%s total_amount=%s computed=%s',
                                $it->id,
                                $it->category,
                                $it->item_id,
                                $it->qty ?? 0,
                                number_format($it->price ?? 0, 2),
                                number_format($it->total_amount ?? 0, 2),
                                number_format($computed, 2)
                            ));
                        }
                }
                $pharmacySumItems = $billItems->filter(function ($it) {
                    $cat = strtolower((string)($it->category ?? ''));
                    return in_array($cat, ['pharmacy', 'medicine'], true);
                })->sum(function ($it) {
                    if (!empty($it->total_amount)) return (float)$it->total_amount;
                    if (!empty($it->total)) return (float)$it->total;
                    if (!empty($it->amount)) return (float)$it->amount;
                    if (!empty($it->net_amount)) return (float)$it->net_amount;
                    if (isset($it->price) && isset($it->qty)) return (float)$it->price * (float)$it->qty;
                    if (!empty($it->price)) return (float)$it->price;
                    return 0;
                });

                $this->line('Sum of computed totals for category=Pharmacy: ' . number_format($pharmacySumItems, 2));
            }
        }

        return 0;
    }
}
