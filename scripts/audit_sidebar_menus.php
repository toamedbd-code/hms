<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\Route;

function normalize_name(?string $value): string
{
    return strtolower(trim((string) $value));
}

function key_parent(?int $parentId): string
{
    return $parentId === null ? 'root' : (string) $parentId;
}

$menus = Menu::query()
    ->whereNull('deleted_at')
    ->get([
        'id',
        'name',
        'route',
        'parent_id',
        'sorting',
        'permission_name',
        'status',
    ]);

$activeMenus = $menus
    ->filter(function ($menu) {
        return strtolower((string) $menu->status) === 'active';
    })
    ->values();

$menuById = $menus->keyBy('id');

$permissions = Permission::query()
    ->where('guard_name', 'admin')
    ->get(['id', 'name', 'parent_id'])
    ->keyBy(function ($permission) {
        return normalize_name($permission->name);
    });

$routeNames = collect(Route::getRoutes()->getRoutesByName())
    ->keys()
    ->map(function ($name) {
        return normalize_name((string) $name);
    })
    ->filter()
    ->unique()
    ->values();

$issues = [
    'orphan_child_menu' => [],
    'missing_permission_name' => [],
    'missing_permission_record' => [],
    'route_not_found' => [],
    'empty_parent_menu' => [],
    'permission_parent_mismatch' => [],
    'duplicate_active_routes' => [],
    'duplicate_active_permission_name' => [],
    'duplicate_sibling_sorting' => [],
    'sorting_gaps' => [],
];

$childrenByParent = $activeMenus->groupBy(function ($menu) {
    return key_parent($menu->parent_id);
});

foreach ($activeMenus as $menu) {
    $menuId = (int) $menu->id;
    $menuName = (string) $menu->name;
    $menuRoute = normalize_name($menu->route);
    $menuPermission = normalize_name($menu->permission_name);

    if ($menu->parent_id !== null && !$menuById->has((int) $menu->parent_id)) {
        $issues['orphan_child_menu'][] = [
            'menu_id' => $menuId,
            'menu_name' => $menuName,
            'parent_id' => (int) $menu->parent_id,
        ];
    }

    if ($menuPermission === '') {
        $issues['missing_permission_name'][] = [
            'menu_id' => $menuId,
            'menu_name' => $menuName,
            'route' => $menuRoute,
        ];
    } elseif (!$permissions->has($menuPermission)) {
        $issues['missing_permission_record'][] = [
            'menu_id' => $menuId,
            'menu_name' => $menuName,
            'permission_name' => $menuPermission,
        ];
    }

    if ($menuRoute !== '' && !$routeNames->contains($menuRoute)) {
        $issues['route_not_found'][] = [
            'menu_id' => $menuId,
            'menu_name' => $menuName,
            'route' => $menuRoute,
        ];
    }

    $hasChildren = $childrenByParent->has((string) $menuId) && $childrenByParent[(string) $menuId]->isNotEmpty();
    if ($menu->parent_id === null && $menuRoute === '' && !$hasChildren) {
        $issues['empty_parent_menu'][] = [
            'menu_id' => $menuId,
            'menu_name' => $menuName,
            'permission_name' => $menuPermission,
        ];
    }

    if ($menu->parent_id !== null && $menuPermission !== '' && $menuById->has((int) $menu->parent_id)) {
        $parentMenu = $menuById[(int) $menu->parent_id];
        $parentPermissionName = normalize_name($parentMenu->permission_name);

        if (
            $parentPermissionName !== ''
            && $permissions->has($menuPermission)
            && $permissions->has($parentPermissionName)
            && $menuPermission !== $parentPermissionName
        ) {
            $permission = $permissions[$menuPermission];
            $expectedParentId = (int) $permissions[$parentPermissionName]->id;
            $actualParentId = (int) ($permission->parent_id ?? 0);

            if ($actualParentId !== $expectedParentId) {
                $issues['permission_parent_mismatch'][] = [
                    'menu_id' => $menuId,
                    'menu_name' => $menuName,
                    'permission_name' => $menuPermission,
                    'expected_parent_permission' => $parentPermissionName,
                    'expected_parent_id' => $expectedParentId,
                    'actual_parent_id' => $actualParentId,
                ];
            }
        }
    }
}

