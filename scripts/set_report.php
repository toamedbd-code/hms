<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BillItem;

$itemId = $argv[1] ?? null;
$filePath = $argv[2] ?? 'reports/sample_report.pdf';

if (!$itemId) {
    echo "Usage: php scripts/set_report.php <bill_item_id> [file_path]\n";
    exit(1);
}

$item = BillItem::find($itemId);
if (!$item) {
    echo "BillItem {$itemId} not found\n";
    exit(1);
}

$item->report_file = $filePath;
$item->reported_at = now();
$item->save();

echo json_encode(['id'=>$item->id,'billing_id'=>$item->billing_id,'item_name'=>$item->item_name,'category'=>$item->category,'report_file'=>$item->report_file,'reported_at'=>$item->reported_at], JSON_PRETTY_PRINT);
