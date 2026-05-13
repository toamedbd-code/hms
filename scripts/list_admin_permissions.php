<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = \App\Models\Admin::first();
if (!$admin) {
    echo "No admin user found\n";
    exit(1);
}
$perms = [];
try {
    $perms = $admin->getAllPermissions()->pluck('name')->toArray();
} catch (\Throwable $e) {
    echo "Error fetching permissions: " . $e->getMessage() . "\n";
    exit(1);
}
foreach ($perms as $p) echo $p . "\n";
