<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ChargeType;

$types = ChargeType::query()->orderBy('id')->get(['id','name','status']);

echo json_encode($types->toArray(), JSON_PRETTY_PRINT);
