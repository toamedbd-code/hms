<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    $menus = Menu::query()
        ->whereNull('deleted_at')
        ->where('status', 'Active')
        ->orderBy('parent_id')
        ->orderBy('sorting')
        ->orderBy('id')
        ->get(['id', 'parent_id', 'permission_name', 'sorting']);

    $permissionByName = Permission::query()
        ->where('guard_name', 'admin')
        ->get(['id', 'name', 'parent_id', 'sorting'])
        ->keyBy(function ($p) {
            return strtolower(trim((string) $p->name));
        });

    $menuById = $menus->keyBy('id');
    $updated = 0;
    $created = 0;
    $duplicateSkipped = 0;
    $appliedPermissionNames = [];

    foreach ($menus as $menu) {
        $permName = strtolower(trim((string) $menu->permission_name));
        if ($permName === '') {
            continue;
        }

        if (!isset($permissionByName[$permName])) {
            $permissionByName[$permName] = Permission::query()->create([
                'name' => $permName,
                'guard_name' => 'admin',
                'parent_id' => null,
                'sorting' => (int) ($menu->sorting ?? 1),
            ]);
            $created++;
        }

        // Some child menus reuse the same permission_name as a parent menu.
        // Apply mapping only once (first match wins) to avoid overriding a
        // valid top-level parent mapping with a child mapping.
        if (isset($appliedPermissionNames[$permName])) {
            $duplicateSkipped++;
            continue;
        }

        $perm = $permissionByName[$permName];

        $targetParentId = null;
        if (!empty($menu->parent_id) && isset($menuById[$menu->parent_id])) {
            $parentMenu = $menuById[$menu->parent_id];
            $parentPermName = strtolower(trim((string) $parentMenu->permission_name));
            if ($parentPermName !== '' && !isset($permissionByName[$parentPermName])) {
                $permissionByName[$parentPermName] = Permission::query()->create([
                    'name' => $parentPermName,
                    'guard_name' => 'admin',
                    'parent_id' => null,
                    'sorting' => (int) ($parentMenu->sorting ?? 1),
                ]);
                $created++;
            }

            if ($parentPermName !== '' && isset($permissionByName[$parentPermName])) {
                $targetParentId = $permissionByName[$parentPermName]->id;
            }
        }

        // Prevent accidental self-referential loops.
        if ((int) ($targetParentId ?? 0) === (int) $perm->id) {
            $targetParentId = null;
        }

        $targetSorting = (int) ($menu->sorting ?? 1);

        $needsUpdate = ((int) ($perm->parent_id ?? 0) !== (int) ($targetParentId ?? 0))
            || ((int) ($perm->sorting ?? 0) !== $targetSorting);

        if ($needsUpdate) {
            Permission::query()->where('id', $perm->id)->update([
                'parent_id' => $targetParentId,
                'sorting' => $targetSorting,
            ]);
            $perm->parent_id = $targetParentId;
            $perm->sorting = $targetSorting;
            $updated++;
        }

        $appliedPermissionNames[$permName] = true;
    }

    // Keep action-level permissions grouped right after their base permission.
    $actionSuffixes = ['-status', '-create', '-edit', '-delete'];
    $actionUpdated = 0;

    // Reload after menu-mapped updates so action mapping uses fresh parent_id.
    $permissionByName = Permission::query()
        ->where('guard_name', 'admin')
        ->get(['id', 'name', 'parent_id', 'sorting'])
        ->keyBy(function ($p) {
            return strtolower(trim((string) $p->name));
        });

    foreach ($permissionByName as $name => $perm) {
        foreach ($actionSuffixes as $suffix) {
            if (!str_ends_with($name, $suffix)) {
                continue;
            }

            $baseName = substr($name, 0, -strlen($suffix));
            if ($baseName === '' || !isset($permissionByName[$baseName])) {
                continue;
            }

            $base = $permissionByName[$baseName];
            // action permissions should be nested under their base permission.
            $targetParentId = $base->id;
            $targetSorting = (int) ($base->sorting ?? 1);

            $needsUpdate = ((int) ($perm->parent_id ?? 0) !== (int) ($targetParentId ?? 0))
                || ((int) ($perm->sorting ?? 0) !== $targetSorting);

            if ($needsUpdate) {
                Permission::query()->where('id', $perm->id)->update([
                    'parent_id' => $targetParentId,
                    'sorting' => $targetSorting,
                ]);
                $actionUpdated++;
            }
            break;
        }
    }

    // Fill sorting gaps serially for each sibling group so permission trees
    // remain stable and contiguous in role-permission UI rendering.
    $serialUpdated = 0;
    $groupedPermissions = Permission::query()
        ->where('guard_name', 'admin')
        ->orderByRaw('COALESCE(parent_id, 0) ASC')
        ->orderBy('sorting', 'ASC')
        ->orderBy('id', 'ASC')
        ->get(['id', 'parent_id', 'sorting'])
        ->groupBy(function ($permission) {
            return (string) ($permission->parent_id ?? 'root');
        });

    foreach ($groupedPermissions as $siblings) {
        $serial = 1;
        foreach ($siblings as $permission) {
            if ((int) ($permission->sorting ?? 0) !== $serial) {
                Permission::query()->where('id', $permission->id)->update([
                    'sorting' => $serial,
                ]);
                $serialUpdated++;
            }
            $serial++;
        }
    }

    DB::commit();

    try {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    } catch (\Throwable $e) {
        // ignore
    }

    echo "Menu-mapped permission creates: {$created}\n";
    echo "Menu-mapped permission updates: {$updated}\n";
    echo "Duplicate menu-permission mappings skipped: {$duplicateSkipped}\n";
    echo "Action permission updates: {$actionUpdated}\n";
    echo "Serial sorting gap updates: {$serialUpdated}\n";
    echo "Done\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