// Duplicate active route names
$duplicateRoutes = $activeMenus
    ->filter(function ($menu) {
        return normalize_name($menu->route) !== '';
    })
    ->groupBy(function ($menu) {
        return normalize_name($menu->route);
    })
    ->filter(function ($group) {
        return $group->count() > 1;
    });

foreach ($duplicateRoutes as $route => $group) {
    $issues['duplicate_active_routes'][] = [
        'route' => $route,
        'menus' => $group->map(function ($menu) {
            return [
                'menu_id' => (int) $menu->id,
                'menu_name' => (string) $menu->name,
                'parent_id' => $menu->parent_id !== null ? (int) $menu->parent_id : null,
            ];
        })->values()->all(),
    ];
}

// Duplicate active permission names in menus
$duplicatePermissionNames = $activeMenus
    ->filter(function ($menu) {
        return normalize_name($menu->permission_name) !== '';
    })
    ->groupBy(function ($menu) {
        return normalize_name($menu->permission_name);
    })
    ->filter(function ($group) {
        return $group->count() > 1;
    });

foreach ($duplicatePermissionNames as $permissionName => $group) {
    $issues['duplicate_active_permission_name'][] = [
        'permission_name' => $permissionName,
        'menus' => $group->map(function ($menu) {
            return [
                'menu_id' => (int) $menu->id,
                'menu_name' => (string) $menu->name,
                'route' => normalize_name($menu->route),
                'parent_id' => $menu->parent_id !== null ? (int) $menu->parent_id : null,
            ];
        })->values()->all(),
    ];
}

// Duplicate sibling sorting and sorting gaps by sibling group
foreach ($childrenByParent as $parentKey => $siblings) {
    $sorted = $siblings
        ->sortBy(function ($menu) {
            return [(int) ($menu->sorting ?? 0), (int) $menu->id];
        })
        ->values();

    $duplicateSorting = $sorted
        ->groupBy(function ($menu) {
            return (int) ($menu->sorting ?? 0);
        })
        ->filter(function ($group) {
            return $group->count() > 1;
        });

    foreach ($duplicateSorting as $sorting => $group) {
        $issues['duplicate_sibling_sorting'][] = [
            'parent' => $parentKey,
            'sorting' => (int) $sorting,
            'menus' => $group->map(function ($menu) {
                return [
                    'menu_id' => (int) $menu->id,
                    'menu_name' => (string) $menu->name,
                ];
            })->values()->all(),
        ];
    }

    $expected = 1;
    foreach ($sorted as $menu) {
        $actual = (int) ($menu->sorting ?? 0);
        if ($actual !== $expected) {
            $issues['sorting_gaps'][] = [
                'parent' => $parentKey,
                'menu_id' => (int) $menu->id,
                'menu_name' => (string) $menu->name,
                'expected_sorting' => $expected,
                'actual_sorting' => $actual,
            ];
        }
        $expected++;
    }
}

$issueCounts = [];
$totalIssueRows = 0;
foreach ($issues as $type => $rows) {
    $issueCounts[$type] = count($rows);
    $totalIssueRows += count($rows);
}

$report = [
    'summary' => [
        'total_menus_non_deleted' => $menus->count(),
        'total_active_menus' => $activeMenus->count(),
        'total_admin_permissions' => $permissions->count(),
        'total_named_routes' => $routeNames->count(),
        'total_issue_rows' => $totalIssueRows,
        'issue_counts' => $issueCounts,
    ],
    'issues' => $issues,
    'generated_at' => date('Y-m-d H:i:s'),
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
