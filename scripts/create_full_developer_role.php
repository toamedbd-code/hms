<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$email = $argv[1] ?? 'dev-sidebar-check@example.com';
$password = $argv[2] ?? 'DevPass123!';
$first = $argv[3] ?? 'Dev';
$last = $argv[4] ?? 'Tester';
$status = $argv[5] ?? 'Active';

try {
    // Create or get developer role
    $role = Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'admin']);

    // Collect all admin-guard permissions and sync to role
    $allPermissions = Permission::where('guard_name', 'admin')->pluck('name')->toArray();
    if (empty($allPermissions)) {
        $allPermissions = Permission::pluck('name')->toArray();
    }

    $role->syncPermissions($allPermissions);
    echo "Synchronized role 'developer' with " . count($allPermissions) . " permissions.\n";

    // Create or update admin user for testing
    $existing = Admin::where('email', $email)->first();
    if ($existing) {
        $existing->first_name = $first;
        $existing->last_name = $last;
        $existing->password = $password;
        $existing->status = $status;
        $existing->save();
        $admin = $existing;
        $action = 'updated';
    } else {
        $admin = Admin::create([
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'password' => $password,
            'status' => $status,
        ]);
        $action = 'created';
    }

    $admin->assignRole($role);

    echo ucfirst($action) . " admin: {$email} (id: {$admin->id}).\n";

    // Clear spatie permission cache
    try {
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        echo "Cleared Spatie permission cache.\n";
    } catch (Throwable $_) {
        // ignore
    }

    echo "Done. You can login with {$email} / {$password} to verify full access.\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
