<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use App\Models\Permission;
use Spatie\Permission\Models\Role;

$email = 'toamedbd@gmail.com';
$admin = Admin::where('email', $email)->first();
if (! $admin) {
    echo "Admin not found: {$email}\n";
    exit(1);
}

try {
    $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'admin']);
    $developerRole = Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'admin']);

    $allPermissions = [];
    try {
        $allPermissions = Permission::pluck('name')->toArray();
    } catch (Throwable $e) {
        $allPermissions = [];
    }

    if (! empty($allPermissions)) {
        $adminRole->syncPermissions($allPermissions);
        $developerRole->syncPermissions($allPermissions);
        echo "Synced " . count($allPermissions) . " permissions to roles.\n";
    } else {
        echo "No permissions found to sync.\n";
    }

    $admin->assignRole($adminRole->name);
    $admin->assignRole($developerRole->name);

    echo "Assigned roles to {$admin->email}\n";
    exit(0);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
