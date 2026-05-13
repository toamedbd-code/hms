<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    $updated = 0;
    $created = 0;
    $sortingUpdated = 0;

    // Fix Product Return route to match backend resource route name.
    $productReturn = Menu::query()
        ->where(function ($query) {
            $query->where('route', 'backend.pharmacy.return.index')
                ->orWhere('route', 'backend.productreturn.index');
        })
        ->where('name', 'Product Return')
        ->first();

    if ($productReturn && (string) $productReturn->route !== 'backend.productreturn.index') {
        $productReturn->route = 'backend.productreturn.index';
        $productReturn->save();
        $updated++;
        echo "[UPDATED] Product Return route => backend.productreturn.index" . PHP_EOL;
    }

    // Normalize Store Management child mapping to dedicated permission names.
    $storeParent = Menu::query()
        ->whereNull('parent_id')
        ->where(function ($query) {
            $query->where('name', 'Store Management')
                ->orWhere('permission_name', 'store-management');
        })
        ->first();

    if ($storeParent) {
        $storeChildren = [
            [
                'name' => 'Store Item Setup',
                'icon' => 'package',
                'route' => 'backend.stock.item.create',
                'permission_name' => 'store-item-setup',
                'sorting' => 1,
            ],
            [
                'name' => 'Stock Management',
                'icon' => 'box',
                'route' => 'backend.stock.index',
                'permission_name' => 'stock-management',
                'sorting' => 2,
            ],
            [
                'name' => 'Department Requisitions',
                'icon' => 'clipboard',
                'route' => 'backend.stock.requisitions',
                'permission_name' => 'department-requisitions',
                'sorting' => 3,
            ],
            [
                'name' => 'GRN Receive',
                'icon' => 'download-cloud',
                'route' => 'backend.stock.grns',
                'permission_name' => 'grn-receive',
                'sorting' => 4,
            ],
            [
                'name' => 'Store Adjustments',
                'icon' => 'shuffle',
                'route' => 'backend.stock.adjustments',
                'permission_name' => 'store-adjustments',
                'sorting' => 5,
            ],
            [
                'name' => 'Stock In/Out Entry',
                'icon' => 'plus-circle',
                'route' => 'backend.stock.adjustment.create',
                'permission_name' => 'stock-in-out-entry',
                'sorting' => 6,
            ],
            [
                'name' => 'Low Stock Report',
                'icon' => 'alert-triangle',
                'route' => 'backend.stock.low-stock-report',
                'permission_name' => 'low-stock-report',
                'sorting' => 7,
            ],
            [
                'name' => 'Stock Movement Report',
                'icon' => 'trending-up',
                'route' => 'backend.stock.movement-report',
                'permission_name' => 'stock-movement-report',
                'sorting' => 8,
            ],
            [
                'name' => 'Monthly Closing',
                'icon' => 'file-text',
                'route' => 'backend.stock.monthly-closing',
                'permission_name' => 'monthly-closing',
                'sorting' => 9,
            ],
        ];

        foreach ($storeChildren as $child) {
            $menu = Menu::query()->where('route', $child['route'])->first();
            if (!$menu) {
                $menu = new Menu();
                $created++;
                echo "[CREATED] Store child: {$child['name']}" . PHP_EOL;
            }

            $needsUpdate = (int) ($menu->parent_id ?? 0) !== (int) $storeParent->id
                || (string) ($menu->name ?? '') !== $child['name']
                || (string) ($menu->icon ?? '') !== $child['icon']
                || (string) ($menu->permission_name ?? '') !== $child['permission_name']
                || (int) ($menu->sorting ?? 0) !== (int) $child['sorting']
                || strtolower((string) ($menu->status ?? '')) !== 'active';

            $menu->parent_id = $storeParent->id;
            $menu->name = $child['name'];
            $menu->icon = $child['icon'];
            $menu->route = $child['route'];
            $menu->description = null;
            $menu->permission_name = $child['permission_name'];
            $menu->sorting = $child['sorting'];
            $menu->status = 'Active';
            $menu->deleted_at = null;
            $menu->save();

            if ($needsUpdate) {
                $updated++;
            }
        }
    }

    // Normalize menu sorting serially under each sibling group.
    $grouped = Menu::query()
        ->whereNull('deleted_at')
        ->where('status', 'Active')
        ->orderByRaw('COALESCE(parent_id, 0) ASC')
        ->orderBy('sorting', 'ASC')
        ->orderBy('id', 'ASC')
        ->get(['id', 'parent_id', 'sorting'])
        ->groupBy(function ($menu) {
            return (string) ($menu->parent_id ?? 'root');
        });

    foreach ($grouped as $siblings) {
        $serial = 1;
        foreach ($siblings as $menu) {
            if ((int) ($menu->sorting ?? 0) !== $serial) {
                Menu::query()->where('id', $menu->id)->update(['sorting' => $serial]);
                $sortingUpdated++;
            }
            $serial++;
        }
    }

    DB::commit();

    echo "Updated rows: {$updated}" . PHP_EOL;
    echo "Created rows: {$created}" . PHP_EOL;
    echo "Sorting updates: {$sortingUpdated}" . PHP_EOL;
    echo "Done" . PHP_EOL;
} catch (Throwable $e) {
    DB::rollBack();
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
