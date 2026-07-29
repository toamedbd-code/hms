<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\Admin::query()->first();
$user = $user->fresh(['roles.permissions', 'permissions']);

$grantedPermissions = $user->getAllPermissions()
    ->pluck('name')
    ->filter()
    ->map(function ($name) {
        return strtolower(trim((string) $name));
    })
    ->unique()
    ->values();

$hasMenuPermission = function ($permissionName) use ($grantedPermissions) {
    $permissionName = trim((string) $permissionName);
    if ($permissionName === '') {
        return false;
    }

    return $grantedPermissions->contains(strtolower($permissionName));
};

$menus = App\Models\Menu::with(['childrens' => function ($query) {
    $query->whereNull('deleted_at')
        ->where('status', 'Active')
        ->orderBy('sorting', 'ASC')
        ->orderBy('id', 'ASC');
}])
    ->whereNull('parent_id')
    ->whereNull('deleted_at')
    ->where('status', 'Active')
    ->orderBy('sorting', 'ASC')
    ->orderBy('id', 'ASC')
    ->get();

$normalizedMenus = $menus->map(function ($menu) {
    $menuArray = is_array($menu) ? $menu : $menu->toArray();
    $menuArray['childrens'] = collect($menuArray['childrens'] ?? [])
        ->values()
        ->all();

    return $menuArray;
})->values();

$hasAnyPermission = $grantedPermissions->isNotEmpty();
echo "granted perms: ".$grantedPermissions->count().PHP_EOL;
foreach ($normalizedMenus as $menu) {
    $menuArray = is_array($menu) ? $menu : (array) $menu;
    $children = collect($menuArray['childrens'] ?? []);

    $filteredChildren = $children->filter(function ($child) use ($hasMenuPermission, $hasAnyPermission) {
        $permissionName = trim((string) ($child['permission_name'] ?? $child['permission'] ?? ''));
        if ($permissionName !== '' && $hasAnyPermission && !$hasMenuPermission($permissionName)) {
            return false;
        }

        $route = trim((string) ($child['route'] ?? ''));
        if ($route === '') {
            return false;
        }

        return \Illuminate\Support\Facades\Route::has($route);
    })->values()->all();

    $menuPermissionName = trim((string) ($menuArray['permission_name'] ?? $menuArray['permission'] ?? ''));
    $skip = false;
    if ($menuPermissionName !== '' && $hasAnyPermission && !$hasMenuPermission($menuPermissionName)) {
        $skip = true;
        echo 'SKIP parent due permission: ' . ($menuArray['name'] ?? '') . ' perm=' . $menuPermissionName . PHP_EOL;
    }

    $route = trim((string) ($menuArray['route'] ?? ''));
    if ($route === '' && empty($filteredChildren)) {
        $skip = true;
        echo 'SKIP parent due empty route/children: ' . ($menuArray['name'] ?? '') . PHP_EOL;
    }

    if (!$skip) {
        echo 'KEEP parent: ' . ($menuArray['name'] ?? '') . ' route=' . $route . ' children=' . count($filteredChildren) . PHP_EOL;
    }
}
