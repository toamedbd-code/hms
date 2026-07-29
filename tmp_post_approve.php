<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Payment\BkashController;
use App\Models\Payment;

$p = Payment::orderBy('id','desc')->first();
if (! $p) { echo "no payment\n"; exit(1); }
$token = $p->metadata['approval_token'] ?? null;
if (! $token) { echo "no token\n"; exit(1); }

$req = Request::create('/payment/bkash/simulate-public/'.$p->id.'/approve', 'POST', ['approval_token' => $token]);
$controller = new BkashController();
$response = $controller->publicSimulateApprove($req, $p);

if (method_exists($response, 'getStatusCode')) {
    echo 'status=' . $response->getStatusCode() . PHP_EOL;
}
if (method_exists($response, 'headers')) {
    echo 'location=' . $response->headers->get('Location') . PHP_EOL;
}

$p = Payment::find($p->id);
echo 'payment_status=' . $p->status . PHP_EOL;
$sub = App\Models\Subscription::getCurrent();
echo 'subscription_active=' . ($sub ? ($sub->is_active ? 'yes' : 'no') : 'missing') . PHP_EOL;
if ($sub) echo 'expires_at=' . $sub->expires_at . PHP_EOL;
