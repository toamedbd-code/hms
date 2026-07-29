<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BkashService;
use App\Models\Payment;

$svc = new BkashService();
$out = ['timestamp' => date('c')];

// 1) One attempt to obtain token
echo "Attempting single grantToken() call (will not repeat)...\n";
$token = $svc->grantToken();
$out['token_obtained'] = $token ? true : false;
if ($token) {
    echo "Token obtained (masked): " . substr($token,0,8) . str_repeat('*', max(0, strlen($token)-8)) . "\n";
} else {
    echo "No token obtained. Aborting external API calls.\n";
    $path = __DIR__ . '/../storage/logs/bkash-full-one-shot-'.date('Ymd_His').'.json';
    file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT));
    echo "Result saved to: $path\n";
    exit(0);
}

// 2) Create a Payment (will attempt external create)
$payment = Payment::create([
    'provider' => 'bkash',
    'amount' => 100.00,
    'payment_method' => 'bkash',
    'status' => 'initiated',
    'metadata' => ['approval_token' => Illuminate\Support\Str::random(40), 'period' => 'monthly'],
]);
$out['created'] = ['invoice' => $payment->id, 'amount' => $payment->amount, 'created_at' => $payment->created_at->toDateTimeString()];

// 3) Attempt createPayment
echo "Calling createPayment() against configured endpoints...\n";
$create = $svc->createPayment($payment);
$out['create'] = $create;

$paymentId = $create['payment_id'] ?? null;
$redirectUrl = $create['redirect_url'] ?? null;

if ($paymentId) {
    echo "Create returned payment_id: $paymentId\n";
}
if ($redirectUrl) {
    echo "Create returned redirect_url: $redirectUrl\n";
}

// 4) If we have a payment_id, try executePayment
if ($paymentId) {
    echo "Attempting executePayment($paymentId)...\n";
    $exec = $svc->executePayment($paymentId);
    $out['execute'] = $exec;
    if (! empty($exec['raw'])) {
        $trxId = data_get($exec['raw'], 'trxID') ?: data_get($exec['raw'], 'trxId') ?: data_get($exec['raw'], 'transaction_id');
        $out['execute_trx'] = $trxId ?: null;
    }
} else {
    echo "No payment_id from create — skipping execute.\n";
}

// 5) If execute gave trxID, query and search
$trx = $out['execute_trx'] ?? null;
if ($trx) {
    echo "Querying payment by paymentID...\n";
    $q = $svc->queryPayment($paymentId);
    $out['query'] = $q;

    echo "Searching transaction by trxID $trx...\n";
    $s = $svc->searchTransaction($trx);
    $out['search'] = $s;
} else {
    echo "No trxID available to query/search.\n";
}

$path = __DIR__ . '/../storage/logs/bkash-full-one-shot-'.date('Ymd_His').'.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT));

echo "One-shot result saved to: $path\n";
print_r($out);
return 0;
