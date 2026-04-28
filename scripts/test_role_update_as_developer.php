<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;

$email = 'toamedbd@gmail.com';
$roleId = 1;

try {
    $admin = Admin::where('email', $email)->first();
    if (! $admin) {
        echo "Admin not found: {$email}\n";
        exit(1);
    }

    echo "Admin id: {$admin->id}; roles: " . implode(',', $admin->getRoleNames()->toArray()) . "\n";

    $roleService = app(\App\Services\RoleService::class);

    $before = $roleService->roleHasPermission($roleId);
    echo "Role {$roleId} permissions before: " . json_encode($before) . "\n";

    $all = \Spatie\Permission\Models\Permission::where('guard_name', 'admin')->pluck('id')->toArray();
    $toAdd = null;
    foreach ($all as $pid) {
        if (! in_array($pid, $before)) {
            $toAdd = $pid;
            break;
        }
    }

    if (! $toAdd) {
        echo "No permission available to add (all assigned)\n";
        exit(0);
    }

    echo "Selected permission id to add: {$toAdd}\n";

    $new = array_values(array_unique(array_merge($before, [$toAdd])));
    $roleService->syncPermissions($roleId, $new);

    $after = $roleService->roleHasPermission($roleId);
    echo "Role {$roleId} permissions after: " . json_encode($after) . "\n";

    if (in_array($toAdd, $after)) {
        echo "Success: permission {$toAdd} added to role {$roleId}\n";
        exit(0);
    }

    echo "Failed to add permission {$toAdd}\n";
    exit(1);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
