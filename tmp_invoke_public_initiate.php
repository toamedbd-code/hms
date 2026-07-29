<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Payment\BkashController;
use App\Services\BkashService;
use App\Models\Payment;

$req = Request::create('/payment/bkash/renew', 'GET', ['amount' => 175]);
$controller = new BkashController();
$service = new BkashService();
$response = $controller->publicInitiate($req, $service);

if (method_exists($response, 'getStatusCode')) {
    echo 'status=' . $response->getStatusCode() . PHP_EOL;
}
if (method_exists($response, 'headers')) {
    try { echo 'location=' . $response->headers->get('Location') . PHP_EOL; } catch(Throwable $e) {}
}

$p = Payment::orderBy('id','desc')->first();
if ($p) {
    echo 'id=' . $p->id . PHP_EOL;
    echo 'status=' . $p->status . PHP_EOL;
    echo 'metadata=' . json_encode($p->metadata) . PHP_EOL;
}
