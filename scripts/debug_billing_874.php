<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Billing;
use App\Http\Controllers\Backend\InvoiceController;

$billingId = $argv[1] ?? 0;
if (!(int)$billingId) {
    echo "Usage: php debug_billing_874.php <billing_id>\n";
    exit(1);
}

$billing = Billing::with(['billItems','payments','dueCollections'])->find((int)$billingId);
if (!$billing) {
    echo "Billing {$billingId} not found\n";
    exit(1);
}

$ic = app()->make(App\Http\Controllers\Backend\InvoiceController::class);
$activeItems = collect($billing->billItems)->filter(function($it){ return is_null($it->deleted_at); });
$itemsSum = (float) $activeItems->sum('net_amount');

$totalFromItems = $itemsSum;
$total = $totalFromItems > 0 ? $totalFromItems : (float) ($billing->total ?? 0);

if ($billing->discount_type === 'percentage') {
    $discountPercent = (float) ($billing->discount ?? 0);
    $discountAmount = max(0, ($total * $discountPercent) / 100);
} else {
    $discountPercent = null;
    $discountAmount = max(0, (float) ($billing->discount ?? 0));
}

$extraDiscount = max(0, (float) ($billing->extra_flat_discount ?? 0));

$netPayable = max(0, $total - $discountAmount - $extraDiscount);

$invoiceTime = $billing->created_at ?? now();

$paymentsAtInvoice = (float) \App\Models\Payment::where('billing_id', $billing->id)
    ->where('created_at', '<=', $invoiceTime)
    ->sum('amount');

$dueCollectedAtInvoice = (float) \App\Models\DueCollection::where('billing_id', $billing->id)
    ->where('created_at', '<=', $invoiceTime)
    ->sum('collected_amount');

$paidAtInvoice = max(0, $paymentsAtInvoice + $dueCollectedAtInvoice);

$paymentsSum = (float) \App\Models\Payment::where('billing_id', $billing->id)->sum('amount');
$dueCollected = (float) \App\Models\DueCollection::where('billing_id', $billing->id)->sum('collected_amount');

$billingPaid = (float) ($billing->paid_amt ?? 0);
if ($billingPaid <= 0) {
    $billingPaid = max(0, $paymentsSum + $dueCollected);
}

$totalPaid = max(0, $billingPaid);
$computedDue = max(0, $netPayable - $totalPaid);

$totals = [
    'total_amount' => round($total, 2),
    'discount' => $billing->discount_type === 'percentage' ? round($discountPercent, 2) : round($discountAmount, 2),
    'net_payable' => round($netPayable, 2),
    'paid_at_invoice' => round($paidAtInvoice, 2),
    'paid' => round($totalPaid, 2),
    'due' => round($computedDue, 2)
];

$items = collect($billing->billItems)->filter(function($it){ return is_null($it->deleted_at); })->map(function($it){
    return [
        'id' => $it->id ?? null,
        'item_name' => $it->item_name ?? ($it->description ?? ''),
        'quantity' => isset($it->quantity) ? (float)$it->quantity : (float)($it->qty ?? 1),
        'unit_price' => isset($it->unit_price) ? (float)$it->unit_price : null,
        'net_amount' => (float) ($it->net_amount ?? $it->total_amount ?? 0),
    ];
})->values();

echo json_encode([
    'billing' => [
        'id' => $billing->id,
        'total_db' => (float) $billing->total,
        'payable_db' => (float) $billing->payable_amount,
        'paid_db' => (float) $billing->paid_amt,
        'return_db' => (float) $billing->return_amt,
    ],
    'items_sum' => $itemsSum,
    'items' => $items,
    'calc_totals' => $totals
], JSON_PRETTY_PRINT);
