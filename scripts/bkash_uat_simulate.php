<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Payment;
use App\Services\BkashService;
use App\Http\Controllers\Payment\BkashController;
use Illuminate\Http\Request;

echo "Starting simulated UAT sequence...\n";

$amount = 100.00;
$results = ['created' => [], 'actions' => []];

// Create two payments with same amount
$payment1 = Payment::create([
    'provider' => 'bkash',
    'amount' => $amount,
    'payment_method' => 'bkash',
    'status' => 'initiated',
    'metadata' => ['approval_token' => Illuminate\Support\Str::random(40), 'period' => 'monthly'],
]);
$results['created'][] = [
    'invoice' => $payment1->id,
    'provider_payment_id' => $payment1->provider_payment_id,
    'amount' => $payment1->amount,
    'created_at' => $payment1->created_at->toDateTimeString(),
];

$payment2 = Payment::create([
    'provider' => 'bkash',
    'amount' => $amount,
    'payment_method' => 'bkash',
    'status' => 'initiated',
    'metadata' => ['approval_token' => Illuminate\Support\Str::random(40), 'period' => 'monthly'],
]);
$results['created'][] = [
    'invoice' => $payment2->id,
    'provider_payment_id' => $payment2->provider_payment_id,
    'amount' => $payment2->amount,
    'created_at' => $payment2->created_at->toDateTimeString(),
];

// Simulate Approval for payment1 via controller method (publicSimulateApprove expects Request)
$controller = new BkashController();
$req1 = Request::create('/payment/bkash/simulate-public/'.$payment1->id.'/approve', 'POST', [
    'approval_token' => data_get($payment1->metadata, 'approval_token'),
]);
try {
    $resp = $controller->publicSimulateApprove($req1, $payment1);
    $payment1->refresh();
    $results['actions'][] = [
        'invoice' => $payment1->id,
        'action' => 'approved',
        'provider_payment_id' => $payment1->provider_payment_id,
        'status' => $payment1->status,
        'timestamp' => now()->toDateTimeString(),
    ];
} catch (Exception $e) {
    $results['actions'][] = [
        'invoice' => $payment1->id,
        'action' => 'approve_failed',
        'error' => $e->getMessage(),
    ];
}

// Simulate failed attempts for payment2: mark status failed and set an error message
$payment2->status = 'failed';
$payment2->metadata = array_merge($payment2->metadata ?? [], ['error' => 'Simulated invalid OTP x3']);
$payment2->save();
$results['actions'][] = [
    'invoice' => $payment2->id,
    'action' => 'marked_failed',
    'provider_payment_id' => $payment2->provider_payment_id,
    'status' => $payment2->status,
    'timestamp' => now()->toDateTimeString(),
];

// Save results
$path = __DIR__ . '/../storage/logs/bkash-uat-simulate-'.date('Ymd_His').'.json';
file_put_contents($path, json_encode($results, JSON_PRETTY_PRINT));

echo "Simulation completed. Results saved to: $path\n";
print_r($results);
return 0;
