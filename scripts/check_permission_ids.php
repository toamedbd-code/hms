<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;

$ids = array_map('intval', array_filter(isset($argv[1]) ? explode(',', $argv[1]) : range(23,39)));
$perms = Permission::whereIn('id', $ids)->get();

$found = [];
foreach ($perms as $p) {
    $found[] = [
        'id' => $p->id,
        'name' => $p->name,
        'guard_name' => $p->guard_name,
        'module_slug' => $p->module_slug ?? null,
    ];
}

echo "Checking IDs: " . implode(',', $ids) . "\n";
if (count($found) === 0) {
    echo "No permissions found for those ids.\n";
} else {
    foreach ($found as $f) {
        echo "ID: {$f['id']} Name: {$f['name']} Guard: {$f['guard_name']} Module: {$f['module_slug']}\n";
    }
}

$missing = array_diff($ids, array_map(function($x){return $x['id'];}, $found));
if (count($missing)) {
    echo "Missing IDs: " . implode(',', $missing) . "\n";
}
