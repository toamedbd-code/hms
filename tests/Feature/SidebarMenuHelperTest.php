<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SidebarMenuHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_conservative_fallback_menu_when_permission_filtering_would_empty_the_sidebar(): void
    {
        $role = Role::query()->create([
            'name' => 'sidebar-test-role',
            'guard_name' => 'admin',
        ]);

        $admin = Admin::query()->create([
            'first_name' => 'Sidebar',
            'last_name' => 'Test',
            'email' => 'sidebar@example.com',
            'phone' => '01700000000',
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'Active',
        ]);

        $admin->assignRole($role);

        Menu::query()->create([
            'name' => 'Reports',
            'icon' => 'bar-chart',
            'route' => 'backend.dashboard',
            'module_slug' => null,
            'description' => 'Reports section',
            'sorting' => 10,
            'parent_id' => null,
            'permission_name' => 'reports-view',
            'status' => 'Active',
        ]);

        $menus = getSideMenus($admin);

        $this->assertNotEmpty($menus);
        $this->assertTrue($menus->contains(function ($menu) {
            return trim((string) ($menu['route'] ?? $menu->route ?? '')) === 'backend.dashboard';
        }));
    }

    public function test_site_purchase_is_hidden_from_the_sidebar(): void
    {
        $role = Role::query()->create([
            'name' => 'site-purchase-sidebar-role',
            'guard_name' => 'admin',
        ]);

        $inventoryPermission = Permission::query()->firstOrCreate([
            'name' => 'inventory-management',
            'guard_name' => 'admin',
        ]);
        $sitePurchasePermission = Permission::query()->firstOrCreate([
            'name' => 'site-purchase-list',
            'guard_name' => 'admin',
        ]);
        $role->givePermissionTo($inventoryPermission, $sitePurchasePermission);

        $admin = Admin::query()->create([
            'first_name' => 'Site Purchase',
            'last_name' => 'Sidebar Test',
            'email' => 'site-purchase-sidebar@example.com',
            'phone' => '01700000003',
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'Active',
        ]);
        $admin->assignRole($role);

        $inventoryMenu = Menu::query()->create([
            'name' => 'Inventory',
            'icon' => 'inventory',
            'route' => 'backend.inventory.index',
            'sorting' => 1,
            'parent_id' => null,
            'permission_name' => 'inventory-management',
            'status' => 'Active',
        ]);
        Menu::query()->create([
            'name' => 'Site Purchase',
            'icon' => 'shopping-cart',
            'route' => 'backend.sitepurchase.index',
            'sorting' => 2,
            'parent_id' => $inventoryMenu->id,
            'permission_name' => 'site-purchase-list',
            'status' => 'Active',
        ]);

        $menus = getSideMenus($admin);
        $childMenus = collect($menus)->flatMap(fn ($menu) => $menu['childrens'] ?? []);

        $this->assertFalse($childMenus->contains(
            fn ($menu) => ($menu['route'] ?? '') === 'backend.sitepurchase.index'
        ));
    }

    public function test_non_developer_admins_with_explicit_role_permissions_can_see_role_management_in_sidebar(): void
    {
        $role = Role::query()->create([
            'name' => 'admin-role',
            'guard_name' => 'admin',
        ]);

        $roleManagementPermission = Permission::query()->firstOrCreate([
            'name' => 'role-management',
            'guard_name' => 'admin',
        ]);
        $roleListPermission = Permission::query()->firstOrCreate([
            'name' => 'role-list',
            'guard_name' => 'admin',
        ]);

        $role->givePermissionTo($roleManagementPermission, $roleListPermission);

        $admin = Admin::query()->create([
            'first_name' => 'Basic',
            'last_name' => 'Admin',
            'email' => 'basic@example.com',
            'phone' => '01700000001',
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'Active',
        ]);

        $admin->assignRole($role);

        $roleManagementMenu = Menu::query()->create([
            'name' => 'Role Management',
            'icon' => 'role-management',
            'route' => null,
            'module_slug' => null,
            'description' => null,
            'sorting' => 1,
            'parent_id' => null,
            'permission_name' => 'role-management',
            'status' => 'Active',
        ]);

        Menu::query()->create([
            'name' => 'Role List',
            'icon' => 'list',
            'route' => 'backend.role.index',
            'module_slug' => null,
            'description' => null,
            'sorting' => 1,
            'parent_id' => $roleManagementMenu->id,
            'permission_name' => 'role-list',
            'status' => 'Active',
        ]);

        $menus = getSideMenus($admin);

        $this->assertTrue($menus->contains(function ($menu) {
            return trim((string) ($menu['name'] ?? $menu->name ?? '')) === 'Role Management';
        }));
    }

    public function test_pharmacy_and_prescription_menus_are_hidden_until_those_modules_are_assigned(): void
    {
        $role = Role::query()->create([
            'name' => 'clinic-admin',
            'guard_name' => 'admin',
        ]);

        $pharmacyPermission = Permission::query()->firstOrCreate([
            'name' => 'pharmacy-bill-list',
            'guard_name' => 'admin',
        ]);
        $prescriptionPermission = Permission::query()->firstOrCreate([
            'name' => 'prescription-list',
            'guard_name' => 'admin',
        ]);
        $billingPermission = Permission::query()->firstOrCreate([
            'name' => 'billing',
            'guard_name' => 'admin',
        ]);
        $role->givePermissionTo($pharmacyPermission, $prescriptionPermission, $billingPermission);

        $admin = Admin::query()->create([
            'first_name' => 'Clinic',
            'last_name' => 'Admin',
            'email' => 'clinic@example.com',
            'phone' => '01700000002',
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'Active',
        ]);
        $admin->assignRole($role);

        Menu::query()->create([
            'name' => 'Billing',
            'icon' => 'billing',
            'route' => 'backend.dashboard',
            'module_slug' => 'billing',
            'sorting' => 1,
            'parent_id' => null,
            'permission_name' => 'billing',
            'status' => 'Active',
        ]);
        Menu::query()->create([
            'name' => 'Pharmacy',
            'icon' => 'pharmacy',
            'route' => 'backend.dashboard',
            'module_slug' => 'pharmacy',
            'sorting' => 2,
            'parent_id' => null,
            'permission_name' => 'pharmacy-bill-list',
            'status' => 'Active',
        ]);
        Menu::query()->create([
            'name' => 'Prescription',
            'icon' => 'file-text',
            'route' => 'backend.dashboard',
            'module_slug' => 'prescription',
            'sorting' => 3,
            'parent_id' => null,
            'permission_name' => 'prescription-list',
            'status' => 'Active',
        ]);

        $coreModule = \App\Models\Module::query()->create([
            'name' => 'Total Management',
            'slug' => 'billing',
            'description' => 'Core hospital management',
        ]);
        $admin->modules()->sync([$coreModule->id]);

        $menus = getSideMenus($admin->fresh());
        $names = collect($menus)->map(fn ($menu) => trim((string) ($menu['name'] ?? '')));

        $this->assertTrue($names->contains('Billing'));
        $this->assertFalse($names->contains('Pharmacy'));
        $this->assertFalse($names->contains('Prescription'));

        $pharmacyModule = \App\Models\Module::query()->create([
            'name' => 'Pharmacy',
            'slug' => 'pharmacy',
        ]);
        $prescriptionModule = \App\Models\Module::query()->create([
            'name' => 'Prescription',
            'slug' => 'prescription',
        ]);
        $admin->modules()->sync([$coreModule->id, $pharmacyModule->id, $prescriptionModule->id]);

        $menus = getSideMenus($admin->fresh());
        $names = collect($menus)->map(fn ($menu) => trim((string) ($menu['name'] ?? '')));

        $this->assertTrue($names->contains('Billing'));
        $this->assertTrue($names->contains('Pharmacy'));
        $this->assertTrue($names->contains('Prescription'));
    }
}
