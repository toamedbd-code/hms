<?php
// One-time sync script to create/update Charge records from Test records.
// Usage: from project root run: php scripts/sync_charges.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Test;
use App\Models\Charge;
use App\Models\ChargeType;
use App\Models\ChargeUnitType;
use App\Models\ChargeTaxCategory;
use App\Models\ChargeCategory;

echo "Starting Test -> Charge sync...\n";
$tests = Test::whereIn('category_type', ['Pathology','Radiology','ECG','Ultrasound','IPD','Disposable'])->get();
$count = 0;
foreach ($tests as $t) {
    $name = trim($t->charge_name ?: $t->test_name);
    if (!$name) continue;

    $existing = Charge::whereRaw('LOWER(name)=?', [strtolower($name)])->first();

    $moduleArr = [];
    if ($t->category_type) $moduleArr[] = $t->category_type;
    if (preg_match('/\b(tube|v\.?\s*tube|syringe|needle|glove|gauze|dispos)\b/i', $name)) {
        array_unshift($moduleArr, 'Disposable');
    }
    if (empty($moduleArr)) $moduleArr[] = 'Service';

    $payload = [
        'name' => $name,
        'charge_type_id' => ChargeType::query()->value('id') ?? 1,
        'charge_category_id' => ChargeCategory::query()->value('id') ?? 1,
        'unit_type_id' => ChargeUnitType::query()->value('id') ?? 1,
        'tax_category_id' => ChargeTaxCategory::query()->value('id') ?? 1,
        'tax' => $t->tax ?? 0,
        'standard_charge' => $t->standard_charge ?? $t->amount ?? 0,
        'module' => json_encode(array_values(array_unique($moduleArr))),
        'status' => 'Active',
    ];

    if ($existing) {
        $existing->update($payload);
    } else {
        Charge::create($payload);
    }
    $count++;
}

echo "Synced: {$count} tests into Charges.\n";
echo "Done.\n";
