<?php

namespace App\Console\Commands;

use App\Models\Billing;
use App\Models\OpdPatient;
use App\Services\DashboardService;
use Illuminate\Console\Command;

class CheckPendingIncome extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:pending-income
        {--type=daily : Filter type (daily|monthly|yearly|custom)}
        {--from= : From date for custom range (Y-m-d)}
        {--to= : To date for custom range (Y-m-d)}
        {--details : Show per-row breakdown}
        {--limit=200 : Max rows for detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute pending income with bill-wise and OPD-wise breakdown';

    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        parent::__construct();
        $this->dashboardService = $dashboardService;
    }

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $from = $this->option('from') ?: null;
        $to = $this->option('to') ?: null;
        $showDetails = (bool) $this->option('details');
        $limit = max(1, (int) $this->option('limit'));

        $filter = $this->dashboardService->resolveDashboardFilter($type, $from, $to);
        $dbRange = $filter['dbRange'];
        $appRange = $filter['appRange'];

        $this->info('Filter type: ' . $filter['type']);
        $this->info('From: ' . $filter['from'] . ' To: ' . $filter['to']);

        $pendingIncome = (float) $this->dashboardService->countPendingIncome($dbRange, $appRange);
        $this->line('Pending Income: ' . number_format($pendingIncome, 2));

        if (!$showDetails) {
            return self::SUCCESS;
        }

        $this->line('--- Detailed Breakdown (source: due_amount + opd balance) ---');

        $billingQuery = Billing::query()
            ->where('status', 'Active')
            ->where('due_amount', '>', 0)
            ->whereBetween('created_at', $dbRange)
            ->orderByDesc('created_at');

        $billingRows = $billingQuery
            ->limit($limit)
            ->get(['id', 'bill_number', 'due_amount', 'created_at']);

        $billingTotal = (float) $billingQuery->sum('due_amount');

        $opdQuery = OpdPatient::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('balance_amount', '>', 0)
            ->whereBetween('appointment_date', [
                $appRange[0]->toDateString(),
                $appRange[1]->toDateString(),
            ])
            ->orderByDesc('appointment_date');

        $opdRows = $opdQuery
            ->limit($limit)
            ->get(['id', 'opd_no', 'balance_amount', 'appointment_date']);

        $opdTotal = (float) $opdQuery->sum('balance_amount');

        $this->line('Billing pending total: ' . number_format($billingTotal, 2));
        $this->line('OPD pending total: ' . number_format($opdTotal, 2));
        $this->line('Grand total: ' . number_format(max(0, $billingTotal) + max(0, $opdTotal), 2));

        if ($billingRows->isNotEmpty()) {
            $this->line('--- Billing Due Rows ---');
            foreach ($billingRows as $row) {
                $this->line(sprintf(
                    'Billing id=%s bill_no=%s due=%s created_at=%s',
                    $row->id,
                    (string) ($row->bill_number ?? 'N/A'),
                    number_format((float) ($row->due_amount ?? 0), 2),
                    optional($row->created_at)->format('Y-m-d H:i:s') ?? 'N/A'
                ));
            }
        } else {
            $this->line('No Billing due rows found in selected range.');
        }

        if ($opdRows->isNotEmpty()) {
            $this->line('--- OPD Balance Rows ---');
            foreach ($opdRows as $row) {
                $this->line(sprintf(
                    'OPD id=%s opd_no=%s balance=%s appointment_date=%s',
                    $row->id,
                    (string) ($row->opd_no ?? 'N/A'),
                    number_format((float) ($row->balance_amount ?? 0), 2),
                    (string) ($row->appointment_date ?? 'N/A')
                ));
            }
        } else {
            $this->line('No OPD due rows found in selected range.');
        }

        return self::SUCCESS;
    }
}
