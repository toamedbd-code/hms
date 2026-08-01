<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeveloperAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setEnv('SUBSCRIPTION_ENFORCE', 'true');
        $this->setEnv('SINGLE_DEV_EMAIL', 'toamedbd@gmail.com');
        $this->setEnv('SINGLE_DEV_PASSWORD', 'zxczxc');
    }

    public function test_default_developer_can_login_when_subscription_is_inactive(): void
    {
        $this->assertDatabaseCount('admins', 0);

        $response = $this->post(route('backend.auth.login'), [
            'email' => 'toamedbd@gmail.com',
            'password' => 'zxczxc',
        ]);

        $response->assertRedirect(route('backend.dashboard'));
        $this->assertAuthenticated('admin');
        $this->assertDatabaseHas('admins', ['email' => 'toamedbd@gmail.com']);
    }

    public function test_non_developer_cannot_open_developer_edit_page(): void
    {
        $this->withoutMiddleware();

        $developerRole = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
            'is_private' => true,
        ]);

        $adminRole = Role::query()->create([
            'name' => 'Admin',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);

        $nonDeveloper = Admin::query()->create([
            'first_name' => 'Normal',
            'last_name' => 'User',
            'email' => 'normal@example.test',
            'phone' => '01710000001',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $adminRole->id,
        ]);

        $developer = Admin::query()->create([
            'first_name' => 'Dev',
            'last_name' => 'User',
            'email' => 'developer@example.test',
            'phone' => '01710000002',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $developerRole->id,
        ]);

        $this->actingAs($nonDeveloper, 'admin');

        $response = $this->get(route('backend.admin.edit', $developer->id));

        $response->assertStatus(403);
    }

    public function test_user_with_role_list_edit_permission_can_open_role_edit_page(): void
    {
        $role = Role::query()->create([
            'name' => 'role-editor',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);

        $roleListPermission = Permission::query()->firstOrCreate([
            'name' => 'role-list',
            'guard_name' => 'admin',
        ]);
        $roleListEditPermission = Permission::query()->firstOrCreate([
            'name' => 'role-list-edit',
            'guard_name' => 'admin',
        ]);

        $role->givePermissionTo($roleListPermission, $roleListEditPermission);

        $admin = Admin::query()->create([
            'first_name' => 'Role',
            'last_name' => 'Editor',
            'email' => 'role-editor@example.test',
            'phone' => '01710000003',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);

        $admin->assignRole($role);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('backend.role.index'));

        $response->assertOk();
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
