<?php
/**
 * One-off script to sync `paid_amt` and `due_amount` on a Billing
 * Usage: php scripts/fix_bill_paid_sync.php BILL2026030007
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Billing;
use App\Models\Payment;
use App\Models\DueCollection;

try {
    $billNumber = $argv[1] ?? 'BILL2026030007';

    echo "Looking up bill: {$billNumber}\n";

    $billing = Billing::where('bill_number', $billNumber)->first();

    if (! $billing) {
        echo "Billing not found: {$billNumber}\n";
        exit(1);
    }

    echo "Before -> paid_amt: {$billing->paid_amt}, due_amount: {$billing->due_amount}, payable_amount: {$billing->payable_amount}\n";

    $paymentsSum = (float) Payment::where('billing_id', $billing->id)->whereNull('deleted_at')->sum('amount');
    $dueCollected = (float) DueCollection::where('billing_id', $billing->id)->sum('collected_amount');

    $totalPaid = round($paymentsSum + $dueCollected, 2);

    $payable = floatval($billing->payable_amount ?? $billing->total ?? 0);

    $billing->paid_amt = $totalPaid;
    $billing->invoice_amount = $totalPaid;
    $billing->due_amount = max(0, round($payable - $billing->paid_amt, 2));

    if ($billing->paid_amt >= $payable) {
        $billing->payment_status = 'Paid';
    } elseif ($billing->paid_amt > 0) {
        $billing->payment_status = 'Partial';
    } else {
        $billing->payment_status = 'Pending';
    }

    $billing->save();

    echo "Payments sum: {$paymentsSum}, DueCollected: {$dueCollected}\n";
    echo "After  -> paid_amt: {$billing->paid_amt}, due_amount: {$billing->due_amount}, payment_status: {$billing->payment_status}\n";

    exit(0);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(2);
}
