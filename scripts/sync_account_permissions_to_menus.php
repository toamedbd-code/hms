<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$accountPerm = Permission::where('name', 'account-management')->first();
if (! $accountPerm) {
    echo "account-management permission not found\n";
    exit(1);
}

$parentMenu = Menu::whereRaw("LOWER(name) LIKE ?", ['%account%'])->whereNull('parent_id')->first();
if (! $parentMenu) {
    echo "Account parent menu not found\n";
    exit(1);
}

$childrenPerms = Permission::where('parent_id', $accountPerm->id)->orderBy('id')->get();
if ($childrenPerms->isEmpty()) {
    echo "No child permissions under account-management\n";
    exit(0);
}

$routeMap = [
    'chart-of-accounts' => 'backend.accounts.index',
    'ledger' => 'backend.ledger.index',
    'account-balances' => 'backend.accounts.balances',
    'currency-list' => 'backend.currency.index',
    'exchange-rate-list' => 'backend.exchange-rate.index',
    'journal-entry' => 'journal-entry.index',
];

$iconMap = [
    'chart-of-accounts' => 'wallet',
    'ledger' => 'book',
    'account-balances' => 'bar-chart-2',
    'currency-list' => 'dollar-sign',
    'exchange-rate-list' => 'repeat',
    'journal-entry' => 'book',
];

$adminRole = Role::where('name', 'Admin')->where('guard_name', 'admin')->first();
$developerRole = Role::where('name', 'developer')->where('guard_name', 'admin')->first();

$created = [];
$skipped = [];

foreach ($childrenPerms as $perm) {
    $pname = $perm->name;

    // Skip action-level perms (status/create/edit/delete)
    if (preg_match('/-(status|create|edit|delete)$/', $pname)) {
        $skipped[] = $pname;
        // still ensure permission is given to roles
        if ($adminRole) $adminRole->givePermissionTo($pname);
        if ($developerRole) $developerRole->givePermissionTo($pname);
        continue;
    }

    // check existing menu under parent with same permission_name or route
    $existing = Menu::where('parent_id', $parentMenu->id)
        ->where(function ($q) use ($pname, $routeMap) {
            $q->where('permission_name', $pname);
            if (isset($routeMap[$pname])) $q->orWhere('route', $routeMap[$pname]);
        })->first();

    if ($existing) {
        $skipped[] = $pname;
        if ($adminRole) $adminRole->givePermissionTo($pname);
        if ($developerRole) $developerRole->givePermissionTo($pname);
        continue;
    }

    // create menu
    $menu = new Menu();
    $menuName = ucwords(str_replace(['-', '_'], [' ', ' '], $pname));
    $menu->name = $menuName;
    $menu->parent_id = $parentMenu->id;
    $menu->permission_name = $pname;
    $menu->icon = $iconMap[$pname] ?? 'list';
    $menu->route = $routeMap[$pname] ?? null;
    $menu->status = 'Active';
    $maxSort = Menu::where('parent_id', $parentMenu->id)->max('sorting') ?? 0;
    $menu->sorting = $maxSort + 1;
    $menu->deleted_at = null;
    $menu->save();

    if ($adminRole) $adminRole->givePermissionTo($pname);
    if ($developerRole) $developerRole->givePermissionTo($pname);

    $created[] = $pname;
}

app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

echo "Created menus: " . (count($created) ? implode(', ', $created) : '(none)') . "\n";
echo "Skipped or action-level ignored: " . (count($skipped) ? implode(', ', $skipped) : '(none)') . "\n";
echo "Done\n";
