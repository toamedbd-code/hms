<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\BillingController;
use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillingDiscountEditDueAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_payment_records_recomputes_due_from_persisted_payable_amount_after_discount_edit(): void
    {
        $adminId = DB::table('admins')->insertGetId([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'billing-discount-edit@example.com',
            'phone' => '01700000001',
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $billing = Billing::create([
            'patient_mobile' => '01700000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'discount' => 20,
            'discount_type' => 'percentage',
            'payable_amount' => 100,
            'paid_amt' => 70,
            'invoice_amount' => 70,
            'receiving_amt' => 70,
            'return_amt' => 0,
            'due_amount' => 30,
            'created_by' => $adminId,
            'payment_status' => 'Partial',
        ]);

        Payment::create([
            'billing_id' => $billing->id,
            'amount' => 40,
            'payment_method' => 'Cash',
            'received_by' => $adminId,
            'payment_status' => 'Partial',
        ]);

        DueCollection::create([
            'billing_id' => $billing->id,
            'collected_amount' => 30,
            'collected_at' => now(),
            'created_by' => $adminId,
        ]);

        $controller = (new \ReflectionClass(BillingController::class))->newInstanceWithoutConstructor();

        $data = [
            'paid_amt' => 70,
            'payable_amount' => 80,
            'total' => 100,
            'receiving_amt' => 0,
            'return_amt' => 0,
        ];

        $method = new \ReflectionMethod(BillingController::class, 'updatePaymentRecords');
        $method->setAccessible(true);
        $method->invoke($controller, $billing->id, $data);

        $billing->refresh();

        $this->assertSame(80.0, round((float) $billing->fresh()->payable_amount, 2));
        $this->assertSame(10.0, round((float) $billing->fresh()->due_amount, 2));
    }

    public function test_invoice_totals_recover_percentage_discount_when_bill_item_discount_snapshot_is_missing(): void
    {
        $adminId = DB::table('admins')->insertGetId([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'billing-invoice-discount@example.com',
            'phone' => '01700000002',
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $billing = Billing::create([
            'patient_mobile' => '01700000001',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 500,
            'discount' => 30,
            'discount_type' => 'percentage',
            'extra_flat_discount' => 0,
            'payable_amount' => 350,
            'paid_amt' => 350,
            'invoice_amount' => 350,
            'receiving_amt' => 350,
            'return_amt' => 0,
            'due_amount' => 0,
            'created_by' => $adminId,
            'payment_status' => 'Paid',
        ]);

        $controller = (new \ReflectionClass(\App\Http\Controllers\Backend\InvoiceController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(\App\Http\Controllers\Backend\InvoiceController::class, 'calculateFilteredTotals');
        $method->setAccessible(true);

        $totals = $method->invoke($controller, collect([]), $billing, 'billing');

        $this->assertSame(150.0, round((float) ($totals['discount'] ?? 0), 2));
        $this->assertSame(350.0, round((float) ($totals['net_payable'] ?? 0), 2));
    }

    public function test_user_daily_stats_discount_matches_the_stored_payable_amount(): void
    {
        $adminId = DB::table('admins')->insertGetId([
            'first_name' => 'Discount',
            'last_name' => 'Stats',
            'email' => 'billing-discount-stats@example.com',
            'phone' => '01700000003',
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Billing::create([
            'patient_mobile' => '01700000002',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'case_number' => 'OPD-DISCOUNT-STATS',
            'total' => 170061.98,
            'discount' => 52027.00,
            'discount_type' => 'flat',
            'vat_amount' => 0,
            'payable_amount' => 118035.00,
            'paid_amt' => 118035.00,
            'invoice_amount' => 118035.00,
            'receiving_amt' => 118035.00,
            'return_amt' => 0,
            'due_amount' => 0,
            'created_by' => $adminId,
            'payment_status' => 'Paid',
        ]);

        $controller = (new \ReflectionClass(BillingController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(BillingController::class, 'getUserStats');
        $method->setAccessible(true);
        $stats = $method->invoke($controller, Request::create('/billing?user=Discount', 'GET'));

        $this->assertCount(1, $stats);
        $this->assertSame(170061.98, $stats[0]['total_amount']);
        $this->assertSame(52026.98, round($stats[0]['total_discount'], 2));
        $this->assertSame(118035.0, $stats[0]['total_net_income']);
    }

    public function test_invoice_reinterprets_legacy_percentage_discount_amount_as_percentage_when_it_exceeds_100(): void
    {
        $adminId = DB::table('admins')->insertGetId([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'billing-invoice-legacy-percent@example.com',
            'phone' => '01700000003',
            'password' => bcrypt('password'),
            'role_id' => null,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $billing = Billing::create([
            'patient_mobile' => '01700000002',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 2500,
            'discount' => 625,
            'discount_type' => 'percentage',
            'extra_flat_discount' => 0,
            'payable_amount' => 1875,
            'paid_amt' => 1875,
            'invoice_amount' => 1875,
            'receiving_amt' => 1875,
            'return_amt' => 0,
            'due_amount' => 0,
            'created_by' => $adminId,
            'payment_status' => 'Paid',
        ]);

        $controller = (new \ReflectionClass(\App\Http\Controllers\Backend\InvoiceController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(\App\Http\Controllers\Backend\InvoiceController::class, 'calculateFilteredTotals');
        $method->setAccessible(true);

        $billItems = collect([
            (object) ['id' => 1, 'item_name' => 'Test 1', 'category' => 'Pathology', 'total_amount' => 500, 'discount' => 125, 'discount_exempt' => false],
            (object) ['id' => 2, 'item_name' => 'Test 2', 'category' => 'Radiology', 'total_amount' => 600, 'discount' => 150, 'discount_exempt' => false],
            (object) ['id' => 3, 'item_name' => 'Test 3', 'category' => 'Radiology', 'total_amount' => 1400, 'discount' => 350, 'discount_exempt' => false],
        ]);

        $totals = $method->invoke($controller, $billItems, $billing, 'billing');

        $this->assertSame(625.0, round((float) ($totals['discount'] ?? 0), 2));
        $this->assertSame(1875.0, round((float) ($totals['net_payable'] ?? 0), 2));
    }
}
