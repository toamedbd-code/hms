<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Charge;

$charges = Charge::query()->whereNull('deleted_at')->where('status','Active')->orderByDesc('id')->limit(50)->get([
    'id','name','standard_charge','tax','charge_category_id','created_at'
]);

echo json_encode($charges->toArray(), JSON_PRETTY_PRINT);
