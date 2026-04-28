<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
// Boot the kernel so app() and facades work
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
// Ensure facades have application instance
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
// Bootstrap the kernel (register config, providers, etc.)
$kernel->bootstrap();

$q = $argv[1] ?? 'rbs';
$from = $argv[2] ?? date('Y-m-d');
$to = $argv[3] ?? $from;

$sql = "select bill_items.*, billings.case_number, billings.patient_id, billings.patient_mobile from bill_items inner join billings on bill_items.billing_id = billings.id where bill_items.category = 'Pathology' and bill_items.item_name like ? and DATE(billings.created_at) between ? and ? limit 100";
try {
    $rows = Illuminate\Support\Facades\DB::select($sql, ["%{$q}%", $from, $to]);
    echo "Found " . count($rows) . " rows\n";
    foreach ($rows as $r) {
        echo ($r->id ?? $r['id']) . " - " . ($r->item_name ?? $r['item_name']) . " - case: " . ($r->case_number ?? $r['case_number']) . " - patient_mobile: " . ($r->patient_mobile ?? $r['patient_mobile']) . "\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
