<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$email = $argv[1] ?? 'eti@gmail.com';
$roleName = $argv[2] ?? 'reception';

$permissions = [
    'frontoffice-list',
    'appoinment-list',
    'website-inbox',
    'opd-patient-list',
];

foreach ($permissions as $p) {
    Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
}

$role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'admin']);
$role->syncPermissions($permissions);

$admin = Admin::where('email', $email)->first();
if (!$admin) {
    echo "No user with email {$email} found.\n";
    exit(1);
}

try {
    // Replace all roles for this user with the reception role to demonstrate strict view
    $admin->syncRoles([$role]);
    echo "Assigned role '{$role->name}' to {$email}\n";
} catch (\Throwable $e) {
    echo "Error assigning role: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    echo "Cleared Spatie permission cache.\n";
} catch (\Throwable $_) {
    // ignore
}

echo "Done.\n";
