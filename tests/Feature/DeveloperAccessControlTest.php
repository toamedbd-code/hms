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

    public function test_billing_manager_can_restore_add_bill_permission_for_another_role(): void
    {
        $this->withoutMiddleware();

        $roleEditor = Role::query()->create([
            'name' => 'billing-role-editor',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $roleListEditPermission = Permission::query()->firstOrCreate([
            'name' => 'role-list-edit',
            'guard_name' => 'admin',
        ]);
        $billingPermission = Permission::query()->firstOrCreate([
            'name' => 'billing',
            'guard_name' => 'admin',
        ]);
        $billingAddPermission = Permission::query()->firstOrCreate([
            'name' => 'billing-add',
            'guard_name' => 'admin',
        ], [
            'parent_id' => $billingPermission->id,
        ]);
        $roleEditor->givePermissionTo($roleListEditPermission, $billingPermission);

        $actor = Admin::query()->create([
            'first_name' => 'Billing',
            'last_name' => 'Editor',
            'email' => 'billing-role-editor@example.test',
            'phone' => '01710000004',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $roleEditor->id,
        ]);
        $actor->assignRole($roleEditor);

        $targetRole = Role::query()->create([
            'name' => 'restricted-billing',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);

        $this->actingAs($actor, 'admin');

        $this->get(route('backend.role.edit', $targetRole->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('permissions', function ($permissions) use ($billingAddPermission) {
                    foreach ($permissions as $group) {
                        foreach ($group['child'] ?? [] as $child) {
                            if ((int) $child['id'] === $billingAddPermission->id) {
                                return true;
                            }
                        }
                    }

                    return false;
                })
            );

        $updateResponse = $this->put(route('backend.role.update', $targetRole->id), [
            'name' => $targetRole->name,
            'guard_name' => 'admin',
            'permission_ids' => [$billingPermission->id, $billingAddPermission->id],
        ]);
        $updateResponse->assertSessionHas('successMessage', 'Role updated successfully');

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $targetRole->id,
            'permission_id' => $billingAddPermission->id,
        ]);
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
