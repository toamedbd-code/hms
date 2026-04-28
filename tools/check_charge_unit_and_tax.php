<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ChargeUnitType;
use App\Models\ChargeTaxCategory;

$units = ChargeUnitType::query()->get(['id','name','status']);
$taxcats = ChargeTaxCategory::query()->get(['id','name','status']);

echo json_encode(['units'=> $units->toArray(), 'taxcats' => $taxcats->toArray()], JSON_PRETTY_PRINT);
