<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "Assigning permissions to 'developer' role...\n";

$roleName = 'developer';

$role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'admin']);
if ($role->wasRecentlyCreated) {
    echo "Created role: $roleName\n";
} else {
    echo "Role exists: $roleName\n";
}

// Developer role gets ALL permissions (so they can manage other roles)
$allPerms = Permission::pluck('name')->toArray();
$role->syncPermissions($allPerms);
echo "Assigned ALL permissions to role '$roleName'.\n";

echo "Done. If the admin is logged in, they may need to re-login to see updated menus.\n";
