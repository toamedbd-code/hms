<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$now = now()->toDateTimeString();

// 1. Charge Types
DB::table('chargetypes')->insert([
    ['name' => 'General',      'modules' => 'ipd,opd', 'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['name' => 'Consultation', 'modules' => 'ipd,opd', 'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['name' => 'Procedure',    'modules' => 'ipd,opd', 'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['name' => 'Laboratory',   'modules' => 'ipd,opd', 'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['name' => 'Room / Bed',   'modules' => 'ipd',     'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
]);
$generalTypeId = DB::table('chargetypes')->where('name', 'General')->value('id');
echo "chargetypes seeded. General ID: $generalTypeId\n";

// 2. Charge Categories (requires charge_type_id)
DB::table('chargecategories')->insert([
    ['charge_type_id' => $generalTypeId, 'name' => 'General',     'description' => 'General category',     'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['charge_type_id' => $generalTypeId, 'name' => 'OPD Service', 'description' => 'OPD service category', 'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['charge_type_id' => $generalTypeId, 'name' => 'IPD Service', 'description' => 'IPD service category', 'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
]);
echo "chargecategories seeded\n";

// 3. Charge Unit Types
DB::table('chargeunittypes')->insert([
    ['name' => 'Per Unit',  'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['name' => 'Per Day',   'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['name' => 'Per Visit', 'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['name' => 'Per Test',  'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['name' => 'Flat',      'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
]);
echo "chargeunittypes seeded\n";

// 4. Charge Tax Categories
DB::table('chargetaxcategories')->insert([
    ['name' => 'No Tax',  'percentage' => 0.00, 'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['name' => 'VAT 5%',  'percentage' => 5.00, 'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
    ['name' => 'VAT 15%', 'percentage' => 15.00,'status' => 'Active', 'created_at' => $now, 'updated_at' => $now],
]);
echo "chargetaxcategories seeded\n";

echo "\nAll charge dependency tables seeded successfully!\n";
