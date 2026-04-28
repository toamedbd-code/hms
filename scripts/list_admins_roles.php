<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admins = \App\Models\Admin::all();
foreach ($admins as $admin) {
    $roles = $admin->getRoleNames()->toArray();
    $perms = $admin->getAllPermissions()->pluck('name')->toArray();
    echo "ID: {$admin->id} Email: {$admin->email} Roles: [" . implode(',', $roles) . "] Perms: " . count($perms) . "\n";
}
