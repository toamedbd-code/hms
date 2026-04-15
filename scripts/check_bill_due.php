<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;

$billNumber = $argv[1] ?? 'BILL2026030013';
$billing = App\Models\Billing::where('bill_number', $billNumber)->first();
if (! $billing) {
    echo "Billing not found for {$billNumber}\n";
    exit(0);
}

$svc = $app->make(App\Services\ReportAccountingService::class);
$dateFrom = Carbon::parse($billing->created_at)->startOfDay();
$dateTo = Carbon::parse($billing->created_at)->endOfDay();
$rows = $svc->getBillRowsByDate(['date_from' => $dateFrom, 'date_to' => $dateTo]);

if ($rows->isEmpty()) {
    echo "No bill rows returned\n";
    exit(0);
}

$matched = $rows->first(function ($r) use ($billNumber) {
    return isset($r['bill_no']) && $r['bill_no'] === $billNumber;
});

if ($matched) {
    echo json_encode($matched, JSON_PRETTY_PRINT) . "\n";
} else {
    // If exact bill not found in the date-filtered rows, print all rows for inspection
    echo json_encode($rows->toArray(), JSON_PRETTY_PRINT) . "\n";
}
