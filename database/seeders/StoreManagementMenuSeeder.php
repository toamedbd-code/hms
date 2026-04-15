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
                    'permission_name' => 'stock-report-list-create',
                    'sorting' => 19,
                ],
                [
                    'name' => 'Store Dashboard',
                    'icon' => 'box',
                    'route' => 'backend.stock.index',
                    'permission_name' => 'stock-report-list',
                    'sorting' => 20,
                ],
                [
                    'name' => 'Department Requisitions',
                    'icon' => 'clipboard',
                    'route' => 'backend.stock.requisitions',
                    'permission_name' => 'stock-report-list',
                    'sorting' => 20,
                ],
                [
                    'name' => 'GRN Receive',
                    'icon' => 'download-cloud',
                    'route' => 'backend.stock.grns',
                    'permission_name' => 'stock-report-list',
                    'sorting' => 21,
                ],
                [
                    'name' => 'Store Adjustments',
                    'icon' => 'shuffle',
                    'route' => 'backend.stock.adjustments',
                    'permission_name' => 'stock-report-list',
                    'sorting' => 22,
                ],
                [
                    'name' => 'Stock In/Out Entry',
                    'icon' => 'plus-circle',
                    'route' => 'backend.stock.adjustment.create',
                    'permission_name' => 'stock-report-list-create',
                    'sorting' => 22,
                ],
                [
                    'name' => 'Low Stock Report',
                    'icon' => 'alert-triangle',
                    'route' => 'backend.stock.low-stock-report',
                    'permission_name' => 'stock-report-list',
                    'sorting' => 23,
                ],
                [
                    'name' => 'Stock Movement Report',
                    'icon' => 'trending-up',
                    'route' => 'backend.stock.movement-report',
                    'permission_name' => 'stock-report-list',
                    'sorting' => 24,
                ],
                [
                    'name' => 'Monthly Closing',
                    'icon' => 'file-text',
                    'route' => 'backend.stock.monthly-closing',
                    'permission_name' => 'stock-report-list',
                    'sorting' => 25,
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

            $stockBasePermission = Permission::query()->firstOrCreate(
                [
                    'name' => 'stock-report-list',
                    'guard_name' => 'admin',
                ],
                [
                    'parent_id' => $storeParentPermission->id,
                ]
            );

            if ((int) $stockBasePermission->parent_id !== (int) $storeParentPermission->id) {
                $stockBasePermission->parent_id = $storeParentPermission->id;
                $stockBasePermission->save();
            }

            $stockCreatePermission = Permission::query()->firstOrCreate(
                [
                    'name' => 'stock-report-list-create',
                    'guard_name' => 'admin',
                ],
                [
                    'parent_id' => $stockBasePermission->id,
                ]
            );

            if ((int) $stockCreatePermission->parent_id !== (int) $stockBasePermission->id) {
                $stockCreatePermission->parent_id = $stockBasePermission->id;
                $stockCreatePermission->save();
            }

            // Backfill store permissions for roles that already have stock access.
            $stockPermissions = Permission::query()
                ->whereIn('name', ['store-management', 'stock-report-list', 'stock-report-list-create'])
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
