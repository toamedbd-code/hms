<?php
// Usage: php scripts/find_items_tube.php
// Searches Tests, Charges and MedicineInventories for names containing 'tube' or similar.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Test;
use App\Models\Charge;
use App\Models\MedicineInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$patterns = ['%tube%','%v tube%','%v. tube%','%needle%','%syringe%'];

echo "Searching Tests for tube-like names...\n";
$tests = Test::where(function($q) use ($patterns){
    foreach($patterns as $p) $q->orWhere('test_name','like',$p)->orWhere('charge_name','like',$p);
})->get();
echo "Found Tests: " . $tests->count() . "\n";
foreach($tests as $t) {
    echo "[TestID: {$t->id}] test_name: {$t->test_name} | charge_name: {$t->charge_name}\n";
}

echo "\nSearching Charges for tube-like names...\n";
$charges = Charge::where(function($q) use ($patterns){
    foreach($patterns as $p) $q->orWhere('name','like',$p);
})->get();
echo "Found Charges: " . $charges->count() . "\n";
foreach($charges as $c) {
    echo "[ChargeID: {$c->id}] name: {$c->name} | module: {$c->module}\n";
}

echo "\nSearching MedicineInventories for tube-like names...\n";
// Build medicine inventory query defensively: some installs don't have `medicine_code` column.
$meds = MedicineInventory::where(function($q) use ($patterns){
    foreach($patterns as $p) {
        $q->orWhere('medicine_name','like',$p);
        if (Schema::hasColumn('medicineinventories','medicine_code')) {
            $q->orWhere('medicine_code','like',$p);
        }
    }
})->get();
echo "Found Medicines: " . $meds->count() . "\n";
foreach($meds as $m) {
    $code = property_exists($m,'medicine_code') ? ($m->medicine_code ?? '(n/a)') : '(no code column)';
    echo "[MedID: {$m->id}] name: {$m->medicine_name} | code: {$code}\n";
}

echo "\nDone.\n";
