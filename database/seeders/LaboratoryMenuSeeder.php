<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LaboratoryMenuSeeder extends Seeder
{
    public function run(): void
    {
        $parent = Menu::query()->updateOrCreate(
            ['parent_id' => null, 'permission_name' => 'lab-management'],
            [
                'name' => 'Lab Management',
                'icon' => 'activity',
                'route' => null,
                'sorting' => 27,
                'status' => 'Active',
                'deleted_at' => null,
            ]
        );

        $parentPermission = Permission::query()->firstOrCreate(
            ['name' => 'lab-management', 'guard_name' => 'admin'],
            ['sorting' => 1]
        );

        $children = [
            ['name' => 'Lab Dashboard', 'route' => 'backend.lab.dashboard', 'permission' => 'lab-dashboard', 'icon' => 'home'],
            ['name' => 'Lab Inventory', 'route' => 'backend.lab.inventory', 'permission' => 'lab-inventory', 'icon' => 'package'],
            ['name' => 'Lab Suppliers', 'route' => 'backend.lab.suppliers', 'permission' => 'lab-suppliers', 'icon' => 'users'],
            ['name' => 'Lab Purchase Orders & GRN', 'route' => 'backend.lab.purchase-orders', 'permission' => 'lab-purchase-orders', 'icon' => 'shopping-cart'],
            ['name' => 'Lab Supplier Payments', 'route' => 'backend.lab.purchase-payments', 'permission' => 'lab-purchase-payments', 'icon' => 'credit-card'],
        ];

        Menu::query()
            ->whereIn('route', [
                'backend.lab.patients',
                'backend.lab.tests',
                'backend.lab.orders',
            ])
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'status' => 'Inactive']);

        foreach ($children as $index => $child) {
            Menu::query()->updateOrCreate(
                ['route' => $child['route']],
                [
                    'parent_id' => $parent->id,
                    'name' => $child['name'],
                    'icon' => $child['icon'],
                    'permission_name' => $child['permission'],
                    'sorting' => $index + 1,
                    'status' => 'Active',
                    'deleted_at' => null,
                ]
            );

            Permission::query()->firstOrCreate(
                ['name' => $child['permission'], 'guard_name' => 'admin'],
                ['parent_id' => $parentPermission->id, 'sorting' => $index + 1]
            );
        }

        $permissions = array_merge(['lab-management'], array_column($children, 'permission'));
        $roles = Role::query()->whereHas('permissions', fn ($query) => $query
            ->whereIn('name', ['pathology-list', 'radiology-list', 'stock-management']))
            ->get();
        foreach ($roles as $role) {
            $role->givePermissionTo($permissions);
        }
    }
}
