<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Billing;
use App\Models\PharmacyBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingListModuleSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_and_pharmacy_lists_are_separate_while_ipd_bills_remain_in_the_ipd_list(): void
    {
        $this->withoutMiddleware();
        $admin = Admin::create([
            'first_name' => 'Billing',
            'last_name' => 'List',
            'email' => 'billing-list-separation@example.test',
            'phone' => '01700000000',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $this->actingAs($admin, 'admin');

        $regularBill = $this->createBill($admin, 'CASE-REGULAR-001');
        $pharmacyBill = $this->createBill($admin, 'CASE-PHARMACY-001');
        $ipdBill = $this->createBill($admin, 'IPD-001');

        PharmacyBill::query()->create([
            'pharmacy_no' => 'PH-000001',
            'bill_no' => $pharmacyBill->bill_number,
            'billing_id' => $pharmacyBill->id,
            'date' => now()->toDateString(),
            'products' => json_encode([]),
            'created_by' => $admin->id,
        ]);

        $this->get(route('backend.billing.list'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('datas.data', 1)
                ->where('datas.data.0.bill_number', $regularBill->bill_number));

        $this->get(route('backend.billing.list', ['ipd' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('datas.data', 1)
                ->where('datas.data.0.bill_number', $ipdBill->bill_number));
    }

    private function createBill(Admin $admin, string $caseNumber): Billing
    {
        return Billing::query()->create([
            'bill_number' => 'BILL-' . $caseNumber,
            'case_number' => $caseNumber,
            'patient_mobile' => '01700000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'discount' => 0,
            'discount_type' => 'percentage',
            'payable_amount' => 100,
            'paid_amt' => 0,
            'invoice_amount' => 0,
            'change_amt' => 0,
            'receiving_amt' => 0,
            'return_amt' => 0,
            'due_amount' => 100,
            'created_by' => $admin->id,
            'payment_status' => 'Pending',
            'status' => 'Active',
        ]);
    }
}
