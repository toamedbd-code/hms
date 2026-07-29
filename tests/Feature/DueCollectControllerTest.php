<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\IpdPatient;
use App\Models\Payment;
use App\Services\IpdDischargeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DueCollectControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_due_collection_submission_is_rejected_with_same_token(): void
    {
        $adminId = DB::table('admins')->insertGetId([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000000',
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = new \Illuminate\Auth\GenericUser([
            'id' => $adminId,
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
        ]);

        $billing = Billing::create([
            'patient_mobile' => '01700000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'payable_amount' => 100,
            'paid_amt' => 0,
            'receiving_amt' => 0,
            'due_amount' => 100,
            'created_by' => $adminId,
            'payment_status' => 'Pending',
        ]);

        $token = Str::uuid()->toString();
        $sessionKey = 'due_collect.billing.' . $billing->id;

        $this->withoutMiddleware();
        $this->withSession([$sessionKey => $token]);

        $firstResponse = $this->actingAs($admin, 'admin')
            ->post(route('backend.due.collect.store', ['id' => $billing->id]), [
                'amount' => 50,
                'submission_token' => $token,
                'return_to' => '',
            ]);

        $firstResponse->assertRedirect();
        $this->assertDatabaseHas('due_collections', [
            'billing_id' => $billing->id,
            'collected_amount' => 50,
        ]);

        $secondResponse = $this->actingAs($admin, 'admin')
            ->withSession([$sessionKey => $token])
            ->post(route('backend.due.collect.store', ['id' => $billing->id]), [
                'amount' => 50,
                'submission_token' => $token,
                'return_to' => '',
            ]);

        $secondResponse->assertStatus(302);
        $this->assertEquals(1, $billing->fresh()->dueCollections()->count());
    }

    public function test_running_bill_summary_includes_due_collections_for_ipd_patient(): void
    {
        $adminId = DB::table('admins')->insertGetId([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin2@example.com',
            'phone' => '01700000001',
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ipdPatient = new IpdPatient(['id' => 999]);

        $billing = Billing::create([
            'patient_mobile' => '01700000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'payable_amount' => 100,
            'paid_amt' => 0,
            'receiving_amt' => 0,
            'due_amount' => 100,
            'created_by' => $adminId,
            'payment_status' => 'Pending',
        ]);

        Payment::create([
            'ipd_patient_id' => $ipdPatient->id,
            'billing_id' => $billing->id,
            'amount' => 40,
            'payment_method' => 'Cash',
            'status' => 'Active',
        ]);

        DueCollection::create([
            'billing_id' => $billing->id,
            'collected_amount' => 20,
            'collected_at' => now(),
            'created_by' => $adminId,
        ]);

        $service = new IpdDischargeBillingService();
        $summary = $service->getRunningSummary($ipdPatient);

        $this->assertSame(60.0, round((float) $summary['paid'], 2));
        $this->assertSame(0.0, round((float) $summary['due'], 2));
    }

    public function test_due_collection_redirects_to_ipd_show_page_with_certificate_open_flag_when_started_from_gate(): void
    {
        $adminId = DB::table('admins')->insertGetId([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000000',
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = new \Illuminate\Auth\GenericUser([
            'id' => $adminId,
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
        ]);

        $billing = Billing::create([
            'patient_mobile' => '01700000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'payable_amount' => 100,
            'paid_amt' => 0,
            'receiving_amt' => 0,
            'due_amount' => 100,
            'created_by' => $adminId,
            'payment_status' => 'Pending',
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($admin, 'admin')
            ->post(route('backend.due.collect.store', ['id' => $billing->id]), [
                'amount' => 50,
                'submission_token' => (string) Str::uuid(),
                'return_to' => route('backend.ipdpatient.discharge-certificate.print', 3),
                'ipd_patient_id' => 3,
            ]);

        $response->assertRedirect();
        $response->assertRedirectContains('/ipdpatient/3?open_certificate=1');
    }
}
