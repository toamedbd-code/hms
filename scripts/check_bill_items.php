<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BillItem;

$billingId = $argv[1] ?? 87;
// `module` column may not exist in this schema; select known columns only
$items = BillItem::where('billing_id', $billingId)->get(['id','billing_id','item_name','category','report_file','reported_at','created_at']);

echo json_encode(['billing_id'=>$billingId, 'count'=>$items->count(), 'items'=>$items->toArray()], JSON_PRETTY_PRINT);

