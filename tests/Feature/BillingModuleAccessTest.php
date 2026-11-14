<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureBranchScope;
use App\Http\Middleware\ResolveOrganizationContext;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_add_permission_can_open_billing_module_page(): void
    {
        $role = Role::query()->create([
            'name' => 'billing-add-role',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $role->givePermissionTo(Permission::query()->firstOrCreate([
            'name' => 'billing-add',
            'guard_name' => 'admin',
        ]));

        $admin = Admin::query()->create([
            'first_name' => 'Billing',
            'last_name' => 'User',
            'email' => 'billing-add@example.test',
            'phone' => '01710000030',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $role->id,
        ]);
        $admin->assignRole($role);

        $this->withoutMiddleware([
            ResolveOrganizationContext::class,
            EnsureBranchScope::class,
        ]);
        $this->actingAs($admin, 'admin')
            ->get(route('backend.billing.Page'))
            ->assertInertia(fn ($page) => $page->component('Backend/Billing/Billing'));
    }
}
