<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ProductReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductReturnStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_developer_can_approve_and_process_returns(): void
    {
        $listPermission = Permission::query()->create([
            'name' => 'product-return-list',
            'guard_name' => 'admin',
        ]);

        foreach (['Admin', 'developer'] as $roleName) {
            $role = Role::query()->create([
                'name' => $roleName,
                'guard_name' => 'admin',
                'is_private' => $roleName === 'developer',
            ]);
            $role->givePermissionTo($listPermission);

            $admin = Admin::query()->create([
                'first_name' => $roleName,
                'last_name' => 'User',
                'email' => strtolower($roleName) . '@example.test',
                'phone' => '01710000001',
                'password' => 'password',
                'status' => 'Active',
                'role_id' => $role->id,
            ]);
            $admin->assignRole($role);

            $productReturn = ProductReturn::query()->create([
                'return_number' => 'RET-' . strtoupper($roleName),
                'return_type' => 'customer',
                'return_date' => today(),
                'total_amount' => 10,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'status' => 'pending',
            ]);

            $this->actingAs($admin, 'admin')
                ->get(route('backend.productreturn.show', $productReturn))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Backend/ProductReturn/Show')
                    ->where('canManageStatus', true)
                );

            $this->post(route('backend.productreturn.approve', $productReturn))
                ->assertRedirect();
            $this->assertSame('approved', $productReturn->fresh()->status);

            $this->post(route('backend.productreturn.process', $productReturn))
                ->assertRedirect();
            $this->assertSame('processed', $productReturn->fresh()->status);

            $this->post(route('backend.productreturn.reopen', $productReturn))
                ->assertRedirect();
            $this->assertSame('pending', $productReturn->fresh()->status);

            $this->post(route('backend.productreturn.approve', $productReturn))
                ->assertRedirect();
            $this->assertSame('approved', $productReturn->fresh()->status);
        }
    }

    public function test_status_permission_controls_workflow_actions_for_other_roles(): void
    {
        $listPermission = Permission::query()->create([
            'name' => 'product-return-list',
            'guard_name' => 'admin',
        ]);
        $statusPermission = Permission::query()->create([
            'name' => 'product-return-list-status',
            'guard_name' => 'admin',
        ]);
        $role = Role::query()->create([
            'name' => 'return-manager',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $role->givePermissionTo($listPermission, $statusPermission);

        $admin = Admin::query()->create([
            'first_name' => 'Return',
            'last_name' => 'Manager',
            'email' => 'return-manager@example.test',
            'phone' => '01710000003',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        $productReturn = ProductReturn::query()->create([
            'return_number' => 'RET-MANAGER',
            'return_type' => 'customer',
            'return_date' => today(),
            'total_amount' => 10,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'status' => 'processed',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('backend.productreturn.show', $productReturn))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backend/ProductReturn/Show')
                ->where('canManageStatus', true)
            );

        $this->post(route('backend.productreturn.reopen', $productReturn))
            ->assertRedirect();
        $this->assertSame('pending', $productReturn->fresh()->status);
    }

    public function test_user_without_status_permission_cannot_approve_or_reopen_returns(): void
    {
        $listPermission = Permission::query()->create([
            'name' => 'product-return-list',
            'guard_name' => 'admin',
        ]);
        $role = Role::query()->create([
            'name' => 'return-viewer',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $role->givePermissionTo($listPermission);

        $admin = Admin::query()->create([
            'first_name' => 'Return',
            'last_name' => 'Viewer',
            'email' => 'return-viewer@example.test',
            'phone' => '01710000002',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        $productReturn = ProductReturn::query()->create([
            'return_number' => 'RET-VIEWER',
            'return_type' => 'customer',
            'return_date' => today(),
            'total_amount' => 10,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('backend.productreturn.show', $productReturn))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backend/ProductReturn/Show')
                ->where('canManageStatus', false)
            );

        $this->post(route('backend.productreturn.approve', $productReturn))
            ->assertForbidden();
        $this->assertSame('pending', $productReturn->fresh()->status);

        $productReturn->update(['status' => 'processed']);
        $this->post(route('backend.productreturn.reopen', $productReturn))
            ->assertForbidden();
        $this->assertSame('processed', $productReturn->fresh()->status);
    }
}
