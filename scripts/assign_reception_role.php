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

// Get or create the role
$role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'admin']);

$admin = Admin::where('email', $email)->first();
if (!$admin) {
    echo "No user with email {$email} found.\n";
    exit(1);
}

try {
    // Assign the role to this user
    // Note: The role should have permissions assigned via the admin panel, not via this script
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

echo "Done. Please assign permissions to this role via the admin panel.\n";
