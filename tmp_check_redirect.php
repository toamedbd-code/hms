<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BkashService;
use App\Models\Payment;

$p = Payment::orderBy('id', 'desc')->first();
if (! $p) { echo "no payment\n"; exit(1); }
$svc = new BkashService();
$result = $svc->createPayment($p);
print_r($result);

echo "payment id: {$p->id}\n";
echo "provider_payment_id: {$p->provider_payment_id}\n";
