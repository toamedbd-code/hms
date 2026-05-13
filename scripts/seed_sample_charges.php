<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$now = now()->toDateTimeString();

$unitId = DB::table('chargeunittypes')->where('name', 'Per Unit')->value('id');
$taxId  = DB::table('chargetaxcategories')->where('name', 'No Tax')->value('id');
$typeId = DB::table('chargetypes')->where('name', 'General')->value('id');

$samples = [
    'Admission Fee',
    'Bed Charge',
    'Doctor Visit Fee',
    'Nursing Charge',
    'Medicine Charge',
    'OT Charge',
    'Pathology Fee',
    'X-Ray Fee',
    'ECG Charge',
    'Oxygen Charge',
];

foreach ($samples as $name) {
    DB::table('charges')->insertOrIgnore([
        'name'              => $name,
        'charge_type_id'    => $typeId,
        'charge_category_id'=> null,
        'unit_type_id'      => $unitId,
        'tax_category_id'   => $taxId,
        'tax'               => 0,
        'standard_charge'   => 0,
        'status'            => 'Active',
        'created_at'        => $now,
        'updated_at'        => $now,
    ]);
}

$count = DB::table('charges')->count();
echo "Sample charges inserted. Total charges: $count\n";
