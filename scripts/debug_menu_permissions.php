<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;

echo "== Menu record ==\n";
$menu = Menu::where('name', 'Account Management')->first();
print_r($menu ? $menu->toArray() : null);

echo "\n== Permission record ==\n";
$perm = Permission::where('name', 'account-management')->first();
print_r($perm ? $perm->toArray() : null);

echo "\n== Role row (roles table) ==\n";
$role = DB::table('roles')->where('name','Admin')->first();
print_r($role);

$roleId = $role?->id ?? null;
echo "\n== role_has_permissions entries for role_id={$roleId} ==\n";
if ($roleId) {
    $permIds = DB::table('role_has_permissions')->where('role_id', $roleId)->pluck('permission_id')->toArray();
    print_r($permIds);

    echo "\n== permission names for these ids ==\n";
    $permNames = DB::table('permissions')->whereIn('id', $permIds)->pluck('name')->toArray();
    print_r($permNames);
} else {
    echo "No Admin role found.\n";
}

echo "\n== getSideMenus for Admin user (admin@gmail.com) ==\n";
$admin = Admin::where('email', 'admin@gmail.com')->first();
if ($admin) {
    $menus = getSideMenus($admin);
    print_r($menus->toArray());
} else {
    echo "No admin user with email admin@gmail.com found.\n";
}

echo "\nDone.\n";
