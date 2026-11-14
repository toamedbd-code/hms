<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCsvTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_export_and_restore_staff_records(): void
    {
        $role = Role::query()->create([
            'name' => 'developer',
            'guard_name' => 'admin',
        ]);
        $adminListPermission = Permission::query()->create([
            'name' => 'admin-list',
            'guard_name' => 'admin',
        ]);
        $role->givePermissionTo($adminListPermission);
        $developer = Admin::query()->create([
            'first_name' => 'Staff',
            'last_name' => 'Backup',
            'email' => 'staff-backup@example.test',
            'phone' => '01700000003',
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'Active',
        ]);
        $developer->syncRoles([$role]);

        $staffRole = Role::query()->create([
            'name' => 'Doctor',
            'guard_name' => 'admin',
        ]);
        $additionalRole = Role::query()->create([
            'name' => 'Billing Supervisor',
            'guard_name' => 'admin',
        ]);
        $staff = Admin::query()->create([
            'first_name' => 'CSV',
            'last_name' => 'Doctor',
            'email' => 'csv-doctor@example.test',
            'phone' => '01700000004',
            'password' => 'doctor-password',
            'role_id' => $staffRole->id,
            'status' => 'Active',
        ]);
        $staff->syncRoles([$staffRole, $additionalRole]);
        AdminDetail::query()->create([
            'admin_id' => $staff->id,
            'staff_id' => 'CSV-STAFF-001',
            'gender' => 'Female',
            'status' => 'Active',
        ]);

        $this->actingAs($developer, 'admin');
        $this->get(route('backend.admin.index'))
            ->assertInertia(fn ($page) => $page->where('isDeveloper', true));
        $export = $this->get(route('backend.admin.export-csv'));
        $export->assertOk();
        $csvContent = $export->streamedContent();
        $this->assertStringContainsString('Staff Details JSON', $csvContent);
        $this->assertStringNotContainsString('remember_token', $csvContent);

        DB::table('admin_details')->where('admin_id', $staff->id)->delete();
        DB::table('admins')->where('id', $staff->id)->delete();

        $this->post(route('backend.admin.import-csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('staff-list.csv', $csvContent),
        ])
            ->assertRedirect(route('backend.admin.index'))
            ->assertSessionHas('successMessage', 'Imported 1 staff; skipped 1 staff already present.');

        $restoredStaff = Admin::query()->where('email', 'csv-doctor@example.test')->firstOrFail();
        $this->assertSame('CSV-STAFF-001', $restoredStaff->details->staff_id);
        $this->assertSame('Doctor', $restoredStaff->role->name);
        $this->assertEqualsCanonicalizing(
            ['Doctor', 'Billing Supervisor'],
            $restoredStaff->roles->pluck('name')->all()
        );
        $this->assertDatabaseHas('admins', [
            'id' => $restoredStaff->id,
            'password' => $staff->getRawOriginal('password'),
        ]);
    }

    public function test_non_developer_cannot_export_or_import_staff_csv(): void
    {
        $role = Role::query()->create([
            'name' => 'staff-csv-user',
            'guard_name' => 'admin',
        ]);
        $role->givePermissionTo(Permission::query()->create([
            'name' => 'admin-list',
            'guard_name' => 'admin',
        ]));
        $admin = Admin::query()->create([
            'first_name' => 'Regular',
            'last_name' => 'Admin',
            'email' => 'regular-staff-csv@example.test',
            'phone' => '01700000005',
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'Active',
        ]);
        $admin->syncRoles([$role]);

        $this->actingAs($admin, 'admin');
        $this->get(route('backend.admin.index'))
            ->assertInertia(fn ($page) => $page->where('isDeveloper', false));
        $this->get(route('backend.admin.export-csv'))->assertForbidden();
        $this->post(route('backend.admin.import-csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('staff-list.csv', "invalid\n"),
        ])->assertForbidden();
    }
}
