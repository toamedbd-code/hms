<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Billing;
use App\Models\IpdPatient;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

$arg = $argv[1] ?? null;
if (!$arg) {
    echo "Usage: php scripts/fix_ipd_links.php <billing_id|bill_number>\n";
    exit(1);
}

// find billing by numeric id or by bill_number
if (is_numeric($arg)) {
    $billing = Billing::find((int)$arg);
} else {
    $billing = Billing::where('bill_number', $arg)->first();
}

if (!$billing) {
    echo "billing_not_found={$arg}\n";
    exit(2);
}

$patientId = $billing->patient_id;
if (empty($patientId)) {
    echo "billing_has_no_patient id={$billing->id}\n";
    exit(3);
}

echo "Found billing id={$billing->id} bill_number={$billing->bill_number} patient_id={$patientId}\n";

// Try to find a recent IPD patient record for this patient
$ipd = IpdPatient::where('patient_id', $patientId)
    ->whereIn('status', ['Active', 'Inactive'])
    ->orderBy('id', 'desc')
    ->first();

if (!$ipd) {
    echo "no_ipd_record_found_for_patient={$patientId}\n";
    exit(4);
}

echo "Using ipd_patient id={$ipd->id} status={$ipd->status} current_billing_id=" . ($ipd->billing_id ?? 'NULL') . "\n";

$updatedIpd = 0;
DB::beginTransaction();
try {
    if ($ipd->billing_id != $billing->id) {
        $ipd->billing_id = $billing->id;
        $ipd->save();
        $updatedIpd = 1;
    }

    // Update payments that belong to this ipd_patient to reference the billing
    $paymentsQuery = Payment::where('ipd_patient_id', $ipd->id)
        ->where(function ($q) use ($billing) {
            $q->whereNull('billing_id')->orWhere('billing_id', '!=', $billing->id);
        });

    $paymentsToUpdate = $paymentsQuery->get();
    $paymentsCount = $paymentsToUpdate->count();
    $paymentsSumBefore = (float) $paymentsToUpdate->sum('amount');

    foreach ($paymentsToUpdate as $p) {
        $p->billing_id = $billing->id;
        $p->save();
    }

    DB::commit();
    echo "ipd_updated={$updatedIpd} payments_updated_count={$paymentsCount} payments_amount_sum={$paymentsSumBefore}\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "error_updating: " . $e->getMessage() . "\n";
    exit(10);
}

// Summarize totals for this billing after update
$linkedPayments = Payment::where('billing_id', $billing->id)->get();
$totalLinked = (float) $linkedPayments->sum('amount');
$countLinked = $linkedPayments->count();

echo "billing_linked_payments_count={$countLinked} billing_linked_payments_sum={$totalLinked}\n";

echo "Done. Refresh dashboard to verify IPD income.\n";

return 0;
