<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "Assigning permissions to 'developer' role...\n";

$roleName = 'developer';
$permNames = ['ultrasound-reporting', 'xray-reporting', 'pathology-reporting'];

$role = Role::firstOrCreate(['name' => $roleName]);
if ($role->wasRecentlyCreated) {
    echo "Created role: $roleName\n";
} else {
    echo "Role exists: $roleName\n";
}

foreach ($permNames as $pn) {
    $p = Permission::firstOrCreate(['name' => $pn, 'guard_name' => 'admin']);
    if ($p->wasRecentlyCreated) echo "Created permission: $pn\n";
}

// Use permission models with correct guard when assigning
$role->givePermissionTo($permNames);
echo "Assigned permissions to role '$roleName'.\n";

echo "Done. If the admin is logged in, they may need to re-login to see updated menus.\n";
