<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DeveloperPermissionBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_pass_permission_checks_without_explicit_assignment(): void
    {
        $permission = Permission::query()->create([
            'name' => 'report-settings',
            'guard_name' => 'admin',
        ]);
        $developerRole = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
        ]);
        $developer = $this->createAdmin('developer@example.test', $developerRole);

        $regularRole = Role::query()->create([
            'name' => 'Report Viewer',
            'guard_name' => 'admin',
        ]);
        $regularUser = $this->createAdmin('viewer@example.test', $regularRole);
        $regularRole->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue(Gate::forUser($developer)->check('report-settings'));
        $this->assertTrue($developer->canAny(['report-settings', 'itemcharge-list']));
        $this->assertTrue(Gate::forUser($regularUser)->check('report-settings'));
        $this->assertFalse(Gate::forUser($regularUser)->check('itemcharge-list'));
    }

    private function createAdmin(string $email, Role $role): Admin
    {
        $admin = Admin::query()->create([
            'first_name' => 'Permission',
            'last_name' => 'Test',
            'email' => $email,
            'phone' => '01700000001',
            'password' => bcrypt('password'),
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        return $admin;
    }
}
