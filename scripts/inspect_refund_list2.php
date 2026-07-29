<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;
use App\Http\Controllers\Backend\BillingController;

$request = Request::create('/refunds/list', 'GET', ['name' => '', 'numOfData' => 10]);
RequestFacade::swap($request);
$app->instance('request', $request);

$controller = $app->make(BillingController::class);
$ref = new ReflectionMethod($controller, 'getRefundListDatas');
$ref->setAccessible(true);
$result = $ref->invoke($controller);

if (is_object($result)) {
    echo 'class:' . get_class($result) . "\n";
    echo 'total:' . $result->total() . "\n";
    echo 'current_page:' . $result->currentPage() . "\n";
    echo 'per_page:' . $result->perPage() . "\n";
    echo 'items_count:' . count($result->items()) . "\n";
    foreach ($result->items() as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
} else {
    var_dump($result);
}
