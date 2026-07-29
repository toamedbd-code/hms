<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Backend\BkashSettingController;
use App\Services\BkashService;

$req = Request::create('/settings/payment/bkash/test', 'POST', []);
$controller = new BkashSettingController();
$service = new BkashService();
$res = $controller->test($req, $service);
if (method_exists($res, 'getStatusCode')) echo 'status=' . $res->getStatusCode() . "\n";
echo (string) $res->getContent() . PHP_EOL;
