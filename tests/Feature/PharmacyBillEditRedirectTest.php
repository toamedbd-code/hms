<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Billing;
use App\Models\PharmacyBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyBillEditRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacy_bill_edit_redirects_to_the_shared_billing_page(): void
    {
        $this->withoutMiddleware();

        $admin = Admin::create([
            'first_name' => 'Pharmacy',
            'last_name' => 'Editor',
            'email' => 'pharmacy-editor@example.test',
            'phone' => '01911112222',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $this->actingAs($admin, 'admin');

        $billing = Billing::query()->create([
            'invoice_number' => 'INV-PHARMACY-EDIT-1',
            'bill_number' => 'BILL-PHARMACY-EDIT-1',
            'case_number' => 'CASE-PHARMACY-EDIT-1',
            'patient_mobile' => '',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 50,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 50,
            'paid_amt' => 50,
            'invoice_amount' => 50,
            'receiving_amt' => 50,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);
        $pharmacyBill = PharmacyBill::query()->create([
            'pharmacy_no' => 'PHAB-PHARMACY-EDIT-1',
            'bill_no' => $billing->bill_number,
            'billing_id' => $billing->id,
            'date' => '2026-10-22',
            'products' => json_encode([]),
            'subtotal' => 50,
            'net_amount' => 50,
            'payment_mode' => 'Cash',
            'payment_amount' => 50,
            'created_by' => $admin->id,
            'status' => 'Active',
        ]);

        $this->followingRedirects()
            ->get(route('backend.pharmacybill.edit', $pharmacyBill->id))
            ->assertInertia(fn ($page) => $page
                ->component('Backend/Billing/BillingPage')
                ->where('isPharmacyDepartment', true));
    }
}
