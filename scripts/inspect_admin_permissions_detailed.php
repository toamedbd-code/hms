<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use Spatie\Permission\Models\Role;

$email = $argv[1] ?? 'toamedbd@gmail.com';
$admin = Admin::where('email', $email)->first();
if (! $admin) {
    echo "Admin not found: $email\n";
    exit(1);
}

echo "Admin ID: {$admin->id}\n";
echo "Email: {$admin->email}\n";
$roles = $admin->getRoleNames()->toArray();
echo "Roles: " . implode(',', $roles) . "\n";
echo "Has role developer: " . (method_exists($admin, 'hasRole') && $admin->hasRole('developer') ? 'true' : 'false') . "\n";

// Direct permissions assigned to the user
try {
    $directPermissions = $admin->permissions()->pluck('name')->toArray();
} catch (\Throwable $e) {
    $directPermissions = [];
}
echo "Direct permission count: " . count($directPermissions) . "\n";
echo "Sample direct perms: " . implode(',', array_slice($directPermissions, 0, 50)) . "\n";

// All permissions (role + direct)
try {
    $allPermissions = $admin->getAllPermissions()->pluck('name')->toArray();
} catch (\Throwable $e) {
    $allPermissions = [];
}
echo "All permission count (getAllPermissions): " . count($allPermissions) . "\n";
echo "Sample all perms: " . implode(',', array_slice($allPermissions, 0, 50)) . "\n";

// Permissions on the developer role itself
$roleObj = Role::where('name', 'developer')->first();
if ($roleObj) {
    try {
        $rolePerms = $roleObj->permissions()->pluck('name')->toArray();
    } catch (\Throwable $e) {
        $rolePerms = [];
    }
    echo "Developer role permission count: " . count($rolePerms) . "\n";
    echo "Sample role perms: " . implode(',', array_slice($rolePerms, 0, 50)) . "\n";
} else {
    echo "Developer role not found\n";
}

// For each role the admin has, list counts
foreach ($admin->roles as $r) {
    try {
        $rPerms = $r->permissions()->pluck('name')->toArray();
    } catch (\Throwable $e) {
        $rPerms = [];
    }
    echo "Role {$r->name} perms: " . count($rPerms) . "\n";
    echo "Sample: " . implode(',', array_slice($rPerms, 0, 20)) . "\n";
}

// List database counts for pivot tables to help debug
try {
    $db = \DB::connection();
    $rolePermCount = $db->table('role_has_permissions')->count();
    $modelPermCount = $db->table('model_has_permissions')->count();
    $modelRoleCount = $db->table('model_has_roles')->count();
    echo "DB pivot counts - role_has_permissions: $rolePermCount, model_has_permissions: $modelPermCount, model_has_roles: $modelRoleCount\n";
} catch (\Throwable $e) {
    // ignore
}
