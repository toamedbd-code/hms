<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$billing = \App\Models\Billing::with(['billItems','payments','dueCollections'])->find(882);
if (!$billing) {
    echo "Billing 882 not found\n";
    exit(1);
}

$items = [];
foreach ($billing->billItems as $bi) {
    $items[] = [
        'id' => $bi->id,
        'item_name' => $bi->item_name ?? ($bi->description ?? ''),
        'qty' => (float) ($bi->quantity ?? $bi->qty ?? 1),
        'unit_price' => (float) ($bi->unit_price ?? 0),
        'total_amount' => (float) ($bi->total_amount ?? 0),
        'net_amount' => (float) ($bi->net_amount ?? 0),
        'discount' => (float) ($bi->discount ?? 0),
    ];
}

$out = [
    'id' => $billing->id,
    'total' => (float) $billing->total,
    'payable_amount' => (float) $billing->payable_amount,
    'invoice_amount' => (float) $billing->invoice_amount,
    'vat_amount' => (float) $billing->vat_amount,
    'discount' => (float) $billing->discount,
    'discount_type' => $billing->discount_type,
    'extra_flat_discount' => (float) $billing->extra_flat_discount,
    'paid_amt' => (float) $billing->paid_amt,
    'receiving_amt' => (float) $billing->receiving_amt,
    'return_amt' => (float) $billing->return_amt,
    'items' => $items,
    'payments' => $billing->payments->map(fn($p)=>['amount'=>(float)$p->amount,'created_at'=>(string)$p->created_at])->all(),
    'due_collections' => $billing->dueCollections->map(fn($d)=>['amount'=>(float)$d->collected_amount,'created_at'=>(string)$d->collected_at])->all(),
];

echo json_encode($out, JSON_PRETTY_PRINT);
