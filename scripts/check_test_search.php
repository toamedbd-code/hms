<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$kernel->bootstrap();

$cli = $_SERVER['argv'] ?? [];
$term = $cli[1] ?? 'cbc';
$from = $cli[2] ?? '2020-01-01';
$to = $cli[3] ?? date('Y-m-d');
$limit = isset($cli[4]) ? (int)$cli[4] : 50;

echo "Searching for term='{$term}' from={$from} to={$to}\n";

try {
    $qb = \Illuminate\Support\Facades\DB::table('bill_items')
        ->join('billings', 'bill_items.billing_id', '=', 'billings.id')
        ->where('bill_items.category', 'Pathology')
        ->where('bill_items.item_name', 'like', '%' . $term . '%')
        ->whereBetween('billings.created_at', [$from, $to])
        ->select(
            'billings.id as billing_id',
            'billings.case_number as case_number',
            'bill_items.id as bill_item_id',
            'bill_items.item_name',
            'bill_items.unit_price',
            'bill_items.total_amount',
            'bill_items.category'
        )
        ->limit($limit);

    $rows = $qb->get();

    echo "Found: " . $rows->count() . " rows\n";
    foreach ($rows as $i => $r) {
        if ($i >= 10) break;
        echo "- billing_id={$r->billing_id} case_number={$r->case_number} bill_item_id={$r->bill_item_id} item_name={$r->item_name} unit_price={$r->unit_price} total_amount={$r->total_amount}\n";
    }
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// done
