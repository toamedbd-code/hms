<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$billing = \App\Models\Billing::find(871);
echo json_encode([
    'id' => $billing->id,
    'paid_amt' => $billing->paid_amt,
    'payable_amount' => $billing->payable_amount,
    'total' => $billing->total,
    'invoice_amount' => $billing->invoice_amount,
    'receiving_amt' => $billing->receiving_amt,
    'return_amt' => $billing->return_amt,
    'return_amount' => $billing->return_amount,
], JSON_PRETTY_PRINT);
?>
