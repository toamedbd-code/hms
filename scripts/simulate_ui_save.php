<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
// Bootstrap app
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin;
use App\Models\BillItem;

$admin = Admin::first();
if (!$admin) {
    echo "No admin user found to authenticate. Create an admin first.\n";
    exit(1);
}

// Login as admin guard
Auth::guard('admin')->loginUsingId($admin->id);

$tests = [
    ['item' => 198, 'dept' => 'ultrasound', 'note' => 'Simulated note for item 198 (should fail)'],
    ['item' => 199, 'dept' => 'ultrasound', 'note' => 'Simulated note for item 199 (should succeed)'],
];

foreach ($tests as $t) {
    $uri = '/reporting/item/' . $t['item'];
    $request = Request::create($uri, 'POST', [
        'department' => $t['dept'],
        'report_note' => $t['note'],
    ]);

    // Use session & cookies from current app for auth
    $request->setLaravelSession(app('session')->driver());

    echo "POST {$uri} department={$t['dept']}\n";
    $response = $kernel->handle($request);

    $status = $response->getStatusCode();
    echo "Response status: {$status}\n";

    // check DB state
    $item = BillItem::find($t['item']);
    echo json_encode(['id'=>$item->id,'report_note'=>$item->report_note,'reported_at'=>$item->reported_at?->toDateTimeString()], JSON_PRETTY_PRINT) . "\n\n";

    // terminate middleware/request lifecycle
    $kernel->terminate($request, $response);
}

