<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

function resolve_module_slug(string $text): string
{
    $t = strtolower(trim($text));

    if ($t === '') {
        return 'core';
    }

    $contains = static function (array $needles) use ($t): bool {
        foreach ($needles as $n) {
            if ($n !== '' && str_contains($t, $n)) {
                return true;
            }
        }
        return false;
    };

    if ($contains(['dashboard'])) return 'dashboard';
    if ($contains(['account', 'ledger', 'currency', 'exchange-rate', 'journal', 'income', 'expense', 'finance'])) return 'accounting';
    if ($contains(['role', 'permission', 'admin-list', 'user-management', 'human-resource', 'access'])) return 'access-control';
    if ($contains(['patient', 'doctor-portal'])) return 'patient';
    if ($contains(['appoinment', 'appointment', 'website-inbox'])) return 'appointment';
    if ($contains(['opd'])) return 'opd';
    if ($contains(['ipd', 'bed', 'ward', 'floor'])) return 'ipd';
    if ($contains(['pathology', 'test', 'lab'])) return 'pathology';
    if ($contains(['radiology', 'xray', 'ultra', 'ct', 'mri'])) return 'radiology';
    if ($contains(['pharmacy', 'medicine', 'drug'])) return 'pharmacy';
    if ($contains(['blood'])) return 'blood-bank';
    if ($contains(['ambulance'])) return 'ambulance';
    if ($contains(['frontoffice', 'front-office', 'visitor', 'calllog', 'postal', 'complain', 'birthdeath', 'certificate'])) return 'front-office';
    if ($contains(['leave', 'attendance', 'payroll', 'salary', 'duty', 'designation', 'department', 'specialist', 'staff'])) return 'hr';
    if ($contains(['inventory', 'stock', 'supplier', 'purchase', 'item', 'item-charge', 'store-management', 'product'])) return 'inventory';
    if ($contains(['report'])) return 'reports';
    if ($contains(['setting', 'websetting', 'sms-setting', 'module-setting', 'order-setting'])) return 'settings';

    return 'core';
}

DB::beginTransaction();

try {
    $menus = Menu::query()->orderBy('id')->get(['id', 'parent_id', 'name', 'route', 'permission_name', 'module_slug']);
    $menuById = [];
    foreach ($menus as $m) {
        $menuById[$m->id] = $m;
    }

    $topRootCache = [];
    $getTopRootId = function ($menuId) use (&$getTopRootId, &$topRootCache, $menuById) {
        if (isset($topRootCache[$menuId])) {
            return $topRootCache[$menuId];
        }

        $current = $menuById[$menuId] ?? null;
        if (! $current) {
            return null;
        }

        if (empty($current->parent_id)) {
            $topRootCache[$menuId] = $current->id;
            return $current->id;
        }

        $root = $getTopRootId((int) $current->parent_id);
        $topRootCache[$menuId] = $root;
        return $root;
    };

    $rootSlugById = [];
    foreach ($menus as $m) {
        if (! empty($m->parent_id)) {
            continue;
        }

        $seedText = trim(implode(' ', array_filter([
            (string) $m->route,
            (string) $m->permission_name,
            (string) $m->name,
        ])));

        $rootSlugById[$m->id] = resolve_module_slug($seedText);
    }

    $updatedMenus = 0;
    $menuPermissionSlugMap = [];

    foreach ($menus as $m) {
        $rootId = $getTopRootId($m->id);
        $slug = $rootSlugById[$rootId] ?? null;

        if (! $slug) {
            $seedText = trim(implode(' ', array_filter([
                (string) $m->route,
                (string) $m->permission_name,
                (string) $m->name,
            ])));
            $slug = resolve_module_slug($seedText);
        }

        if ((string) $m->module_slug !== (string) $slug) {
            Menu::query()->where('id', $m->id)->update(['module_slug' => $slug]);
            $updatedMenus++;
        }

        if (! empty($m->permission_name)) {
            $menuPermissionSlugMap[strtolower(trim((string) $m->permission_name))] = $slug;
        }
    }

    $permissions = Permission::query()->orderBy('id')->get(['id', 'name', 'parent_id', 'module_slug']);
    $permById = [];
    foreach ($permissions as $p) {
        $permById[$p->id] = $p;
    }

    $resolvedPermissionSlugById = [];
    $resolvePermissionSlug = function ($perm) use (&$resolvePermissionSlug, &$resolvedPermissionSlugById, $menuPermissionSlugMap, $permById) {
        if (isset($resolvedPermissionSlugById[$perm->id])) {
            return $resolvedPermissionSlugById[$perm->id];
        }

        $nameKey = strtolower(trim((string) $perm->name));

        if (isset($menuPermissionSlugMap[$nameKey])) {
            $resolvedPermissionSlugById[$perm->id] = $menuPermissionSlugMap[$nameKey];
            return $resolvedPermissionSlugById[$perm->id];
        }

        if (! empty($perm->parent_id) && isset($permById[$perm->parent_id])) {
            $parentSlug = $resolvePermissionSlug($permById[$perm->parent_id]);
            if (! empty($parentSlug)) {
                $resolvedPermissionSlugById[$perm->id] = $parentSlug;
                return $parentSlug;
            }
        }

        $resolvedPermissionSlugById[$perm->id] = resolve_module_slug($nameKey);
        return $resolvedPermissionSlugById[$perm->id];
    };

    $updatedPermissions = 0;
    foreach ($permissions as $p) {
        $slug = $resolvePermissionSlug($p);
        if ((string) $p->module_slug !== (string) $slug) {
            Permission::query()->where('id', $p->id)->update(['module_slug' => $slug]);
            $updatedPermissions++;
        }
    }

    DB::commit();

    echo "Updated menus: {$updatedMenus}\n";
    echo "Updated permissions: {$updatedPermissions}\n";
    echo "Done\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
