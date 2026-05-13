<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StoreManagementMenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $storeParentMenu = Menu::query()->updateOrCreate(
                [
                    'parent_id' => null,
                    'permission_name' => 'store-management',
                ],
                [
                    'name' => 'Store Management',
                    'icon' => 'archive',
                    'route' => null,
                    'description' => null,
                    'sorting' => 26,
                    'status' => 'Active',
                    'deleted_at' => null,
                ]
            );

            $storeParentPermission = Permission::query()->firstOrCreate(
                [
                    'name' => 'store-management',
                    'guard_name' => 'admin',
                ]
            );

            $desiredChildren = [
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

            foreach ($desiredChildren as $child) {
                $menu = Menu::query()->where('route', $child['route'])->first();

                if (!$menu) {
                    $menu = new Menu();
                }

                $menu->parent_id = $storeParentMenu->id;
                $menu->name = $child['name'];
                $menu->icon = $child['icon'];
                $menu->route = $child['route'];
                $menu->description = null;
                $menu->sorting = $child['sorting'];
                $menu->permission_name = $child['permission_name'];
                $menu->status = 'Active';
                $menu->deleted_at = null;
                $menu->save();
            }

            $storePermissionNames = [
                'store-item-setup',
                'stock-management',
                'department-requisitions',
                'grn-receive',
                'store-adjustments',
                'stock-in-out-entry',
                'low-stock-report',
                'stock-movement-report',
                'monthly-closing',
            ];

            foreach ($storePermissionNames as $index => $permissionName) {
                $permission = Permission::query()->firstOrCreate(
                    [
                        'name' => $permissionName,
                        'guard_name' => 'admin',
                    ],
                    [
                        'parent_id' => $storeParentPermission->id,
                        'sorting' => $index + 1,
                    ]
                );

                if ((int) ($permission->parent_id ?? 0) !== (int) $storeParentPermission->id || (int) ($permission->sorting ?? 0) !== ($index + 1)) {
                    $permission->parent_id = $storeParentPermission->id;
                    $permission->sorting = $index + 1;
                    $permission->save();
                }
            }

            // Backfill store permissions for roles that already have stock access.
            $stockPermissions = Permission::query()
                ->whereIn('name', array_merge(['store-management'], $storePermissionNames))
                ->where('guard_name', 'admin')
                ->pluck('name')
                ->all();

            if (!empty($stockPermissions)) {
                $roles = Role::query()
                    ->whereHas('permissions', function ($query) {
                        $query->whereIn('name', ['stock-report-list', 'stock-report-list-create', 'pharmacy-management']);
                    })
                    ->get();

                foreach ($roles as $role) {
                    $role->givePermissionTo($stockPermissions);
                }
            }
        });
    }
}
