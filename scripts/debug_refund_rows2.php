<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Billing;

$rows = Billing::with(['patient', 'payments'])
    ->whereNull('deleted_at')
    ->where('status', 'Active')
    ->where('return_amt', '>', 0.0001)
    ->where('total', '>', 0)
    ->orderBy('created_at', 'desc')
    ->get();

echo "found=" . count($rows) . "\n";
foreach ($rows as $r) {
    echo "-- id=" . $r->id . "\n";
    echo "bill_number=" . ($r->bill_number ?? '') . "\n";
    echo "patient_id=" . ($r->patient_id ?? '') . "\n";
    echo "patient_name=" . ($r->patient_name ?? '') . "\n";
    echo "patient->name=" . ($r->patient?->name ?? '') . "\n";
    echo "created_at=" . ($r->created_at ? $r->created_at->toDateTimeString() : '') . "\n";
    echo "total=" . $r->total . " paid_amt=" . $r->paid_amt . " return_amt=" . $r->return_amt . " payable_amount=" . $r->payable_amount . "\n";
    echo "attributes=" . json_encode($r->getAttributes()) . "\n";
    echo "payment count=" . ($r->payments?->count() ?? 0) . "\n";
}
