<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\BillingController;
use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
