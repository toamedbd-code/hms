<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TestCategory;
use App\Models\Test;
use App\Models\ChargeCategory;

$category = TestCategory::create([
    'name' => 'AutoTestCategory',
    'status' => 'Active'
]);

$chargeCategory = ChargeCategory::query()->where('name','Pathology')->first();
if (!$chargeCategory) {
    $chargeCategory = ChargeCategory::create([
        'charge_type_id' => \App\Models\ChargeType::query()->value('id') ?? 1,
        'name' => 'Pathology',
        'description' => 'Auto-created Pathology category',
        'status' => 'Active'
    ]);
}

$test = Test::create([
    'category_type' => 'Pathology',
    'test_name' => 'AUTO IPD Admission Fee Test',
    'test_short_name' => 'AIPD',
    'test_type' => 'AUTO',
    'test_category_id' => $category->id,
    'method' => 'Auto',
    'report_days' => 1,
    'charge_category_id' => $chargeCategory->id,
    'charge_name' => 'IPD Admission Fee',
    'tax' => 0,
    'standard_charge' => 500,
    'amount' => 500,
    'test_parameters' => json_encode([]),
    'status' => 'Active'
]);

echo json_encode(['category'=>$category->toArray(),'test'=>$test->toArray()], JSON_PRETTY_PRINT);
