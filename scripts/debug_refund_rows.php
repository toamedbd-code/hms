<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Billing;

$rows = Billing::with('patient')
    ->whereNull('deleted_at')
    ->where('status', 'Active')
    ->where('return_amt', '>', 0)
    ->get();

echo "total_rows:" . count($rows) . "\n";

foreach ($rows as $r) {
    $bn = trim((string) $r->bill_number);
    $pn = trim((string) ($r->patient_name ?? ($r->patient?->name ?? '')));
    $ca = $r->created_at ? $r->created_at->toDateTimeString() : '';
    echo sprintf(
        "id=%s bill_number=\"%s\" patient_name=\"%s\" patient_name_db=\"%s\" patient.name=\"%s\" created_at=\"%s\" total=%s paid_amt=%s return_amt=%s payable_amount=%s payment_status=\"%s\"\n",
        $r->id,
        addslashes($bn),
        addslashes($pn),
        addslashes((string) ($r->patient_name ?? '')),
        addslashes((string) ($r->patient?->name ?? '')),
        $ca,
        $r->total,
        $r->paid_amt,
        $r->return_amt,
        $r->payable_amount,
        $r->payment_status
    );
}
