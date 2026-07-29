<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Backend\BillingController;
use Illuminate\Support\Facades\Request;

// emulate request query parameters if needed
$_GET['name'] = '';
$_GET['numOfData'] = 10;

$controller = new BillingController();

$ref = new ReflectionClass($controller);
$method = $ref->getMethod('getRefundListDatas');
$method->setAccessible(true);

$result = $method->invoke($controller);

if (is_object($result)) {
    echo 'class: ' . get_class($result) . "\n";
    echo 'total: ' . $result->total() . "\n";
    echo 'current_page: ' . $result->currentPage() . "\n";
    echo 'per_page: ' . $result->perPage() . "\n";
    echo 'items count: ' . count($result->items()) . "\n";
    foreach ($result->items() as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
} else {
    var_dump($result);
}
