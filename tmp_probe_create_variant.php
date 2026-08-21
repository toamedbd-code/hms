<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = (new class extends App\Http\Controllers\Payment\BkashController {
    public function exposeGrantToken(): ?string
    {
        return $this->grantToken();
    }
})->exposeGrantToken();

if (! $token) {
    echo "NO_TOKEN\n";
    exit(1);
}

echo "TOKEN_OK\n";

$base = config('bkash.production_base_url');
$url = rtrim($base, '/') . '/' . ltrim(config('bkash.create_payment_endpoint'), '/');
$variants = [
    'minimal' => [
        'mode' => '0011',
        'callbackURL' => 'https://merchant-callback.example.com/payment/bkash/callback',
        'amount' => '100.00',
        'currency' => 'BDT',
        'intent' => 'sale',
        'merchantInvoiceNumber' => '1001',
    ],
    'payer_ref_blank' => [
        'mode' => '0011',
        'callbackURL' => 'https://merchant-callback.example.com/payment/bkash/callback',
        'amount' => '100.00',
        'currency' => 'BDT',
        'intent' => 'sale',
        'merchantInvoiceNumber' => '1001',
        'payerReference' => '',
    ],
    'merchant_number' => [
        'mode' => '0011',
        'callbackURL' => 'https://merchant-callback.example.com/payment/bkash/callback',
        'amount' => '100.00',
        'currency' => 'BDT',
        'intent' => 'sale',
        'merchantInvoiceNumber' => '1001',
        'payerReference' => '01410592638',
    ],
    'customer_msisdn_blank' => [
        'mode' => '0011',
        'callbackURL' => 'https://merchant-callback.example.com/payment/bkash/callback',
        'amount' => '100.00',
        'currency' => 'BDT',
        'intent' => 'sale',
        'merchantInvoiceNumber' => '1001',
        'payerReference' => '',
        'customerMsisdn' => '',
    ],
];

foreach ($variants as $name => $payload) {
    $response = Illuminate\Support\Facades\Http::acceptJson()
        ->asJson()
        ->timeout(30)
        ->withHeaders([
            'Authorization' => $token,
            'X-App-Key' => config('bkash.app_key'),
        ])
        ->post($url, $payload);

    echo "=== $name ===\n";
    echo 'status=' . $response->status() . "\n";
    echo $response->body() . "\n\n";
}
