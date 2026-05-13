<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ChargeCategory;

$cats = ChargeCategory::query()->orderBy('id')->get(['id','name','status']);

echo json_encode($cats->toArray(), JSON_PRETTY_PRINT);
