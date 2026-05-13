<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$parent = Menu::whereRaw('LOWER(name) LIKE ?', ['%account management%'])->whereNull('parent_id')->first();
if (! $parent) {
    echo "Account Management parent not found\n";
    exit(1);
}

$menuName = 'Journal Entries';
$permissionName = 'journal-entry';
$route = 'journal-entry.index';

// create permission if missing
$perm = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'admin']);

// create menu under parent if missing
$menu = Menu::where('parent_id', $parent->id)->where('name', $menuName)->first();
if (! $menu) {
    $menu = new Menu();
    $menu->parent_id = $parent->id;
    $menu->name = $menuName;
}
$menu->icon = 'book';
$menu->route = $route;
$menu->permission_name = $permissionName;
$menu->status = 'Active';
$menu->deleted_at = null;
$menu->sorting = 10; // place near the end; adjust if needed
$menu->save();

// assign permission to Admin role
$adminRole = Role::where('name', 'Admin')->where('guard_name', 'admin')->first();
if ($adminRole) {
    $adminRole->givePermissionTo($permissionName);
}

// clear Spatie cache
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

// print children now
$children = Menu::where('parent_id', $parent->id)->orderBy('sorting')->orderBy('id')->get();
echo "Added/updated menu: {$menu->name}\n";
echo "Current children under {$parent->name}:\n";
foreach ($children as $c) {
    echo "ID: {$c->id} | Name: {$c->name} | perm:" . ($c->permission_name ?? '') . " | route:{$c->route} | module:{$c->module_slug} | status:{$c->status} | sorting:{$c->sorting}\n";
}
