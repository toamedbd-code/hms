<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Menu;
use App\Models\Module;
use App\Models\Permission;
use App\Support\DefaultDeveloperManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PharmacyPrescriptionModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('menus') || !Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function () {
            $pharmacyModule = $this->ensureModule(
                'pharmacy',
                'Pharmacy',
                'Standalone pharmacy add-on: billing, inventory, suppliers, purchases, stock and returns. Not included in Total Management.'
            );

            $prescriptionModule = $this->ensureModule(
                'prescription',
                'Prescription',
                'Standalone prescription add-on: write, print and manage prescriptions independently of OPD/IPD. Not included in Total Management.'
            );

            $this->tagPharmacyMenus();
            $this->ensurePrescriptionMenus();
            $this->syncRolePermissions();
            $this->assignModulesToAdmins($pharmacyModule, $prescriptionModule);
        });

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function ensureModule(string $slug, string $name, string $description): Module
    {
        $module = Module::firstOrNew(['slug' => $slug]);
        $module->name = $name;
        $module->description = $description;
        $module->save();

        return $module;
    }

    private function tagPharmacyMenus(): void
    {
        $pharmacyParents = Menu::query()
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('permission_name', 'pharmacy-bill-list')
                    ->orWhere('permission_name', 'pharmacy-management')
                    ->orWhere('route', 'backend.pharmacybill.index')
                    ->orWhere('route', 'like', 'backend.pharmacy%')
                    ->orWhere('name', 'Pharmacy')
                    ->orWhere('name', 'Pharmacy Management');
            })
            ->get();

        foreach ($pharmacyParents as $parent) {
            $this->tagMenuTree($parent, 'pharmacy');
        }

        Menu::query()
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('route', 'like', 'backend.pharmacy%')
                    ->orWhere('route', 'like', 'backend.pharmacybill%')
                    ->orWhere('route', 'like', 'backend.medicineinventory%')
                    ->orWhere('route', 'like', 'backend.medicinesupplier%')
                    ->orWhere('route', 'like', 'backend.medicinepurchase%')
                    ->orWhere('route', 'like', 'backend.supplierpayment%')
                    ->orWhere('route', 'like', 'backend.productreturn%')
                    ->orWhere('permission_name', 'like', 'pharmacy%')
                    ->orWhere('permission_name', 'like', 'medicine-%')
                    ->orWhere('permission_name', 'like', 'supplier-payment%')
                    ->orWhere('permission_name', 'like', 'product-return%');
            })
            ->update(['module_slug' => 'pharmacy']);

        Permission::query()
            ->where(function ($query) {
                $query->where('name', 'like', 'pharmacy%')
                    ->orWhere('name', 'like', 'medicine-%')
                    ->orWhere('name', 'like', 'supplier-payment%')
                    ->orWhere('name', 'like', 'product-return%')
                    ->orWhere('name', 'like', 'stock-report-list%');
            })
            ->update(['module_slug' => 'pharmacy']);
    }

    private function tagMenuTree(Menu $menu, string $slug): void
    {
        if ((string) $menu->module_slug !== $slug) {
            $menu->module_slug = $slug;
            $menu->save();
        }

        if (!empty($menu->permission_name)) {
            Permission::query()
                ->where('name', $menu->permission_name)
                ->where('guard_name', 'admin')
                ->update(['module_slug' => $slug]);
        }

        Menu::query()
            ->where('parent_id', $menu->id)
            ->whereNull('deleted_at')
            ->get()
            ->each(function ($child) use ($slug) {
                $this->tagMenuTree($child, $slug);
            });
    }

    private function ensurePrescriptionMenus(): void
    {
        $parent = Menu::query()->updateOrCreate(
            [
                'parent_id' => null,
                'permission_name' => 'prescription-management',
            ],
            [
                'name' => 'Prescription',
                'icon' => 'file-text',
                'route' => null,
                'description' => 'Standalone prescription module',
                'sorting' => 12,
                'status' => 'Active',
                'module_slug' => 'prescription',
                'deleted_at' => null,
            ]
        );

        $children = [
            [
                'name' => 'Prescription List',
                'icon' => 'list',
                'route' => 'backend.prescription.index',
                'permission_name' => 'prescription-list',
                'sorting' => 1,
            ],
            [
                'name' => 'Prescription Add',
                'icon' => 'plus-circle',
                'route' => 'backend.prescription.create',
                'permission_name' => 'prescription-add',
                'sorting' => 2,
            ],
            [
                'name' => 'Settings',
                'icon' => 'settings',
                'route' => 'backend.prescription.settings',
                'permission_name' => 'prescription-settings',
                'sorting' => 3,
            ],
        ];

        foreach ($children as $child) {
            Menu::query()->updateOrCreate(
                [
                    'parent_id' => $parent->id,
                    'permission_name' => $child['permission_name'],
                ],
                [
                    'name' => $child['name'],
                    'icon' => $child['icon'],
                    'route' => $child['route'],
                    'description' => null,
                    'sorting' => $child['sorting'],
                    'status' => 'Active',
                    'module_slug' => 'prescription',
                    'deleted_at' => null,
                ]
            );
        }

        $parentPermission = Permission::query()->firstOrCreate(
            ['name' => 'prescription-management', 'guard_name' => 'admin'],
            ['parent_id' => null, 'sorting' => 12, 'module_slug' => 'prescription']
        );
        $parentPermission->module_slug = 'prescription';
        $parentPermission->parent_id = null;
        $parentPermission->sorting = 12;
        $parentPermission->save();

        $childPermissions = [
            'prescription-list' => 1,
            'prescription-add' => 2,
            'prescription-edit' => 3,
            'prescription-delete' => 4,
            'prescription-print' => 5,
            'prescription-list-create' => 6,
            'prescription-list-edit' => 7,
            'prescription-list-delete' => 8,
            'prescription-settings' => 9,
        ];

        foreach ($childPermissions as $name => $sorting) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'admin'],
                ['parent_id' => $parentPermission->id, 'sorting' => $sorting, 'module_slug' => 'prescription']
            );
            $permission->parent_id = $parentPermission->id;
            $permission->sorting = $sorting;
            $permission->module_slug = 'prescription';
            $permission->save();
        }
    }

    private function syncRolePermissions(): void
    {
        $names = [
            'prescription-management',
            'prescription-list',
            'prescription-add',
            'prescription-edit',
            'prescription-delete',
            'prescription-print',
            'prescription-list-create',
            'prescription-list-edit',
            'prescription-list-delete',
            'prescription-settings',
        ];

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ignore
        }

        $roles = Role::query()
            ->where('guard_name', 'admin')
            ->whereIn('name', ['developer', 'Admin'])
            ->get();

        foreach ($roles as $role) {
            try {
                $role->givePermissionTo($names);
            } catch (\Throwable $e) {
                foreach ($names as $name) {
                    try {
                        $role->givePermissionTo($name);
                    } catch (\Throwable $inner) {
                        // ignore
                    }
                }
            }
        }
    }

    private function assignModulesToAdmins(Module $pharmacyModule, Module $prescriptionModule): void
    {
        if (!Schema::hasTable('admin_module')) {
            return;
        }

        $admins = Admin::query()->get();
        foreach ($admins as $admin) {
            if (!method_exists($admin, 'modules')) {
                continue;
            }

            $toAttach = [];

            if (DefaultDeveloperManager::isDeveloper($admin)) {
                $toAttach = [$pharmacyModule->id, $prescriptionModule->id];
            } else {
                try {
                    if (method_exists($admin, 'can') && (
                        $admin->can('pharmacy-bill-list')
                        || $admin->can('pharmacy-management')
                        || $admin->can('medicine-inventory-list')
                    )) {
                        $toAttach[] = $pharmacyModule->id;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            if (!empty($toAttach)) {
                $admin->modules()->syncWithoutDetaching($toAttach);
            }
        }
    }
}
