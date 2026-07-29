<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Billing;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\IpdPatient;
use Illuminate\Support\Facades\DB;

$arg = $argv[1] ?? null;
if (!$arg) {
    echo "Usage: php scripts/inspect_billing.php <billing_id|bill_number>\n";
    exit(1);
}

if (is_numeric($arg)) {
    $billing = Billing::find((int)$arg);
} else {
    $billing = Billing::where('bill_number', $arg)->first();
}

if (!$billing) {
    echo "billing_not_found={$arg}\n";
    exit(2);
}

echo "--- Billing ---\n";
echo "id={$billing->id} bill_number={$billing->bill_number} patient_id={$billing->patient_id} status={$billing->status} payment_status={$billing->payment_status} total={$billing->total} paid_amt={$billing->paid_amt} due_amount={$billing->due_amount} created_at={$billing->created_at}\n";

echo "--- Bill Items ---\n";
$items = BillItem::where('billing_id', $billing->id)->get();
if ($items->isEmpty()) {
    echo "(no bill_items)\n";
} else {
    foreach ($items as $it) {
        echo "id={$it->id} item_id={$it->item_id} name={$it->name} category={$it->category} qty={$it->quantity} total_amount={$it->total_amount} price={$it->unit_price}\n";
    }
}

echo "--- Payments (billing_id={$billing->id}) ---\n";
$payments = Payment::where('billing_id', $billing->id)->get();
if ($payments->isEmpty()) {
    echo "(no payments linked by billing_id)\n";
} else {
    foreach ($payments as $p) {
        echo "id={$p->id} amount={$p->amount} ipd_patient_id={$p->ipd_patient_id} created_at={$p->created_at}\n";
    }
}

echo "--- Payments referencing ipd_patient for this billing's patient (if any) ---\n";
$ipdForPatient = IpdPatient::where('patient_id', $billing->patient_id)->pluck('id')->toArray();
if (empty($ipdForPatient)) {
    echo "(no ipd_patient rows for patient_id={$billing->patient_id})\n";
} else {
    $pays = Payment::whereIn('ipd_patient_id', $ipdForPatient)->get();
    if ($pays->isEmpty()) {
        echo "(no payments linked to ipd_patient rows)\n";
    } else {
        foreach ($pays as $pp) {
            echo "id={$pp->id} amount={$pp->amount} ipd_patient_id={$pp->ipd_patient_id} billing_id={$pp->billing_id} created_at={$pp->created_at}\n";
        }
    }
}

echo "--- Ipd Patients for billing.patient_id ({$billing->patient_id}) ---\n";
$ipds = IpdPatient::where('patient_id', $billing->patient_id)->orderBy('id','desc')->get();
if ($ipds->isEmpty()) {
    echo "(no ipd_patient rows)\n";
} else {
    foreach ($ipds as $row) {
        echo "id={$row->id} patient_id={$row->patient_id} admission_date={$row->admission_date} discharge_date={$row->discharge_date} status={$row->status} billing_id=" . ($row->billing_id ?? 'NULL') . "\n";
    }
}

// Also dump patient basic info if table exists
echo "--- Patient record (if exists) ---\n";
$patient = DB::table('patients')->where('id', $billing->patient_id)->first();
if (!$patient) {
    echo "(no patients row with id={$billing->patient_id})\n";
} else {
    $cols = [];
    foreach ((array)$patient as $k=>$v) {
        $cols[] = "$k=$v";
    }
    echo implode(' ', $cols) . "\n";
}

echo "--- End ---\n";

return 0;
