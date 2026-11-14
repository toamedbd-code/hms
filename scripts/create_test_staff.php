<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use App\Models\AdminDetail;
use App\Models\Role;

$role = Role::where('name', 'reception')->where('guard_name', 'admin')->first();
if (! $role) {
    echo "Role reception not found\n";
    exit(1);
}

$email = 'test.receptionist+' . time() . '@example.com';
$admin = Admin::create([
    'first_name' => 'Test',
    'last_name' => 'Reception',
    'email' => $email,
    'phone' => '0123456789',
    'role_id' => $role->id,
    'password' => 'secret123',
]);

// assign role (with no default permissions - permissions must be assigned via admin panel)
try {
    $admin->syncRoles([$role->name]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
} catch (\Throwable $e) {
    echo "Role assign error: " . $e->getMessage() . "\n";
}

// add details with staff id
AdminDetail::create([
    'admin_id' => $admin->id,
    'staff_id' => sprintf('%02d', rand(3, 99)),
]);

echo "Created admin {$admin->id} ({$email}) with role {$role->name}\n";
echo "Please assign permissions to this admin via the admin panel.\n";
