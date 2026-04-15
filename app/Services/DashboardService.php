<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Billing;
use App\Models\BillItem;
use Illuminate\Support\Facades\Schema;
use App\Models\DueCollection;
use App\Models\Expense;
use App\Models\ProductReturn;
use App\Models\OpdPatient;
use App\Models\IpdPatient;
use App\Models\PharmacyBill;
use Carbon\Carbon;
use App\Services\ReportAccountingService;

class DashboardService
{
    protected ReportAccountingService $accountingService;

    public function __construct(ReportAccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function todayDate()
    {
        return Carbon::today();
    }

    public function getDate($day = 'today')
    {
        if ($day === 'yesterday') {
            return Carbon::yesterday();
        }
        return Carbon::today();
    }

    public function getDateRange($day = 'today')
    {
        $appTimezone = config('app.timezone');
        $dbTimezone = config('database.connections.mysql.timezone_offset') ?: '+00:00';

        $date = $day === 'yesterday'
            ? Carbon::now($appTimezone)->subDay()
            : Carbon::now($appTimezone);

        $start = $date->copy()->startOfDay()->setTimezone($dbTimezone);
        $end = $date->copy()->endOfDay()->setTimezone($dbTimezone);

        return [$start, $end];
    }

    public function resolveDashboardFilter(?string $filterType, ?string $fromDate, ?string $toDate)
    {
        $allowedTypes = ['daily', 'monthly', 'yearly', 'custom'];
        $type = in_array($filterType, $allowedTypes, true) ? $filterType : 'daily';

        $appTimezone = config('app.timezone');
        $dbTimezone = config('database.connections.mysql.timezone_offset') ?: '+00:00';
        $now = Carbon::now($appTimezone);

        switch ($type) {
            case 'monthly':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
            case 'yearly':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                break;
            case 'custom':
                if ($fromDate && $toDate) {
                    $start = Carbon::parse($fromDate, $appTimezone)->startOfDay();
                    $end = Carbon::parse($toDate, $appTimezone)->endOfDay();
                    break;
                }
                $type = 'daily';
            default:
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
        }

        $appRange = [$start->copy(), $end->copy()];
        $dbRange = [$start->copy()->setTimezone($dbTimezone), $end->copy()->setTimezone($dbTimezone)];

        return [
            'type' => $type,
            'appRange' => $appRange,
            'dbRange' => $dbRange,
            'from' => $appRange[0]->toDateString(),
            'to' => $appRange[1]->toDateString(),
        ];
    }

    // ✔ Active Users
    public function countActiveUser()
    {
        return Admin::whereNull('deleted_at')
            ->where('status', 'Active')
            ->count();
    }

    // ✔ Inactive Users
    public function countInActiveUser()
    {
        return Admin::whereNull('deleted_at')
            ->where('status', 'Inactive')
            ->count();
    }

    // ✔ Pharmacy Income (Pharmacy module only) - collected amounts
    public function countPharmacyIncome(array $dbRange, array $dateRange)
    {
        // Identify pharmacy bills in the selected date range, then locate the
        // corresponding Billing records (by bill number) and sum payments + due-collections
        // recorded against those billings. This matches the report rows the user sees.
        $pharmacyRows = PharmacyBill::query()
            ->where('status', 'Active')
            // Use DB-range (timezone-aware) when querying pharmacy bill dates
            ->whereBetween('date', [
                $dbRange[0]->toDateString(),
                $dbRange[1]->toDateString(),
            ])
            // fetch safe fields only; amount fields may not exist on all schemas
            ->get(['id', 'bill_no', 'date']);

        if ($pharmacyRows->isEmpty()) {
            return 0;
        }

        $returnDeduction = (float) ProductReturn::query()
            ->where('return_type', 'customer')
            ->whereIn('status', ['approved', 'processed'])
            ->whereBetween('return_date', [
                $dateRange[0]->toDateString(),
                $dateRange[1]->toDateString(),
            ])
            ->sum('total_amount');

        $billNos = $pharmacyRows->pluck('bill_no')->filter()->unique()->values()->toArray();
        if (empty($billNos)) {
            return 0;
        }

        // Try to compute pharmacy income directly from PharmacyBill totals (preferred).
        // Some installations record pharmacy totals on `pharmacybills` table, which
        // represents the actual pharmacy amount (e.g., small retail sums). If those
        // values exist, return their sum. Otherwise fall back to the previous
        // approach (summing payments recorded against linked Billing records).
        $pharmacySum = $pharmacyRows->sum(function ($row) {
            return (float) ($row->net_amount ?? $row->total ?? $row->net ?? $row->paid_amount ?? 0);
        });

        if ($pharmacySum > 0) {
            return max(0, $pharmacySum - $returnDeduction);
        }

        // Next preference: compute pharmacy amount from BillItem rows with category 'Pharmacy'
        $billingIds = Billing::whereIn('bill_number', $billNos)->pluck('id')->toArray();
        if (!empty($billingIds)) {
            // select only columns that exist in the current schema to avoid errors
            $cols = ['billing_id', 'id', 'category', 'item_id'];
            foreach (['total_amount', 'total', 'amount', 'net_amount', 'price', 'qty'] as $c) {
                if (Schema::hasColumn('bill_items', $c)) {
                    $cols[] = $c;
                }
            }

            // Treat both 'pharmacy' and 'medicine' categories as pharmacy items
            $pharmacyItems = BillItem::whereRaw('LOWER(category) IN (?,?)', ['pharmacy', 'medicine'])
                ->where('status', 'Active')
                ->whereIn('billing_id', $billingIds)
                ->get($cols);

            if ($pharmacyItems->isNotEmpty()) {
                // Sum per-billing pharmacy totals
                // Compute each item's effective total using any available field
                $pharmacyTotalsByBilling = $pharmacyItems->groupBy('billing_id')->map(function ($items) {
                    return $items->sum(function ($it) {
                        $val = 0;
                        if (!empty($it->total_amount)) {
                            $val = (float) $it->total_amount;
                        } elseif (!empty($it->total)) {
                            $val = (float) $it->total;
                        } elseif (!empty($it->amount)) {
                            $val = (float) $it->amount;
                        } elseif (!empty($it->net_amount)) {
                            $val = (float) $it->net_amount;
                        } elseif (isset($it->price) && isset($it->qty)) {
                            $val = (float) $it->price * (float) $it->qty;
                        } elseif (!empty($it->price)) {
                            $val = (float) $it->price;
                        }
                        return $val;
                    });
                });

                $billings = Billing::whereIn('id', $pharmacyTotalsByBilling->keys()->toArray())
                    ->get(['id', 'total', 'discount', 'discount_type', 'extra_flat_discount']);

                $netPharmacy = 0.0;
                foreach ($billings as $billing) {
                    $billingId = $billing->id;
                    $pharmacyTotal = (float) ($pharmacyTotalsByBilling[$billingId] ?? 0);
                    if ($pharmacyTotal <= 0) {
                        continue;
                    }

                    // Calculate billing-level discount amount (includes extra flat)
                    $billingDiscount = 0;
                    if ($billing->discount > 0) {
                        if (($billing->discount_type ?? '') === 'percentage') {
                            $billingDiscount = ($billing->total * $billing->discount) / 100;
                        } else {
                            $billingDiscount = $billing->discount;
                        }
                    }
                    $billingDiscount += max(0, (float) ($billing->extra_flat_discount ?? 0));

                    // Allocate discount proportionally to pharmacy items
                    $allocatedDiscount = 0;
                    if ((float)$billing->total > 0) {
                        $allocatedDiscount = ($billingDiscount * $pharmacyTotal) / (float)$billing->total;
                    }

                    $net = max(0, $pharmacyTotal - $allocatedDiscount);
                    $netPharmacy += round($net, 2);
                }

                if ($netPharmacy > 0) {
                    return max(0, $netPharmacy - $returnDeduction);
                }
            }
        }

        // Final fallback: sum payments + due collections against linked billings
        if (empty($billingIds)) {
            return 0;
        }

        $paymentsSum = (float) \App\Models\Payment::whereIn('billing_id', $billingIds)->sum('amount');
        $dueCollectionSum = (float) \App\Models\DueCollection::whereIn('billing_id', $billingIds)->sum('collected_amount');

        return max(0, ($paymentsSum + $dueCollectionSum) - $returnDeduction);
    }

    // ✔ Blood Bank Income - Net Income After Discount (from Billing)
    public function countBloodBankIncome(array $dbRange, array $dateRange)
    {
        $billItems = BillItem::where('category', 'Blood Bank')
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                    ->where('payment_status', '!=', 'Pending');
            })->get();

