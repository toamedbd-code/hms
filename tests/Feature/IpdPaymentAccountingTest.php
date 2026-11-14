<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bed;
use App\Models\BedGroup;
use App\Models\BedType;
use App\Models\BillItem;
use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\Floor;
use App\Models\IpdPatient;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PharmacyBill;
use App\Services\IpdDischargeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IpdPaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmission(): array
    {
        $this->withoutMiddleware();

        $patient = Patient::create(['name' => 'Pay Patient', 'phone' => '0177000000', 'gender' => 'Male']);

        $doctor = Admin::create([
            'first_name' => 'Doc',
            'last_name' => 'Pay',
            'email' => 'doctor.pay@example.test',
            'phone' => '01900000011',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);

        $this->actingAs($doctor, 'admin');

        $floor = Floor::create(['name' => 'Floor P', 'description' => '', 'status' => 'Active']);
        $bedGroup = BedGroup::create(['name' => 'Group P', 'floor_id' => $floor->id, 'status' => 'Active']);
        $bedType = BedType::create([
            'name' => 'Type P',
            'room_rent_rate_per_day' => 0,
            'bed_charge_rate_per_day' => 0,
            'status' => 'Active',
        ]);
        $bed = Bed::create([
            'name' => 'Bed P',
            'bed_type_id' => $bedType->id,
            'bed_group_id' => $bedGroup->id,
            'status' => 'Active',
        ]);

        $ipd = IpdPatient::create([
            'patient_id' => $patient->id,
            'consultant_doctor_id' => $doctor->id,
            'admission_date' => now()->subDay(),
            'bed_group_id' => $bedGroup->id,
            'bed_id' => $bed->id,
            'status' => 'Active',
        ]);

        $billing = Billing::create([
            'patient_id' => $patient->id,
            'patient_mobile' => '0177000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 1000,
            'payable_amount' => 1000,
            'paid_amt' => 0,
            'receiving_amt' => 0,
            'due_amount' => 1000,
            'created_by' => $doctor->id,
            'payment_status' => 'Pending',
            'status' => 'Active',
        ]);

        BillItem::create([
            'billing_id' => $billing->id,
            'item_id' => 0,
            'item_name' => 'IPD Service',
            'category' => 'IPD',
            'unit_price' => 1000,
            'quantity' => 1,
            'total_amount' => 1000,
            'discount' => 0,
            'net_amount' => 1000,
            'status' => 'Active',
        ]);

        $ipd->billing_id = $billing->id;
        $ipd->save();

        return [$ipd, $billing];
    }

    public function test_ipd_payment_is_counted_only_once_in_running_summary(): void
    {
        [$ipd] = $this->makeAdmission();

        $response = $this->postJson(route('backend.ipdpatient.payments.store', $ipd->id), [
            'amount' => 400,
            'payment_method' => 'Cash',
            'notes' => 'Part payment',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $summary = (new IpdDischargeBillingService())->getRunningSummary($ipd->fresh());

        $this->assertSame(400.0, round((float) $summary['paid'], 2));
    }

    public function test_ipd_payment_can_be_updated(): void
    {
        [$ipd] = $this->makeAdmission();

        $payment = Payment::create([
            'ipd_patient_id' => $ipd->id,
            'billing_id' => $ipd->billing_id,
            'amount' => 200,
            'payment_method' => 'Cash',
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);

        $response = $this->postJson(
            route('backend.ipdpatient.payments.update', ['id' => $ipd->id, 'paymentId' => $payment->id]),
            ['amount' => 350, 'payment_method' => 'bKash']
        );

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertSame(350.0, round((float) $payment->fresh()->amount, 2));
    }

    public function test_ipd_pharmacy_invoices_show_and_collect_the_combined_medicine_due(): void
    {
        [$ipd, $billing] = $this->makeAdmission();
        $this->get(route('backend.ipdpatient.prescription', $ipd->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backend/IpdPatient/Prescription')
            );

        $this->get(route('backend.ipdpatient.running-bill.print', $ipd->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $this->get(route('backend.print.ipd.invoice', ['id' => $ipd->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $adminId = (int) auth('admin')->id();
        $submissionToken = (string) Str::uuid();

        $firstBill = PharmacyBill::query()->create([
            'pharmacy_no' => 'IPD-PH-TEST-001',
            'bill_no' => 'IPD-BILL-TEST-001',
            'case_id' => 'IPD-' . $ipd->id,
            'date' => now()->toDateString(),
            'patient_id' => $ipd->patient_id,
            'ipd_patient_id' => $ipd->id,
            'billing_id' => $billing->id,
            'products' => json_encode([]),
            'subtotal' => 50,
            'net_amount' => 50,
            'payment_mode' => 'Cash',
            'payment_amount' => 20,
            'return_amount' => 0,
            'created_by' => $adminId,
        ]);
        $secondBill = PharmacyBill::query()->create([
            'pharmacy_no' => 'IPD-PH-TEST-002',
            'bill_no' => 'IPD-BILL-TEST-002',
            'case_id' => 'IPD-' . $ipd->id,
            'date' => now()->toDateString(),
            'patient_id' => $ipd->patient_id,
            'ipd_patient_id' => $ipd->id,
            'billing_id' => $billing->id,
            'products' => json_encode([[
                'medicine_name' => 'Test medicine',
                'unit_price' => 10,
                'quantity' => 3,
                'total_amount' => 30,
                'net_amount' => 30,
            ]]),
            'subtotal' => 30,
            'net_amount' => 30,
            'payment_mode' => 'Cash',
            'payment_amount' => 0,
            'return_amount' => 0,
            'created_by' => $adminId,
        ]);

        $this->get(route('backend.ipdpatient.prescription', $ipd->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backend/IpdPatient/Prescription')
                ->where('pharmacyDueSummary.invoice_count', 2)
                ->where('pharmacyDueSummary.due_amount', 60)
            );

        $this->get(route('backend.pharmacybill.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('datas.data.0.bill_no', $secondBill->bill_no)
            );

        Payment::query()->create([
            'ipd_patient_id' => $ipd->id,
            'billing_id' => $billing->id,
            'pharmacy_bill_id' => $firstBill->id,
            'amount' => 20,
            'payment_method' => 'Cash',
            'payment_status' => 'Partial',
            'status' => 'Active',
        ]);
        $billing->update([
            'paid_amt' => 20,
            'due_amount' => 980,
            'payment_status' => 'Partial',
        ]);

        $invoiceResponse = $this->get(route('backend.pharmacybill.invoice', $secondBill->id))->assertOk();
        $this->assertStringContainsString('PHARMACY INVOICE', $invoiceResponse->getContent());
        $this->assertStringContainsString('Print Invoice', $invoiceResponse->getContent());
        $this->assertStringContainsString('size: A5 portrait', $invoiceResponse->getContent());
        $this->assertStringContainsString('Previous Medicine Due', $invoiceResponse->getContent());
        $this->assertStringContainsString('Test medicine', $invoiceResponse->getContent());
        $this->assertStringContainsString('<td class="rate">10.00</td>', $invoiceResponse->getContent());
        $this->assertStringContainsString('Tk 30.00', $invoiceResponse->getContent());
        $this->assertStringContainsString('Tk 60.00', $invoiceResponse->getContent());

        $this->post(route('backend.ipdpatient.pharmacy-due-collect', $ipd->id), [
            'amount' => 61,
            'payment_method' => 'Cash',
            'submission_token' => $submissionToken,
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('due_collections', 0);

        $response = $this->post(route('backend.ipdpatient.pharmacy-due-collect', $ipd->id), [
            'amount' => 60,
            'payment_method' => 'Cash',
            'submission_token' => $submissionToken,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('due_collections', [
            'billing_id' => $billing->id,
            'pharmacy_bill_id' => $firstBill->id,
            'collected_amount' => 30,
        ]);
        $this->assertDatabaseHas('due_collections', [
            'billing_id' => $billing->id,
            'pharmacy_bill_id' => $secondBill->id,
            'collected_amount' => 30,
        ]);
        $this->assertSame(80.0, (float) $billing->fresh()->paid_amt);
        $this->assertSame(920.0, (float) $billing->fresh()->due_amount);

        $this->post(route('backend.ipdpatient.pharmacy-due-collect', $ipd->id), [
            'amount' => 1,
            'payment_method' => 'Cash',
            'submission_token' => $submissionToken,
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('due_collections', 2);

        $csvResponse = $this->get(route('backend.pharmacybill.export', ['format' => 'csv']))->assertOk();
        $this->assertStringContainsString('Bed P', $csvResponse->streamedContent());
    }

    public function test_updating_admission_does_not_reset_created_at(): void
    {
        [$ipd] = $this->makeAdmission();

        $admittedAt = '2026-01-05 09:15:00';
        IpdPatient::withoutEvents(function () use ($ipd, $admittedAt) {
            IpdPatient::query()->whereKey($ipd->id)->update(['created_at' => $admittedAt]);
        });

        $ipd = $ipd->fresh();
        $ipd->note = 'updated note';
        $ipd->save();

        $this->assertSame($admittedAt, $ipd->fresh()->created_at->toDateTimeString());
    }
}
