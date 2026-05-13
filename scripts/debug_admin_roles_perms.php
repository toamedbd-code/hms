<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = \App\Models\Admin::first();
if (!$admin) { echo "No admin user\n"; exit(1); }

echo "Admin: id={$admin->id}, email={$admin->email}\n";
$roleNames = $admin->getRoleNames()->toArray();
echo "Roles: " . implode(', ', $roleNames) . "\n";

foreach ($admin->roles as $role) {
    echo "Role={$role->name} (id={$role->id}) permissions count=" . $role->permissions->count() . "\n";
    // print a short list of permissions for the role
    $p = $role->permissions->pluck('name')->slice(0,50)->toArray();
    echo "Sample perms: " . implode(', ', $p) . "\n";
}

$adminPerms = $admin->getAllPermissions()->pluck('name')->toArray();
$search = ['bom-list','production-order-list','work-order-list','fixed-asset-list','currency-list','exchange-rate-list','manufacturing-management','fixed-assets-management'];
foreach ($search as $s) {
    echo "$s => " . (in_array($s, $adminPerms) ? 'YES' : 'NO') . "\n";
}

echo "Total admin permissions: " . count($adminPerms) . "\n";
