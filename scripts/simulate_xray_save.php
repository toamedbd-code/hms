<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin;
use App\Models\BillItem;
use App\Http\Controllers\Backend\ReportingController;

$admin = Admin::first();
if (!$admin) { echo "No admin found\n"; exit(1); }
Auth::guard('admin')->loginUsingId($admin->id);

$itemId = 198;
$billItem = BillItem::find($itemId);
if (!$billItem) { echo "BillItem {$itemId} not found\n"; exit(1); }

$controller = new ReportingController();
$req = Request::create('/dummy', 'POST', [
    'department' => 'xray',
    'report_note' => 'Simulated X-ray save note for item 198',
]);

try {
    $resp = $controller->updateItem($req, $billItem);
    echo "Controller executed (response class: " . get_class($resp) . ")\n";
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

$item = BillItem::find($itemId);
echo json_encode(['id'=>$item->id,'report_note'=>$item->report_note,'reported_at'=>$item->reported_at?->toDateTimeString()], JSON_PRETTY_PRINT) . "\n";

