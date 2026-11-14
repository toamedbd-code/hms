<?php

namespace Tests\Unit;

use App\Models\Billing;
use App\Models\BillItem;
use App\Models\Expense;
use App\Models\Pathology;
use App\Models\PharmacyBill;
use App\Models\Radiology;
use App\Models\Referral;
use App\Services\BillingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingServiceDeleteReferralCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $this->app['db']->purge('sqlite');

        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('case_number')->nullable();
            $table->string('patient_mobile')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('return_amt', 10, 2)->default(0);
            $table->string('payment_status')->default('Pending');
            $table->string('status')->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_id')->nullable();
            $table->unsignedBigInteger('payee_id')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->default('Active');
            $table->decimal('total_commission_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status')->default('Active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pathologies', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no')->nullable();
            $table->string('status')->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('radiologies', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no')->nullable();
            $table->string('status')->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('pharmacybills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no')->nullable();
            $table->string('status')->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function test_delete_billing_removes_related_referral_and_expense_records(): void
    {
        $billing = Billing::create([
            'bill_number' => 'BILL-2025-0001',
            'invoice_number' => 'INV-2025-0001',
            'case_number' => 'CASE-2025-0001',
            'patient_mobile' => '01700000000',
            'total' => 1000,
            'status' => 'Active',
        ]);

        $referral = Referral::create([
            'billing_id' => $billing->id,
            'payee_id' => 1,
            'date' => '2025-01-01',
            'status' => 'Active',
            'total_commission_amount' => 100,
        ]);

        Expense::create([
            'bill_number' => $billing->bill_number,
            'amount' => 100,
            'status' => 'Active',
        ]);

        $service = app(BillingService::class);

        $service->deleteBIllingWithPathoRadioPharm($billing->id);

        $this->assertNotNull(Billing::withTrashed()->find($billing->id)->deleted_at);
        $this->assertNotNull(Referral::withTrashed()->find($referral->id)->deleted_at);
        $this->assertNotNull(Expense::withTrashed()->where('bill_number', $billing->bill_number)->first()->deleted_at);
    }

    public function test_pharmacy_bill_supports_soft_delete_lookup_and_restore(): void
    {
        $pharmacyBill = PharmacyBill::create([
            'bill_no' => 'BILL-2025-0002',
            'status' => 'Active',
        ]);

        $pharmacyBill->delete();

        $deletedBill = PharmacyBill::withTrashed()->findOrFail($pharmacyBill->id);
        $this->assertNotNull($deletedBill->deleted_at);

        $deletedBill->restore();

        $this->assertNotNull(PharmacyBill::find($pharmacyBill->id));
    }
}
