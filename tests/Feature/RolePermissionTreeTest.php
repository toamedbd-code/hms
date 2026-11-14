<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Department;
use App\Models\Designation;
use App\Models\MainCategory;
use App\Models\Permission;
use App\Models\TestCategory;
use Database\Seeders\DashboardCategoryPermissionSeeder;
use Database\Seeders\PharmacyPrescriptionModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_edit_lists_doctor_visit_and_a_separate_prescription_permission_group(): void
    {
        $categoryNames = [
            'Ambulance',
            'Appointment',
            'Disposable',
            'IPD',
            'OPD',
            'Pathology',
            'Radiology',
            'Ultrasonogram',
        ];
        foreach ($categoryNames as $name) {
            TestCategory::query()->create([
                'name' => $name,
                'status' => 'Active',
            ]);
        }
        MainCategory::query()->create([
            'name' => 'ECG',
            'status' => 'Active',
        ]);

        Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ], [
            'sorting' => 1,
        ]);

        $developerRole = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
            'is_private' => true,
        ]);
        $targetRole = Role::query()->create([
            'name' => 'permission-tree-target',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);

        $admin = Admin::query()->create([
            'first_name' => 'Permission',
            'last_name' => 'Editor',
            'email' => 'permission-tree-editor@example.test',
            'phone' => '01710000013',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $developerRole->id,
        ]);
        $admin->assignRole($developerRole);

        $this->seed(DashboardCategoryPermissionSeeder::class);
        $this->seed(PharmacyPrescriptionModuleSeeder::class);
        $this->withoutMiddleware();
        $this->actingAs($admin, 'admin');

        $this->get(route('backend.role.edit', $targetRole->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManageAllPermissions', true)
                ->where('isDeveloper', true)
                ->where('dashboardCategoryPermissionNames', function ($permissionNames) {
                    $expectedPermissionNames = TestCategory::query()
                        ->whereNull('deleted_at')
                        ->where('status', 'Active')
                        ->pluck('name')
                        ->map(fn ($name) => 'dashboard-card-' . trim(
                            (string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim((string) $name))),
                            '-'
                        ) . '-income')
                        ->unique()
                        ->sort()
                        ->values();

                    return collect($permissionNames)->sort()->values()->all()
                        === $expectedPermissionNames->all();
                })
                ->where('permissions', function ($permissions) {
                    $groups = collect($permissions);
                    $dashboard = $groups->firstWhere('name', 'dashboard');
                    $prescription = $groups->firstWhere('name', 'prescription-management');

                    $dashboardChildren = collect($dashboard['child'] ?? [])
                        ->pluck('name');
                    $prescriptionChildren = collect($prescription['child'] ?? [])
                        ->pluck('name');

                    return $dashboardChildren->contains('dashboard-card-doctor-visit-appointment')
                        && $dashboardChildren->contains('dashboard-card-ultrasonogram-income')
                        && $dashboardChildren->contains('dashboard-main-category-ecg-income')
                        && $dashboardChildren->contains('dashboard-chart-income-by-department')
                        && $dashboardChildren->contains('dashboard-chart-income-distribution')
                        && $dashboardChildren->contains('dashboard-card-total-income')
                        && $dashboardChildren->contains('dashboard-card-total-discount')
                        && $dashboardChildren->contains('dashboard-card-refunds')
                        && $dashboardChildren->contains('dashboard-card-referral-commission')
                        && $dashboardChildren->contains('dashboard-card-product-returns')
                        && $prescriptionChildren->contains('prescription-list')
                        && $prescriptionChildren->contains('prescription-add')
                        && $prescriptionChildren->contains('prescription-settings')
                        && $prescriptionChildren->contains('prescription-edit')
                        && $prescriptionChildren->contains('prescription-delete')
                        && $prescriptionChildren->contains('prescription-print');
                })
            );
    }

    public function test_developer_can_save_unchecked_permissions_on_the_developer_role(): void
    {
        $developerRole = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
            'is_private' => true,
        ]);
        $dashboardPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);
        $opdPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard-card-opd-income',
            'guard_name' => 'admin',
        ], [
            'parent_id' => $dashboardPermission->id,
        ]);
        $netIncomePermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard-card-net-income',
            'guard_name' => 'admin',
        ], [
            'parent_id' => $dashboardPermission->id,
        ]);
        $developerRole->givePermissionTo([
            $dashboardPermission->name,
            $opdPermission->name,
            $netIncomePermission->name,
        ]);

        $admin = Admin::query()->create([
            'first_name' => 'Developer',
            'last_name' => 'Editor',
            'email' => 'developer-permission-editor@example.test',
            'phone' => '01710000024',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $developerRole->id,
        ]);
        $admin->assignRole($developerRole);
        $this->withoutMiddleware()->actingAs($admin, 'admin');

        $this->get(route('backend.role.edit', $developerRole->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManageAllPermissions', true)
                ->where('role.permission_ids', function ($permissionIds) use ($netIncomePermission) {
                    return collect($permissionIds)->contains($netIncomePermission->id);
                })
            );

        $this->put(route('backend.role.update', $developerRole->id), [
            'name' => 'developer',
            'guard_name' => 'admin',
            'permission_ids' => [$dashboardPermission->id, $opdPermission->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $developerRole->id,
            'permission_id' => $opdPermission->id,
        ]);
        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id' => $developerRole->id,
            'permission_id' => $netIncomePermission->id,
        ]);
        $this->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardCardPermissions.opdIncome', true)
                ->where('dashboardCardPermissions.netIncome', false)
            );
    }

    public function test_developer_role_edit_keeps_unassigned_dashboard_permissions_visible(): void
    {
        $developerRole = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
            'is_private' => true,
        ]);
        $dashboardPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);
        $netIncomePermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard-card-net-income',
            'guard_name' => 'admin',
        ], [
            'parent_id' => $dashboardPermission->id,
        ]);
        $discountPermission = Permission::query()->firstOrCreate([
            'name' => 'dashboard-card-total-discount',
            'guard_name' => 'admin',
        ], [
            'parent_id' => $dashboardPermission->id,
        ]);
        $developerRole->givePermissionTo($dashboardPermission->name, $netIncomePermission->name);

        $admin = Admin::query()->create([
            'first_name' => 'Developer',
            'last_name' => 'Editor',
            'email' => 'developer-unassigned-permission@example.test',
            'phone' => '01710000025',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $developerRole->id,
        ]);
        $admin->assignRole($developerRole);
        $this->withoutMiddleware()->actingAs($admin, 'admin');

        $this->get(route('backend.role.edit', $developerRole->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManageAllPermissions', true)
                ->where('role.permission_ids', fn ($ids) => collect($ids)->contains($netIncomePermission->id)
                    && !collect($ids)->contains($discountPermission->id))
                ->where('permissions', fn ($groups) => collect($groups)
                    ->pipe(fn ($groups) => collect($groups->firstWhere('name', 'dashboard')['child'] ?? []))
                    ->contains(fn ($permission) => $permission['id'] === $discountPermission->id))
            );
    }

    public function test_role_user_can_reselect_a_permission_after_unchecking_and_saving_it(): void
    {
        $dashboardRoot = SpatiePermission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);
        $roleEditPermission = SpatiePermission::query()->firstOrCreate([
            'name' => 'role-list-edit',
            'guard_name' => 'admin',
        ]);

        $dashboardPermissions = collect(range(1, 54))->map(function ($number) use ($dashboardRoot) {
            $categoryName = sprintf('Chip Test %02d', $number);
            TestCategory::query()->create([
                'name' => $categoryName,
                'status' => 'Active',
            ]);

            return SpatiePermission::query()->create([
                'name' => sprintf('dashboard-card-chip-test-%02d-income', $number),
                'guard_name' => 'admin',
                'parent_id' => $dashboardRoot->id,
            ]);
        });

        $actorRole = Role::query()->create([
            'name' => 'chip-admin-role-editor',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $actorRole->givePermissionTo(array_merge(
            [$dashboardRoot, $roleEditPermission],
            $dashboardPermissions->take(33)->all()
        ));
        $actor = Admin::query()->create([
            'first_name' => 'Chip',
            'last_name' => 'Admin',
            'email' => 'chip-admin-role-editor@example.test',
            'phone' => '01710000118',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $actorRole->id,
        ]);
        $actor->assignRole($actorRole);

        $this->withoutMiddleware()->actingAs($actor, 'admin');

        $this->get(route('backend.role.edit', $actorRole->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('permissions', function ($groups) {
                    $dashboard = collect($groups)->firstWhere('name', 'dashboard');
                    $visibleDashboardPermissionNames = collect($dashboard['child'] ?? [])
                        ->pluck('name')
                        ->filter(fn ($name) => str_starts_with($name, 'dashboard-card-chip-test-'))
                        ->values();

                    return $visibleDashboardPermissionNames->count() === 33
                        && !$visibleDashboardPermissionNames->contains('dashboard-card-chip-test-34-income')
                        && !$visibleDashboardPermissionNames->contains('dashboard-card-chip-test-54-income');
                })
            );

        $permissionToToggle = $dashboardPermissions[32];
        $uncheckedPermissionIds = array_values(array_diff(
            $actorRole->permissions()->pluck('permissions.id')->map(fn ($id) => (int) $id)->all(),
            [$permissionToToggle->id]
        ));

        $this->put(route('backend.role.update', $actorRole->id), [
            'name' => $actorRole->name,
            'guard_name' => 'admin',
            'permission_ids' => $uncheckedPermissionIds,
        ])->assertRedirect();

        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id' => $actorRole->id,
            'permission_id' => $permissionToToggle->id,
        ]);
        $this->actingAs($actor->fresh(), 'admin')
            ->get(route('backend.role.edit', $actorRole->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('role.permission_ids', fn ($ids) => !collect($ids)->contains($permissionToToggle->id))
                ->where('permissions', function ($groups) use ($permissionToToggle) {
                    $dashboard = collect($groups)->firstWhere('name', 'dashboard');
                    $visiblePermissionIds = collect($dashboard['child'] ?? [])->pluck('id');
                    return $visiblePermissionIds->contains($permissionToToggle->id);
                })
            );

        $reselectedPermissionIds = array_merge($uncheckedPermissionIds, [$permissionToToggle->id]);
        $this->put(route('backend.role.update', $actorRole->id), [
            'name' => $actorRole->name,
            'guard_name' => 'admin',
            'permission_ids' => $reselectedPermissionIds,
        ])->assertRedirect();

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $actorRole->id,
            'permission_id' => $permissionToToggle->id,
        ]);
    }

    public function test_prescription_manager_can_assign_prescription_child_permissions(): void
    {
        $this->seed(PharmacyPrescriptionModuleSeeder::class);

        $roleEditorRole = Role::query()->create([
            'name' => 'prescription-role-editor',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $roleEditorPermission = SpatiePermission::query()->create([
            'name' => 'role-list-edit',
            'guard_name' => 'admin',
        ]);
        $prescriptionRoot = SpatiePermission::query()
            ->where('name', 'prescription-management')
            ->where('guard_name', 'admin')
            ->firstOrFail();
        $roleEditorRole->givePermissionTo([$roleEditorPermission, $prescriptionRoot]);

        $admin = Admin::query()->create([
            'first_name' => 'Prescription',
            'last_name' => 'Editor',
            'email' => 'prescription-role-editor@example.test',
            'phone' => '01710000014',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $roleEditorRole->id,
        ]);
        $admin->assignRole($roleEditorRole);

        $targetRole = Role::query()->create([
            'name' => 'prescription-permission-target',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);

        $this->withoutMiddleware();
        $this->actingAs($admin->fresh(), 'admin');

        $this->get(route('backend.role.edit', $targetRole->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isDeveloper', false)
                ->where('permissions', function ($permissions) {
                    $group = collect($permissions)
                        ->firstWhere('name', 'prescription-management');
                    $children = collect($group['child'] ?? [])->pluck('name');

                    return $children->contains('prescription-list')
                        && $children->contains('prescription-add')
                        && $children->contains('prescription-edit')
                        && $children->contains('prescription-delete')
                        && $children->contains('prescription-print');
                })
            );
    }

    public function test_role_permission_updates_apply_to_all_assigned_users_without_removing_other_roles(): void
    {
        $developerRole = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
            'is_private' => true,
        ]);
        $targetRole = Role::query()->create([
            'name' => 'chief-admin',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $additionalRole = Role::query()->create([
            'name' => 'additional-role',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $dashboardPermission = SpatiePermission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ]);
        $opdPermission = SpatiePermission::query()->firstOrCreate([
            'name' => 'dashboard-card-opd-income',
            'guard_name' => 'admin',
        ], [
            'parent_id' => $dashboardPermission->id,
        ]);
        $netIncomePermission = SpatiePermission::query()->firstOrCreate([
            'name' => 'dashboard-card-net-income',
            'guard_name' => 'admin',
        ], [
            'parent_id' => $dashboardPermission->id,
        ]);

        $developer = Admin::query()->create([
            'first_name' => 'Role',
            'last_name' => 'Developer',
            'email' => 'role-update-developer@example.test',
            'phone' => '01710000115',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $developerRole->id,
        ]);
        $developer->assignRole($developerRole);

        $assignedUser = Admin::query()->create([
            'first_name' => 'Chief',
            'last_name' => 'Admin',
            'email' => 'role-update-inactive-user@example.test',
            'phone' => '01710000116',
            'password' => 'password',
            'status' => 'Inactive',
            'role_id' => $targetRole->id,
        ]);
        $assignedUser->assignRole([$targetRole, $additionalRole]);

        $this->withoutMiddleware()->actingAs($developer, 'admin');

        $this->put(route('backend.role.update', $targetRole->id), [
            'name' => $targetRole->name,
            'guard_name' => 'admin',
            'permission_ids' => [$dashboardPermission->id, $opdPermission->id],
        ])->assertRedirect();

        $assignedUser = $assignedUser->fresh();
        $this->assertTrue($assignedUser->hasRole('chief-admin'));
        $this->assertTrue($assignedUser->hasRole('additional-role'));
        $this->assertSame('Inactive', $assignedUser->status);

        $this->actingAs($assignedUser, 'admin')
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardCardPermissions.opdIncome', true)
                ->where('dashboardCardPermissions.netIncome', false)
                ->where('dashboardCardPermissions.pathologyIncome', false)
            );
    }

    public function test_new_admin_sees_only_the_dashboard_cards_assigned_to_the_selected_role(): void
    {
        $role = Role::query()->create([
            'name' => 'dashboard-card-limited-role',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $role->givePermissionTo(SpatiePermission::query()->firstOrCreate([
            'name' => 'dashboard-card-opd-income',
            'guard_name' => 'admin',
        ]));

        $department = Department::query()->create([
            'name' => 'General',
            'status' => 'Active',
        ]);
        $designation = Designation::query()->create([
            'name' => 'Staff',
            'status' => 'Active',
        ]);

        $nullableDetails = [
            'father_name' => null,
            'mother_name' => null,
            'date_of_birth' => null,
            'emergency_contact' => null,
            'specialist_id' => null,
            'current_address' => null,
            'permanent_address' => null,
            'pan_number' => null,
            'national_id_number' => null,
            'local_id_number' => null,
            'qualification' => null,
            'work_experience' => null,
            'specialization' => null,
            'note' => null,
            'epf_no' => null,
            'basic_salary' => null,
            'contract_type' => null,
            'work_shift' => null,
            'work_location' => null,
            'number_of_leaves' => null,
            'bank_account_title' => null,
            'bank_account_no' => null,
            'bank_name' => null,
            'bank_branch_name' => null,
            'ifsc_code' => null,
            'facebook_url' => null,
            'linkedin_url' => null,
            'twitter_url' => null,
            'instagram_url' => null,
        ];

        $this->withoutMiddleware()
            ->post(route('backend.admin.store'), array_merge($nullableDetails, [
                'first_name' => 'Dashboard',
                'last_name' => 'User',
                'email' => 'dashboard-role-user@example.test',
                'phone' => '01710000117',
                'role_id' => $role->id,
                'password' => 'password',
                'staff_id' => 'DASH-ROLE-USER-001',
                'gender' => 'Other',
                'marital_status' => 'Single',
                'blood_group' => 'O+',
                'date_of_joining' => now()->toDateString(),
                'designation_id' => $designation->id,
                'department_id' => $department->id,
            ]))
            ->assertRedirect();

        $createdAdmin = Admin::query()
            ->where('email', 'dashboard-role-user@example.test')
            ->firstOrFail();
        $this->assertTrue($createdAdmin->hasRole($role));
        $this->assertSame($role->id, (int) $createdAdmin->role_id);

        $this->actingAs($createdAdmin, 'admin')
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardCardPermissions.opdIncome', true)
                ->where('dashboardCardPermissions.netIncome', false)
                ->where('dashboardCardPermissions.pathologyIncome', false)
                ->where('dashboardCardPermissions.radiologyIncome', false)
            );
    }

    public function test_dashboard_manager_can_assign_doctor_wise_dashboard_permission(): void
    {
        TestCategory::query()->create([
            'name' => 'ECG',
            'status' => 'Active',
        ]);
        MainCategory::query()->create([
            'name' => 'ECG',
            'status' => 'Active',
        ]);

        Permission::query()->firstOrCreate([
            'name' => 'dashboard',
            'guard_name' => 'admin',
        ], [
            'sorting' => 1,
        ]);
        $this->seed(DashboardCategoryPermissionSeeder::class);

        $roleEditorRole = Role::query()->create([
            'name' => 'dashboard-role-editor',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $roleEditorPermission = SpatiePermission::query()->create([
            'name' => 'role-list-edit',
            'guard_name' => 'admin',
        ]);
        $dashboardRoot = SpatiePermission::query()
            ->where('name', 'dashboard')
            ->where('guard_name', 'admin')
            ->firstOrFail();
        $categoryPermission = SpatiePermission::query()
            ->where('name', 'dashboard-card-ecg-income')
            ->where('guard_name', 'admin')
            ->firstOrFail();
        $mainCategoryPermission = SpatiePermission::query()
            ->where('name', 'dashboard-main-category-ecg-income')
            ->where('guard_name', 'admin')
            ->firstOrFail();
        $roleEditorRole->givePermissionTo([
            $roleEditorPermission,
            $dashboardRoot,
            $categoryPermission,
            $mainCategoryPermission,
        ]);

        $admin = Admin::query()->create([
            'first_name' => 'Dashboard',
            'last_name' => 'Editor',
            'email' => 'dashboard-role-editor@example.test',
            'phone' => '01710000015',
            'password' => 'password',
            'status' => 'Active',
            'role_id' => $roleEditorRole->id,
        ]);
        $admin->assignRole($roleEditorRole);

        $targetRole = Role::query()->create([
            'name' => 'dashboard-permission-target',
            'guard_name' => 'admin',
            'is_private' => false,
        ]);
        $targetRole->givePermissionTo([
            $dashboardRoot,
            $categoryPermission,
            $mainCategoryPermission,
        ]);

        $this->withoutMiddleware();
        $this->actingAs($admin->fresh(), 'admin');

        $this->get(route('backend.role.edit', $targetRole->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isDeveloper', false)
                ->where('role.permission_ids', function ($permissionIds) use ($dashboardRoot, $categoryPermission, $mainCategoryPermission) {
                    $permissionIds = collect($permissionIds)->map(fn ($id) => (int) $id);

                    return $permissionIds->contains($dashboardRoot->id)
                        && $permissionIds->contains($categoryPermission->id)
                        && $permissionIds->contains($mainCategoryPermission->id);
                })
                ->where('permissions', function ($permissions) {
                    $group = collect($permissions)->firstWhere('name', 'dashboard');
                    $children = collect($group['child'] ?? [])->pluck('name');

                    return !$children->contains('dashboard-card-doctor-visit-appointment')
                        && $children->contains('dashboard-card-ecg-income')
                        && $children->contains('dashboard-main-category-ecg-income')
                        && !$children->contains('dashboard-chart-income-by-department')
                        && !$children->contains('dashboard-chart-income-distribution')
                        && !$children->contains('dashboard-card-total-income')
                        && !$children->contains('dashboard-card-product-returns');
                })
            );
    }
}
