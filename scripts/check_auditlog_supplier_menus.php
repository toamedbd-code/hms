<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Get Audit Log and Supplier Payments menu details from Account Management (parent_id=3)
$menus = DB::table('menus')
    ->where('parent_id', 3)
    ->whereIn('permission_name', ['activity-log-view', 'supplier-payment-list'])
    ->get();

foreach ($menus as $m) {
    echo json_encode((array)$m, JSON_PRETTY_PRINT) . PHP_EOL;
}

// Also show max sorting under pharmacy (id=28)
$maxSort = DB::table('menus')->where('parent_id', 28)->max('sorting');
echo 'pharmacy_max_sort=' . ($maxSort ?? 0) . PHP_EOL;
