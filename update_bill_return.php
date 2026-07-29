<?php
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$billing = \App\Models\Billing::find(2431);

if($billing) {
    // Update billing with return amount
    $oldReturnAmt = $billing->return_amt;
    $billing->update([
        'return_amt' => 2650.00,
        'total' => 1250.00
    ]);
    
    // Verify update
    $updated = \App\Models\Billing::find(2431);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Bill updated with return amount',
        'before' => [
            'return_amt' => $oldReturnAmt
        ],
        'after' => [
            'id' => $updated->id,
            'bill_number' => $updated->bill_number,
            'return_amt' => $updated->return_amt,
            'created_at' => $updated->created_at->format('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Bill not found'], JSON_PRETTY_PRINT);
}
