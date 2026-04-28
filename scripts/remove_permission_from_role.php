<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$roleName = $argv[1] ?? null;
$permissionName = $argv[2] ?? null;

if (!$roleName || !$permissionName) {
    echo "Usage: php remove_permission_from_role.php <role-name> <permission-name>\n";
    exit(1);
}

$role = Role::whereRaw('LOWER(name) = ?', [strtolower($roleName)])->first();
if (!$role) {
    echo "Role not found: {$roleName}\n";
    exit(1);
}

$permission = Permission::whereRaw('LOWER(name) = ?', [strtolower($permissionName)])->first();
if (!$permission) {
    echo "Permission not found: {$permissionName}\n";
    exit(1);
}

try {
    if ($role->hasPermissionTo($permission->name)) {
        $role->revokePermissionTo($permission->name);
        echo "Removed permission '{$permission->name}' from role '{$role->name}'\n";
    } else {
        echo "Role '{$role->name}' did not have permission '{$permission->name}'\n";
    }
} catch (Throwable $e) {
    echo "Error while removing permission: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    echo "Cleared Spatie permission cache.\n";
} catch (Throwable $_) {
    // ignore
}

echo "Done.\n";
