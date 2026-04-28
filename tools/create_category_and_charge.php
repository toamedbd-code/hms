<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ChargeCategory;
use App\Models\Charge;

$chargeTypeId = \App\Models\ChargeType::query()->value('id') ?? 1;

$category = ChargeCategory::create([
    'charge_type_id' => $chargeTypeId,
    'name' => 'Pathology',
    'description' => 'Auto-created Pathology category',
    'status' => 'Active',
]);

$charge = Charge::create([
    'name' => 'IPD Admission Fee',
    'charge_type_id' => $chargeTypeId,
    'charge_category_id' => $category->id,
    'unit_type_id' => \App\Models\ChargeUnitType::query()->value('id') ?? 1,
    'tax_category_id' => \App\Models\ChargeTaxCategory::query()->value('id') ?? 1,
    'tax' => 0,
    'standard_charge' => 500,
    'status' => 'Active',
]);

echo json_encode(['category' => $category->toArray(), 'charge' => $charge->toArray()], JSON_PRETTY_PRINT);
