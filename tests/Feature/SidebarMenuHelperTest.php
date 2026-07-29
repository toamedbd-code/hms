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

    public function test_non_developer_admins_do_not_see_role_management_in_sidebar(): void
    {
        $developerRole = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
        ]);

        $role = Role::query()->create([
            'name' => 'admin-role',
            'guard_name' => 'admin',
        ]);

        $developerPermission = Permission::query()->firstOrCreate([
            'name' => 'role-management',
            'guard_name' => 'admin',
        ]);
        $rolePermission = Permission::query()->firstOrCreate([
            'name' => 'role-list',
            'guard_name' => 'admin',
        ]);

        $developerRole->givePermissionTo($developerPermission, $rolePermission);
        $role->givePermissionTo($developerPermission, $rolePermission);

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

        Menu::query()->create([
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
            'parent_id' => 1,
            'permission_name' => 'role-list',
            'status' => 'Active',
        ]);

        $menus = getSideMenus($admin);

        $this->assertFalse($menus->contains(function ($menu) {
            return trim((string) ($menu['name'] ?? $menu->name ?? '')) === 'Role Management';
        }));
    }
}
