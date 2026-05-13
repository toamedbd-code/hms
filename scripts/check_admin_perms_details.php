<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;

$admin = Admin::first();
if (! $admin) { echo "No admin user\n"; exit(1); }

echo "Admin email: " . ($admin->email ?? '') . "\n";
try {
    echo "Roles: \n";
    foreach ($admin->roles as $r) echo " - " . $r->name . "\n";
} catch (Exception $e) { echo "Cannot read roles\n"; }

try {
    $perms = $admin->getAllPermissions()->pluck('name')->map(function($n){ return (string)$n; })->toArray();
    echo "Total permissions: " . count($perms) . "\n";
    $found = false;
    foreach ($perms as $p) {
        if (stripos($p, 'journal') !== false) $found = true;
    }
    echo "Has any journal permission: " . ($found ? 'yes' : 'no') . "\n";
    echo "Has 'journal-entry': " . (in_array('journal-entry', $perms) ? 'yes' : 'no') . "\n";
} catch (Exception $e) {
    echo "Cannot read permissions: " . $e->getMessage() . "\n";
}

