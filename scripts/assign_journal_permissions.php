<?php

// Usage: php scripts/assign_journal_permissions.php
// Creates journal-related permissions and assigns them to admin@gmail.com (role: admin)

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$adminEmail = 'admin@gmail.com';
$permissions = [
    'chart-of-accounts',
    'journal-entry.create',
    'journal-entry.edit',
    'journal-entry.view',
    'journal-entry.delete',
];

foreach ($permissions as $p) {
    Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
}

$role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
$role->givePermissionTo($permissions);

$admin = Admin::where('email', $adminEmail)->first();
if ($admin) {
    $admin->assignRole($role);
    echo "Assigned role 'admin' and permissions to {$adminEmail}\n";
} else {
    echo "Admin user not found: {$adminEmail}\n";
}
