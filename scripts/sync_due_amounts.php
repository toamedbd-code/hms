<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Billing;
use App\Models\Payment;
use App\Models\DueCollection;

$updated = 0;
$skipped = 0;

$billings = Billing::where('status', 'Active')->get();
foreach ($billings as $billing) {
    $discountAmount = $billing->discount_type === 'percentage'
        ? (($billing->total * $billing->discount) / 100)
        : $billing->discount;
    $discountAmount = max(0, (float) $discountAmount);
    $extraDiscount = max(0, (float) $billing->extra_flat_discount);
    $netAmount = max(0, (float) $billing->total - $discountAmount - $extraDiscount);

    $paidAmount = Payment::where('billing_id', $billing->id)->sum('amount');
    $dueCollectedTotal = DueCollection::where('billing_id', $billing->id)->sum('collected_amount');

    $computedDue = max(0, $netAmount - $paidAmount - $dueCollectedTotal);
    $computedDueRounded = round($computedDue, 2);
    $stored = $billing->due_amount !== null ? round((float)$billing->due_amount, 2) : null;

    if ($stored !== $computedDueRounded) {
        $billing->due_amount = $computedDueRounded;
        $billing->save();
        $updated++;
        echo "Updated billing {$billing->bill_number} ({$billing->id}) due_amount to {$computedDueRounded}\n";
    } else {
        $skipped++;
    }
}

echo "Done. Updated: {$updated}, Skipped: {$skipped}\n";
