<?php
// Usage: php scripts/setup_management_menu.php
// Adds a 'Management' parent menu and common management child menus, creates permissions, and assigns them to 'admin' role.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$roleName = 'admin';
$adminRole = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'admin']);

$parentData = [
    'name' => 'Management',
    'icon' => 'settings',
    'route' => null,
    'description' => 'Administration and system management',
    'sorting' => 1,
    'permission_name' => 'management',
    'status' => 'Active',
];

$parent = Menu::where('name', $parentData['name'])->whereNull('parent_id')->first();
if (! $parent) {
    $parent = new Menu();
    $parent->name = $parentData['name'];
    $parent->parent_id = null;
}
$parent->icon = $parentData['icon'];
$parent->route = $parentData['route'];
$parent->description = $parentData['description'];
$parent->sorting = $parentData['sorting'];
$parent->permission_name = $parentData['permission_name'];
$parent->status = $parentData['status'];
$parent->deleted_at = null;
$parent->save();

// ensure parent permission exists
Permission::firstOrCreate(['name' => $parentData['permission_name'], 'guard_name' => 'admin']);

$children = [
    ['name' => 'Staff List', 'icon' => 'list', 'route' => 'backend.admin.index', 'permission' => 'admin-list'],
    ['name' => 'Role List', 'icon' => 'list', 'route' => 'backend.role.index', 'permission' => 'role-list'],
    ['name' => 'Permission List', 'icon' => 'list', 'route' => 'backend.permission.index', 'permission' => 'permission-list'],
    ['name' => 'Activity Logs', 'icon' => 'activity-log', 'route' => 'activity-logs.index', 'permission' => 'activity-log-view'],
    ['name' => 'System Settings', 'icon' => 'settings', 'route' => 'websetting.create', 'permission' => 'system-settings'],
    ['name' => 'Chart of Accounts', 'icon' => 'wallet', 'route' => 'backend.accounts.index', 'permission' => 'chart-of-accounts'],
    ['name' => 'Journal Entries', 'icon' => 'book', 'route' => 'journal-entry.index', 'permission' => 'journal-entry'],
    ['name' => 'Ledger', 'icon' => 'book', 'route' => 'backend.ledger.index', 'permission' => 'ledger'],
];

$created = [];
foreach ($children as $idx => $c) {
    // create permission if missing
    Permission::firstOrCreate(['name' => $c['permission'], 'guard_name' => 'admin']);

    // If a menu with the same route already exists elsewhere, skip creating
    // a duplicate under Management. This prevents duplicates between the
    // canonical seeders (e.g. Settings, Account Management) and this script.
    if (! empty($c['route'])) {
        $existing = Menu::where('route', $c['route'])->whereNull('deleted_at')->first();
        if ($existing && $existing->parent_id !== $parent->id) {
            $created[] = $existing->name . ' (exists elsewhere)';
            continue;
        }
    }

    $menu = Menu::where('name', $c['name'])->where('parent_id', $parent->id)->first();
    if (! $menu) {
        $menu = new Menu();
        $menu->name = $c['name'];
        $menu->parent_id = $parent->id;
    }
    $menu->icon = $c['icon'];
    $menu->route = $c['route'];
    $menu->description = null;
    $menu->sorting = $idx + 1;
    $menu->permission_name = $c['permission'];
    $menu->status = 'Active';
    $menu->deleted_at = null;
    $menu->save();

    $created[] = $menu->name;
}

// give admin role all the created permissions + management
$permissionsToAssign = array_map(function($c){ return $c['permission']; }, $children);
$permissionsToAssign[] = $parentData['permission_name'];
$adminRole->givePermissionTo($permissionsToAssign);

echo "Created/updated Management menu and children: " . implode(', ', $created) . "\n";
echo "Assigned permissions to role '{$roleName}'.\n";
