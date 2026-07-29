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
if (!$admin) {
    echo "No admin found\n"; exit(1);
}
Auth::guard('admin')->loginUsingId($admin->id);

$controller = new ReportingController();

$tests = [
    ['item' => 198, 'dept' => 'ultrasound', 'note' => 'Direct simulated note for item 198 (should fail)'],
    ['item' => 199, 'dept' => 'ultrasound', 'note' => 'Direct simulated note for item 199 (should succeed)'],
];

foreach ($tests as $t) {
    $billItem = BillItem::find($t['item']);
    if (!$billItem) { echo "BillItem {$t['item']} not found\n"; continue; }

    $req = Request::create('/dummy', 'POST', [
        'department' => $t['dept'],
        'report_note' => $t['note'],
    ]);

    echo "Calling updateItem for item {$t['item']} department={$t['dept']}\n";

    try {
        $resp = $controller->updateItem($req, $billItem);
        echo "Controller executed. (response class: " . get_class($resp) . ")\n";
    } catch (\Throwable $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }

    $item = BillItem::find($t['item']);
    echo json_encode(['id'=>$item->id,'report_note'=>$item->report_note,'reported_at'=>$item->reported_at?->toDateTimeString()], JSON_PRETTY_PRINT) . "\n\n";
}

