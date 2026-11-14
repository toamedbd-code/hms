<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$marked = DB::table('due_collections')
    ->where('note', 'like', '%Collected via IPD payment%')
    ->count();

// Mirrors that kept a custom note cannot be detected by note text. They show up
// as a due collection whose amount+time matches an IPD payment on the same bill.
$unmarked = DB::table('due_collections as d')
    ->join('payments as p', function ($join) {
        $join->on('p.billing_id', '=', 'd.billing_id')
            ->whereColumn('p.amount', 'd.collected_amount');
    })
    ->whereNotNull('p.ipd_patient_id')
    ->where(function ($q) {
        $q->whereNull('d.note')->orWhere('d.note', 'not like', '%Collected via IPD payment%');
    })
    ->whereRaw('ABS(TIMESTAMPDIFF(SECOND, p.created_at, d.created_at)) <= 5')
    ->count();

echo 'due_collections total: ' . DB::table('due_collections')->count() . PHP_EOL;
echo 'ipd payments total: ' . DB::table('payments')->whereNotNull('ipd_patient_id')->count() . PHP_EOL;
echo 'mirrors detectable by note: ' . $marked . PHP_EOL;
echo 'mirrors with custom note (amount+time match): ' . $unmarked . PHP_EOL;
