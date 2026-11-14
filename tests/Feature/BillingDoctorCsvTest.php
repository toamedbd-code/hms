<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BillingDoctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingDoctorCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_download_sample_and_all_doctors_and_import_csv(): void
    {
        $developer = $this->createAdminWithRole('developer', 'csv-developer@example.test');
        $this->actingAs($developer, 'admin');

        $this->get(route('backend.billingdoctor.sample-csv'))
            ->assertOk()
            ->assertDownload('billing-doctors-sample.csv');

        BillingDoctor::query()->create(['name' => 'Dr. Existing', 'status' => 'Active']);
        $this->get(route('backend.billingdoctor.export-csv'))
            ->assertOk()
            ->assertDownload();

        $response = $this->post(route('backend.billingdoctor.import-csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent(
                'billing-doctors.csv',
                "Name,Status\nDr. New,Active\ndr. existing,Inactive\n"
            ),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Imported 1 billing doctors; skipped 1 duplicates.');
        $this->assertDatabaseHas('billing_doctors', ['name' => 'Dr. New', 'status' => 'Active']);
    }

    public function test_non_developer_cannot_use_billing_doctor_csv_endpoints(): void
    {
        $admin = $this->createAdminWithRole('billing-admin', 'csv-admin@example.test');
        $this->actingAs($admin, 'admin');

        $this->get(route('backend.billingdoctor.sample-csv'))->assertForbidden();
        $this->get(route('backend.billingdoctor.export-csv'))->assertForbidden();
        $this->post(route('backend.billingdoctor.import-csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('billing-doctors.csv', "Name\nDr. Test\n"),
        ])->assertForbidden();
    }

    private function createAdminWithRole(string $roleName, string $email): Admin
    {
        $role = Role::query()->create([
            'name' => $roleName,
            'guard_name' => 'admin',
        ]);

        return Admin::query()->create([
            'first_name' => 'CSV',
            'last_name' => 'Test',
            'email' => $email,
            'phone' => '01712345678',
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'Active',
        ]);
    }
}
