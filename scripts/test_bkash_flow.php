<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BkashService;
use App\Models\Payment;

echo "Starting bKash test flow...\n";

$svc = new BkashService();

// 1) Probe token endpoints (full results saved)
echo "Probing token endpoints...\n";
$probe = $svc->probeTokenEndpoints();

$out = [
    'timestamp' => date('c'),
    'probe_summary' => [],
    'probe_full' => $probe,
];

$non403 = 0;
foreach ($probe as $p) {
    $status = $p['status'] ?? null;
    if ($status !== 403 && $status !== null) $non403++;
}

$out['probe_summary'] = ['checked' => count($probe), 'non403' => $non403];

$path = __DIR__ . '/../storage/logs/bkash-test-dump-'.date('Ymd_His').'.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT));
echo "Probe saved to: $path\n";

// 2) Try to obtain a token via grantToken()
echo "Attempting grantToken()...\n";
$token = $svc->grantToken();
if ($token) {
    echo "Token obtained (masked): " . substr($token, 0, 8) . str_repeat('*', max(0, strlen($token)-8)) . "\n";
} else {
    echo "No token obtained.\n";
}

// 3) Create a simulated payment and attempt createCheckout()
echo "Creating a test Payment and calling createCheckout()...\n";
$payment = Payment::create([
    'provider' => 'bkash',
    'amount' => config('subscription.monthly_amount', 0) ?: 100,
    'payment_method' => 'bkash',
    'status' => 'initiated',
    'metadata' => ['test' => true],
]);

$checkout = $svc->createCheckout($payment);

echo "Checkout result:\n";
echo " - redirect_url: " . ($checkout['redirect_url'] ?? '') . "\n";
echo " - payment_id: " . ($checkout['payment_id'] ?? '') . "\n";

$out['checkout'] = $checkout;
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT));
echo "Updated dump saved to: $path\n";

echo "Done. If token was not obtained, inspect the probe dump for gateway responses.\n";

return 0;
