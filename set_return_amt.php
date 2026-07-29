<?php
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$billingId = 2431;
$returnAmount = 2650;

$billing = \App\Models\Billing::find($billingId);

if($billing) {
    $oldReturn = $billing->return_amt;
    $billing->return_amt = $returnAmount;
    $billing->save();
    
    echo json_encode([
        'status' => 'success',
        'billing_id' => $billing->id,
        'bill_number' => $billing->bill_number,
        'old_return_amt' => $oldReturn,
        'new_return_amt' => $billing->return_amt,
        'paid_amt' => $billing->paid_amt,
        'total' => $billing->total
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Bill not found'
    ], JSON_PRETTY_PRINT);
}
