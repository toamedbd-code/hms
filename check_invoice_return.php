<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$billing = App\Models\Billing::find(2423);
if (!$billing) {
    echo "billing_not_found\n";
    exit(1);
}

$request = new Illuminate\Http\Request();
$controller = $app->make(App\Http\Controllers\Backend\InvoiceController::class);
$method = new ReflectionMethod($controller, 'resolveInvoiceReturnAmount');
$method->setAccessible(true);
$return = $method->invoke($controller, $billing, $request, 0);

echo "billing_id=" . $billing->id . PHP_EOL;
echo "return_amt=" . $billing->return_amt . PHP_EOL;
echo "receiving_amt=" . $billing->receiving_amt . PHP_EOL;
echo "invoice_amount=" . $billing->invoice_amount . PHP_EOL;
echo "paid_amt=" . $billing->paid_amt . PHP_EOL;
echo "payable_amount=" . $billing->payable_amount . PHP_EOL;
echo "resolved_return=" . $return . PHP_EOL;
