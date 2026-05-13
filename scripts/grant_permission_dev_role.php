<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$perm = Spatie\Permission\Models\Permission::where('name', 'journal-entry')->first();
$role = Spatie\Permission\Models\Role::where('name', 'developer')->first();
if ($role && $perm) {
    $role->givePermissionTo($perm);
    app()[Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    echo "Assigned 'journal-entry' to role developer\n";
} else {
    echo "Role or permission not found\n";
}