        if ($billItems->isEmpty()) {
            return 0;
        }

        $billingIds = $billItems->pluck('billing_id')->unique();
        $billings = Billing::whereIn('id', $billingIds)->get();

        $totalDiscount = $billings->sum(function ($billing) {
            $discount = ($billing->discount_type == 'percentage'
                ? ($billing->total * $billing->discount) / 100
                : $billing->discount) + $billing->extra_flat_discount;
            return max(0, $discount);
        });

        $totalAmount = $billItems->sum('total_amount');

        return max(0, $totalAmount - $totalDiscount);
    }

    // ✔ Pathology Income - Net Income After Discount (from Billing)
    public function countPathologyIncome(array $dbRange, array $dateRange)
    {
        $billItems = BillItem::where('category', 'Pathology')
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                  ->where('status', 'Active')
                  ->where(function ($paymentQuery) {
                      $paymentQuery->where('payment_status', '!=', 'Pending')
                          ->orWhere('due_amount', '<=', 0)
                          ->orWhere('paid_amt', '>', 0);
                  });
            })->get();

        if ($billItems->isEmpty()) {
            return 0;
        }

        $pathologyTotalsByBilling = $billItems->groupBy('billing_id')->map(function ($items) {
            return (float) $items->sum('total_amount');
        });

        $billingIds = $pathologyTotalsByBilling->keys()->toArray();
        $billings = Billing::whereIn('id', $billingIds)
            ->get(['id', 'total', 'discount', 'discount_type', 'extra_flat_discount']);

        $netPathology = 0.0;
        foreach ($billings as $billing) {
            $pathologyTotal = (float) ($pathologyTotalsByBilling[$billing->id] ?? 0);
            if ($pathologyTotal <= 0) {
                continue;
            }

            $billingDiscount = 0;
            if ((float) $billing->discount > 0) {
                if (($billing->discount_type ?? '') === 'percentage') {
                    $billingDiscount = ((float) $billing->total * (float) $billing->discount) / 100;
                } else {
                    $billingDiscount = (float) $billing->discount;
                }
            }
            $billingDiscount += max(0, (float) ($billing->extra_flat_discount ?? 0));

            $allocatedDiscount = 0;
            if ((float) $billing->total > 0) {
                $allocatedDiscount = ($billingDiscount * $pathologyTotal) / (float) $billing->total;
            }

            $netPathology += max(0, $pathologyTotal - $allocatedDiscount);
        }

        return round($netPathology, 2);
    }

    // ✔ Radiology Income - Net Income After Discount (from Billing)
    public function countRadiologyIncome(array $dbRange, array $dateRange)
    {
        $billItems = BillItem::where('category', 'Radiology')
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                  ->where('payment_status', '!=', 'Pending');
            })->get();

        if ($billItems->isEmpty()) {
            return 0;
        }

        // Get unique billing IDs
        $billingIds = $billItems->pluck('billing_id')->unique();
        $billings = Billing::whereIn('id', $billingIds)->get();

        // Calculate discount from Billing records (includes extra_flat_discount)
        $totalDiscount = $billings->sum(function ($billing) {
            $discount = ($billing->discount_type == 'percentage'
                ? ($billing->total * $billing->discount) / 100
                : $billing->discount) + $billing->extra_flat_discount;
            return max(0, $discount);
        });

        // Total amount from radiology items
        $totalAmount = $billItems->sum('total_amount');

        // Net amount = Total - Discount
        return max(0, $totalAmount - $totalDiscount);
    }

    // ✔ OPD Income (Today paid) - Actual paid amount
    public function countOpdIncome(array $dateRange)
    {
        return OpdPatient::where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('payment_status', '!=', 'Pending')
            ->whereBetween('appointment_date', [
                $dateRange[0]->toDateString(),
                $dateRange[1]->toDateString(),
            ])
            ->sum('paid_amount');
    }

    // ✔ IPD Income (from IPD billing) - Collected amount
    public function countIpdIncome(array $dbRange)
    {
        return (float) IpdPatient::query()
            ->join('billings', 'ipdpatients.billing_id', '=', 'billings.id')
            ->whereIn('ipdpatients.status', ['Active', 'Inactive'])
            ->whereBetween('billings.created_at', $dbRange)
            ->where('billings.status', 'Active')
            ->where('billings.payment_status', '!=', 'Pending')
            ->sum('billings.paid_amt');
    }

    // ✔ Pending Income (all outstanding dues)
    // Source of truth is current due fields on each module row.
    // Billing due_amount is already adjusted during due-collection, so avoid
    // subtracting DueCollection again or adding pharmacy pending again.
    public function countPendingIncome(array $dbRange = null, array $dateRange = null)
    {
        $totalDue = 0;

        // Billing dues (optionally limited to the provided DB range)
        $billingQuery = Billing::where('status', 'Active')
            ->where('due_amount', '>', 0);

        if (is_array($dbRange) && count($dbRange) === 2) {
            $billingQuery->whereBetween('created_at', $dbRange);
        }

        $billings = $billingQuery->get(['due_amount']);
        if ($billings->isNotEmpty()) {
            $billingTotalDue = $billings->sum(function ($b) {
                return max(0, (float) ($b->due_amount ?? 0));
            });

            $totalDue += max(0, $billingTotalDue);
        }

        // OPD patient balances (optionally limited to the provided date range)
        $opdQuery = OpdPatient::where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('balance_amount', '>', 0);

        if (is_array($dateRange) && count($dateRange) === 2) {
            $opdQuery->whereBetween('appointment_date', [
                $dateRange[0]->toDateString(),
                $dateRange[1]->toDateString(),
            ]);
        }

        $opdPatients = $opdQuery->get(['balance_amount']);
        foreach ($opdPatients as $opdPatient) {
            $totalDue += max(0, (float) $opdPatient->balance_amount);
        }

        return $totalDue;
    }

    // ✔ Total Income (module incomes + pending dues)
    public function countTotalIncome(array $dbRange, array $dateRange)
    {
        $pharmacyIncome = (float) $this->countPharmacyIncome($dbRange, $dateRange);
        $pathologyIncome = (float) $this->countPathologyIncome($dbRange, $dateRange);
        $radiologyIncome = (float) $this->countRadiologyIncome($dbRange, $dateRange);
        $bloodBankIncome = (float) $this->countBloodBankIncome($dbRange, $dateRange);
        $opdIncome = (float) $this->countOpdIncome($dateRange);
        $ipdIncome = (float) $this->countIpdIncome($dbRange);
        $pendingIncome = (float) $this->countPendingIncome($dbRange, $dateRange);

        return $pharmacyIncome
            + $pathologyIncome
            + $radiologyIncome
            + $bloodBankIncome
            + $opdIncome
            + $ipdIncome
            + $pendingIncome;
    }

    // ✔ Total Discount (Today)
    public function countTotalDiscount(array $dbRange)
    {
        $billings = Billing::where('status', 'Active')
            ->where('payment_status', '!=', 'Pending')
            ->whereBetween('created_at', $dbRange)
            ->get();

        $totalDiscount = 0;
        foreach ($billings as $billing) {
            $discount = ($billing->discount_type == 'percentage'
                ? ($billing->total * $billing->discount) / 100
                : $billing->discount) + $billing->extra_flat_discount;
            $totalDiscount += $discount;
        }

        return $totalDiscount;
    }

    // ✔ Expense (Daily)
    public function countExpense(array $dateRange)
    {
        return Expense::where('status', 'Active')
            ->whereBetween('date', [
                $dateRange[0]->toDateString(),
                $dateRange[1]->toDateString(),
            ])
            ->sum('amount');
    }

    // ✔ Net Income (Daily) = (Today Paid Billing + Due Collection) - Expense
    public function countNetIncome(array $dbRange, array $dateRange)
    {
        $dateConditions = [
            'single_date_range' => $dbRange
        ];

        $billRows = $this->accountingService->getBillRowsByDate($dateConditions);
        $incomeTotals = $this->accountingService->calculateFinalIncomeTotals($billRows, $dateConditions);

        $finalIncomeFromBills = (float) ($incomeTotals['final_income'] ?? 0);
        $dueCollectionFromBills = (float) ($incomeTotals['total_due_collected'] ?? 0);

        $dueCollectionRows = DueCollection::query()
            ->whereBetween('collected_at', $dbRange)
            ->get(['billing_id', 'collected_amount', 'payment_method', 'note']);

        $opdIdsFromDueRows = $dueCollectionRows
            ->filter(function ($row) {
                return strtolower((string)($row->payment_method ?? '')) === 'opd' && empty($row->billing_id);
            })
            ->map(function ($row) {
                $matches = [];
                preg_match('/opd_patient_id:\s*(\d+)/i', (string)($row->note ?? ''), $matches);
                return isset($matches[1]) ? (int)$matches[1] : null;
            })
            ->filter()
            ->unique()
            ->values();

        $activeOpdIdMap = OpdPatient::query()
            ->whereIn('id', $opdIdsFromDueRows)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->flip();

        $dueCollection = (float) $dueCollectionRows->sum(function ($row) use ($activeOpdIdMap) {
            $paymentMethod = strtolower((string)($row->payment_method ?? ''));

            if ($paymentMethod === 'opd' && empty($row->billing_id)) {
                $matches = [];
                preg_match('/opd_patient_id:\s*(\d+)/i', (string)($row->note ?? ''), $matches);
                $opdPatientId = isset($matches[1]) ? (int)$matches[1] : null;

                if ($opdPatientId && !$activeOpdIdMap->has($opdPatientId)) {
                    return 0;
                }
            }

            return (float)($row->collected_amount ?? 0);
        });

        $missingDueCollection = max(0, $dueCollection - $dueCollectionFromBills);

        return $finalIncomeFromBills + $missingDueCollection;
    }
}
