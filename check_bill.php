<?php
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$billing = \App\Models\Billing::find(2431);

if($billing) {
    echo json_encode([
        'id' => $billing->id,
        'bill_number' => $billing->bill_number,
        'total' => $billing->total,
        'return_amt' => $billing->return_amt,
        'paid_amt' => $billing->paid_amt,
        'due_amount' => $billing->due_amount,
        'status' => $billing->status
    ], JSON_PRETTY_PRINT);
} else {
    echo "Bill not found";
}
