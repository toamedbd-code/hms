<?php
// diagnostics: compare Tests -> Charges sync results
// Run from project root: php scripts/diagnose_charges.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Test;
use App\Models\Charge;
use Illuminate\Support\Facades\Schema;

echo "Running diagnostics...\n";

$categories = ['Pathology','Radiology','ECG','Ultrasound','IPD'];
$tests = Test::whereIn('category_type', $categories)->get();
$testsCount = $tests->count();

echo "Tests matching categories (" . implode(',', $categories) . "): {$testsCount}\n";

// Charges that contain any of those module names or Disposable

// Some installations may not have a `module` column on `charges`.
// Detect and gracefully fall back to broad diagnostics if missing.
if (Schema::hasColumn('charges', 'module')) {
    $chargeQuery = Charge::query();
    foreach ($categories as $cat) {
        $chargeQuery->orWhere('module', 'like', "%{$cat}%");
    }
    $chargeQuery->orWhere('module', 'like', '%Disposable%');
    $chargesMatched = $chargeQuery->get();
    $chargesMatchedCount = $chargesMatched->count();
} else {
    echo "Warning: 'module' column not found on 'charges' table — falling back to name-based diagnostics.\n";
    $chargesMatched = Charge::all();
    $chargesMatchedCount = $chargesMatched->count();
}

$totalCharges = Charge::count();

// distinct lowercased charge names among matched charges
$distinctMatchedNames = $chargesMatched->map(function($c){ return mb_strtolower(trim($c->name ?? '')); })->unique()->filter()->values();
$distinctMatchedNamesCount = $distinctMatchedNames->count();

echo "Charges with matching modules or Disposable: {$chargesMatchedCount}\n";
echo "Distinct charge names among those: {$distinctMatchedNamesCount}\n";
echo "Total charges table rows: {$totalCharges}\n";

// Build a set of normalized names from Charges to compare with Tests
$normalize = function($s){
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9\s]/u', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
};

$chargeNameSet = [];
foreach (Charge::all() as $c) {
    $n = $normalize($c->name ?? '');
    if ($n !== '') $chargeNameSet[$n] = true;
}

$testsNotMatched = [];
foreach ($tests as $t) {
    $candidate = trim($t->charge_name ?: $t->test_name ?: '');
    $n = $normalize($candidate);
    if ($n === '') continue;
    if (!isset($chargeNameSet[$n])) {
        $testsNotMatched[] = [
            'test_id' => $t->id,
            'test_name' => (string) $t->test_name,
            'charge_name' => (string) $t->charge_name,
            'normalized' => $n,
        ];
    }
}

echo "Tests without a matching Charge name (by normalized name): " . count($testsNotMatched) . "\n";
if (count($testsNotMatched) > 0) {
    echo "Sample (up to 30):\n";
    $sample = array_slice($testsNotMatched, 0, 30);
    foreach ($sample as $s) {
        echo " - [TestID: {$s['test_id']}] {$s['test_name']} | charge_name: {$s['charge_name']} | norm: {$s['normalized']}\n";
    }
}

// Charges that do not seem to originate from Tests (module empty or not among categories)
$chargeNotFromTest = [];
foreach ($chargesMatched as $c) {
    $n = $normalize($c->name ?? '');
    // if normalized name not present in any test normalized names, mark
    $foundInTest = false;
    foreach ($tests as $t) {
        $tn = $normalize($t->charge_name ?: $t->test_name ?: '');
        if ($tn !== '' && $tn === $n) { $foundInTest = true; break; }
    }
    if (!$foundInTest) {
        $chargeNotFromTest[] = [ 'id' => $c->id, 'name' => $c->name, 'module' => $c->module ];
    }
}

echo "Charges matched by module but not found in Test names: " . count($chargeNotFromTest) . "\n";
if (count($chargeNotFromTest) > 0) {
    echo "Sample (up to 30):\n";
    foreach (array_slice($chargeNotFromTest, 0, 30) as $c) {
        echo " - [ChargeID: {$c['id']}] {$c['name']} | module: {$c['module']}\n";
    }
}

echo "Diagnostics complete.\n";

return 0;
