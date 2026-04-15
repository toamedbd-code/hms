<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\BillItem;
use App\Models\IpdPatient;
use App\Models\Pathology;
use App\Models\PharmacyBill;
use App\Models\Payment;
use App\Models\Radiology;
use App\Models\Test;
use App\Models\IpdBedCharge;
use App\Models\IpdDoctorVisitCharge;
use App\Models\IpdOtCharge;
use App\Models\IpdRoomRentCharge;
use Carbon\Carbon;

class IpdDischargeBillingService
{
    /**
     * Calculate a running bill summary (without creating Billing records).
     */
    public function getRunningSummary(IpdPatient $ipdpatient, ?Carbon $asOf = null): array
    {
        $ipdpatient->loadMissing(['patient', 'doctor']);

        $admissionAt = $this->safeCarbon($ipdpatient->admission_date) ?? now();
        $asOf = $asOf
            ?? $this->safeCarbon($ipdpatient->discharged_at)
            ?? now();

        $lines = $this->collectBillItemLines($ipdpatient, $admissionAt, $asOf);
        $total = (float) collect($lines)->sum('net_amount');

        $paymentsQuery = Payment::query()
            ->where('ipd_patient_id', $ipdpatient->id)
            ->whereNull('deleted_at');

        $paidAmount = (float) $paymentsQuery->sum('amount');

        $dueAmount = max($total - $paidAmount, 0);
        $changeAmount = max($paidAmount - $total, 0);

        return [
            'as_of' => $asOf->toDateTimeString(),
            'items_count' => count($lines),
            'total' => $total,
            'paid' => $paidAmount,
            'due' => $dueAmount,
            'change' => $changeAmount,
            'payment_status' => $this->determinePaymentStatus($paidAmount, $total),
        ];
    }

    /**
     * Build running bill data for print view.
     */
    public function getRunningDetails(IpdPatient $ipdpatient, ?Carbon $asOf = null): array
    {
        $ipdpatient->loadMissing(['patient', 'doctor', 'bed']);

        $admissionAt = $this->safeCarbon($ipdpatient->admission_date) ?? now();
        $asOf = $asOf
            ?? $this->safeCarbon($ipdpatient->discharged_at)
            ?? now();

        $lines = $this->collectBillItemLines($ipdpatient, $admissionAt, $asOf);
        $total = (float) collect($lines)->sum('net_amount');

        $paymentsQuery = Payment::query()
            ->where('ipd_patient_id', $ipdpatient->id)
            ->whereNull('deleted_at');

        $paidAmount = (float) $paymentsQuery->sum('amount');

        $dueAmount = max($total - $paidAmount, 0);
        $changeAmount = max($paidAmount - $total, 0);

        return [
            'ipdpatient' => $ipdpatient,
            'lines' => $lines,
            'summary' => [
                'as_of' => $asOf->toDateTimeString(),
                'total' => $total,
                'paid' => $paidAmount,
                'due' => $dueAmount,
                'change' => $changeAmount,
                'payment_status' => $this->determinePaymentStatus($paidAmount, $total),
            ],
            'printed_at' => now()->format('d-M-Y h:i A'),
            'admission_at' => $admissionAt->toDateTimeString(),
        ];
    }
    /**
     * Create a discharge-time Billing + BillItem set for an IPD patient.
     *
     * বর্তমান ডাটাবেস অনুযায়ী IPD-এর bed/doctor/nursing charge আলাদা টেবিলে নেই,
     * তাই এখানে আমরা existing modules (Pathology, Radiology, Pharmacy) থেকে
     * admission-to-discharge period-এর charges যোগ করি।
     */
    public function createOrGetForDischarge(IpdPatient $ipdpatient, ?int $actorId = null): Billing
    {
        if (!empty($ipdpatient->billing_id)) {
            $existing = Billing::query()->find($ipdpatient->billing_id);
            if ($existing) {
                return $existing;
            }
        }

        $actorId = $actorId ?: (int) (auth('admin')->id() ?? 0);
        if ($actorId <= 0) {
            throw new \RuntimeException('Admin user id (created_by) is required to create IPD discharge billing.');
        }

        $ipdpatient->loadMissing(['patient', 'doctor']);

        $admissionAt = $this->safeCarbon($ipdpatient->admission_date) ?? now();
        $dischargeAt = $this->safeCarbon($ipdpatient->discharged_at) ?? now();

        // Deterministic case number for easy searching.
        $caseNumber = 'IPD-' . str_pad((string) $ipdpatient->id, 6, '0', STR_PAD_LEFT);
        if (Billing::withTrashed()->where('case_number', $caseNumber)->exists()) {
            $caseNumber .= '-' . now()->format('His');
        }

        $lines = $this->collectBillItemLines($ipdpatient, $admissionAt, $dischargeAt);

        $total = (float) collect($lines)->sum('net_amount');

        $paymentInfo = $this->getPaymentInfo($ipdpatient, null);
        $paidAmount = $paymentInfo['paid_amount'];
        $lastMethod = $paymentInfo['last_method'];

        $dueAmount = max($total - $paidAmount, 0);
        $changeAmount = max($paidAmount - $total, 0);

        $paymentStatus = $this->determinePaymentStatus($paidAmount, $total);

        // Billing requires some non-null fields in your schema.
        $patient = $ipdpatient->patient;
        $doctor = $ipdpatient->doctor;

        $billing = Billing::query()->create([
            // keep invoice_number & bill_number empty => Billing model will auto-generate.
            'case_number' => $caseNumber,

            'patient_id' => $patient?->id,
            'patient_mobile' => (string) ($patient?->mobile ?? $patient?->phone ?? ''),
            'gender' => $this->normalizeGender($patient?->gender),

            'doctor_id' => $doctor?->id,
            'doctor_type' => 'admin',
            'doctor_name' => $doctor?->name,

            'card_type' => $lastMethod ?: 'Cash',
            'pay_mode' => $lastMethod ?: 'Cash',
            'card_number' => null,

            'total' => $total,
            'discount' => 0,
            'extra_flat_discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => $total,
            'paid_amt' => $paidAmount,
            'change_amt' => $changeAmount,
            'receiving_amt' => $paidAmount,
            'due_amount' => $dueAmount,

            'delivery_date' => $dischargeAt,
            'delivery_time' => null,
            'remarks' => 'IPD Discharge Billing (Auto) | IPD#' . $ipdpatient->id,

            'commission_total' => 0,
            'physyst_amt' => 0,
            'commission_slider' => 0,

            'created_by' => $actorId,
            'payment_status' => $paymentStatus,
            'status' => 'Active',
        ]);

        $this->attachBillingToPayments($ipdpatient, $billing->id);

        foreach ($lines as $line) {
            BillItem::query()->create(array_merge($line, [
                'billing_id' => $billing->id,
            ]));
        }

        return $billing;
    }

    /**
     * Rebuild (regenerate) BillItems for a discharged IPD patient.
     *
     * Use-case: patient already discharged, but new charges (room rent/OT/doctor visit/pathology/etc)
     * were added later and you want to rebuild the final discharge bill.
     */
    public function regenerateForDischarge(IpdPatient $ipdpatient, ?int $actorId = null): Billing
    {
        $actorId = $actorId ?: (int) (auth('admin')->id() ?? 0);
        if ($actorId <= 0) {
            throw new \RuntimeException('Admin user id (created_by) is required to regenerate IPD discharge billing.');
        }

        $ipdpatient->loadMissing(['patient', 'doctor']);

        // Ensure a Billing exists.
        $billing = null;
        if (!empty($ipdpatient->billing_id)) {
            $billing = Billing::query()->find($ipdpatient->billing_id);
        }
        if (!$billing) {
            $billing = $this->createOrGetForDischarge($ipdpatient, $actorId);
        }

        $admissionAt = $this->safeCarbon($ipdpatient->admission_date) ?? now();
        $dischargeAt = $this->safeCarbon($ipdpatient->discharged_at) ?? now();

        // Soft-delete old bill items then re-create.
        BillItem::query()->where('billing_id', $billing->id)->delete();

        $lines = $this->collectBillItemLines($ipdpatient, $admissionAt, $dischargeAt);
        $total = (float) collect($lines)->sum('net_amount');

        $paymentInfo = $this->getPaymentInfo($ipdpatient, $billing->id);
        $paidAmount = $paymentInfo['paid_amount'];
        $lastMethod = $paymentInfo['last_method'];

        $dueAmount = max($total - $paidAmount, 0);
        $changeAmount = max($paidAmount - $total, 0);

        $billing->fill([
            'card_type' => $lastMethod ?: ($billing->card_type ?: 'Cash'),
            'pay_mode' => $lastMethod ?: ($billing->pay_mode ?: 'Cash'),

            'total' => $total,
            'payable_amount' => $total,
            'paid_amt' => $paidAmount,
            'change_amt' => $changeAmount,
            'receiving_amt' => $paidAmount,
            'due_amount' => $dueAmount,

            'delivery_date' => $dischargeAt,
            'remarks' => 'IPD Discharge Billing (Auto/Regen) | IPD#' . $ipdpatient->id,

            'payment_status' => $this->determinePaymentStatus($paidAmount, $total),
        ]);
        $billing->updated_by = $actorId;
        $billing->save();

        foreach ($lines as $line) {
            BillItem::query()->create(array_merge($line, [
                'billing_id' => $billing->id,
            ]));
        }

        $this->attachBillingToPayments($ipdpatient, $billing->id);

        return $billing;
    }

    public function refreshBillingTotals(IpdPatient $ipdpatient, ?int $actorId = null): Billing
    {
        $actorId = $actorId ?: (int) (auth('admin')->id() ?? 0);

        $billing = null;
        if (!empty($ipdpatient->billing_id)) {
            $billing = Billing::query()->find($ipdpatient->billing_id);
        }
        if (!$billing) {
            $billing = $this->createOrGetForDischarge($ipdpatient, $actorId > 0 ? $actorId : null);
        }

        $billing->loadMissing('billItems');
        $total = (float) ($billing->billItems?->sum('net_amount') ?? 0);

        $paymentInfo = $this->getPaymentInfo($ipdpatient, $billing->id);
        $paidAmount = $paymentInfo['paid_amount'];

        $dueAmount = max($total - $paidAmount, 0);
        $changeAmount = max($paidAmount - $total, 0);

        $billing->fill([
            'total' => $total,
            'payable_amount' => $total,
            'paid_amt' => $paidAmount,
            'change_amt' => $changeAmount,
            'receiving_amt' => $paidAmount,
            'due_amount' => $dueAmount,
            'payment_status' => $this->determinePaymentStatus($paidAmount, $total),
        ]);

        if ($actorId > 0) {
            $billing->updated_by = $actorId;
        }

        $billing->save();

        $this->attachBillingToPayments($ipdpatient, $billing->id);

        return $billing;
    }

    private function getPaymentInfo(IpdPatient $ipdpatient, ?int $billingId = null): array
    {
        $query = Payment::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where(function ($q) use ($ipdpatient, $billingId) {
                $q->where('ipd_patient_id', $ipdpatient->id);
                if ($billingId) {
                    $q->orWhere('billing_id', $billingId);
                }
            });

        $paidAmount = (float) $query->sum('amount');
        $lastMethod = (string) ($query->latest('id')->value('payment_method') ?? 'Cash');

        return [
            'paid_amount' => $paidAmount,
            'last_method' => $lastMethod,
        ];
    }

    private function attachBillingToPayments(IpdPatient $ipdpatient, int $billingId): void
    {
        Payment::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('ipd_patient_id', $ipdpatient->id)
            ->whereNull('billing_id')
            ->update(['billing_id' => $billingId]);
    }

    private function collectBillItemLines(IpdPatient $ipdpatient, Carbon $admissionAt, Carbon $dischargeAt): array
    {
        $reference = trim((string) ($ipdpatient->reference ?? ''));

        $pathologyQuery = Pathology::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('patient_id', $ipdpatient->patient_id);

        // Prefer linking by case_id if IPD reference matches.
        if ($reference !== '' && Pathology::query()->where('case_id', $reference)->exists()) {
            $pathologyQuery->where('case_id', $reference);
        } else {
            $pathologyQuery->whereBetween('date', [
                $admissionAt->toDateString(),
                $dischargeAt->toDateString(),
            ]);
        }

        $radiologyQuery = Radiology::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('patient_id', $ipdpatient->patient_id);

        if ($reference !== '' && Radiology::query()->where('case_id', $reference)->exists()) {
            $radiologyQuery->where('case_id', $reference);
        } else {
            $radiologyQuery->whereBetween('created_at', [$admissionAt, $dischargeAt]);
        }

        $pharmacyQuery = PharmacyBill::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('patient_id', $ipdpatient->patient_id);

        if ($reference !== '' && PharmacyBill::query()->where('case_id', $reference)->exists()) {
            $pharmacyQuery->where('case_id', $reference);
        } else {
            $pharmacyQuery->whereBetween('date', [
                $admissionAt->toDateString(),
                $dischargeAt->toDateString(),
            ]);
        }

        $lines = [];
        $includedTestIds = [];

        // Pathology tests => BillItem(category=Pathology)
        foreach ($pathologyQuery->get() as $pathology) {
            $tests = is_string($pathology->tests) ? json_decode($pathology->tests, true) : $pathology->tests;
            if (!is_array($tests)) {
                continue;
            }

            foreach ($tests as $row) {
                $testId = $row['testId'] ?? $row['test_id'] ?? null;
                if (!$testId) {
                    continue;
                }

                $testInfo = Test::query()->find($testId);
                $amount = (float) ($row['amount'] ?? $testInfo?->amount ?? $testInfo?->standard_charge ?? 0);

                $includedTestIds[(int) $testId] = true;

                $lines[] = [
                    'item_id' => (int) $testId,
                    'item_name' => (string) ($testInfo?->test_name ?? 'Pathology Test'),
                    'category' => $this->normalizeBillItemCategory('Pathology'),
                    'unit_price' => $amount,
                    'quantity' => 1,
                    'total_amount' => $amount,
                    'discount' => 0,
                    'rugound' => 0,
                    'net_amount' => $amount,
                    'status' => 'Active',
                ];
            }
        }

        // Radiology tests => BillItem(category=Radiology)
        foreach ($radiologyQuery->get() as $radiology) {
            $tests = is_string($radiology->test_details) ? json_decode($radiology->test_details, true) : $radiology->test_details;
            if (!is_array($tests)) {
                continue;
            }

            foreach ($tests as $row) {
                $testId = $row['testId'] ?? $row['test_id'] ?? $row['test_id'] ?? null;
                if (!$testId) {
                    continue;
                }

                $testInfo = Test::query()->find($testId);
                $amount = (float) ($row['amount'] ?? $row['net_amount'] ?? $testInfo?->amount ?? $testInfo?->standard_charge ?? 0);

                $includedTestIds[(int) $testId] = true;

                $lines[] = [
                    'item_id' => (int) $testId,
                    'item_name' => (string) ($testInfo?->test_name ?? 'Radiology Test'),
                    'category' => $this->normalizeBillItemCategory('Radiology'),
                    'unit_price' => $amount,
                    'quantity' => 1,
                    'total_amount' => $amount,
                    'discount' => 0,
                    'rugound' => 0,
                    'net_amount' => $amount,
                    'status' => 'Active',
                ];
            }
        }

        // ---------------------------------
        // Suggested Investigations from IPD Prescription
        // ---------------------------------
        // NOTE: Prescription currently stores test_name (string). We try to match it with tests.test_name.
        $ipdpatient->loadMissing(['latestPrescription.tests']);
        $suggestedTestNames = collect($ipdpatient->latestPrescription?->tests ?? [])
            ->map(function ($row) {
                return trim((string) ($row?->test_name ?? ''));
            })
            ->filter()
            ->unique()
            ->values();

        foreach ($suggestedTestNames as $testName) {
            $key = strtolower($testName);

            $test = Test::query()
                ->whereNull('deleted_at')
                ->where('status', 'Active')
                ->whereIn('category_type', ['Pathology', 'Radiology'])
                ->whereRaw('LOWER(test_name) = ?', [$key])
                ->first();

            if (!$test) {
                // fallback match on short name
                $test = Test::query()
                    ->whereNull('deleted_at')
                    ->where('status', 'Active')
                    ->whereIn('category_type', ['Pathology', 'Radiology'])
                    ->whereRaw('LOWER(test_short_name) = ?', [$key])
                    ->first();
            }

            if (!$test) {
                continue;
            }

            if (!empty($includedTestIds[(int) $test->id])) {
                continue;
            }

            $amount = (float) ($test->amount ?? $test->standard_charge ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $includedTestIds[(int) $test->id] = true;

            $lines[] = [
                'item_id' => (int) $test->id,
                'item_name' => (string) ($test->test_name ?? $testName),
                'category' => $this->normalizeBillItemCategory((string) ($test->category_type ?? 'Pathology')),
                'unit_price' => $amount,
                'quantity' => 1,
                'total_amount' => $amount,
                'discount' => 0,
                'rugound' => 0,
                'net_amount' => $amount,
                'status' => 'Active',
            ];
        }

        // Pharmacy products => BillItem(category=Medicine)
        foreach ($pharmacyQuery->get() as $pharmacyBill) {
            $products = is_string($pharmacyBill->products) ? json_decode($pharmacyBill->products, true) : $pharmacyBill->products;
            if (!is_array($products)) {
                continue;
            }

            foreach ($products as $row) {
                $productId = $row['productId'] ?? null;
                $qty = (float) ($row['quantity'] ?? 1);
                $amount = (float) ($row['amount'] ?? 0);
                $rate = (float) ($row['rate'] ?? ($qty > 0 ? $amount / $qty : 0));

                if (!$productId) {
                    continue;
                }

                $lines[] = [
                    'item_id' => (int) $productId,
                    'item_name' => (string) ($row['productName'] ?? 'Medicine'),
                    'category' => $this->normalizeBillItemCategory('Medicine'),
                    'unit_price' => $rate,
                    'quantity' => $qty > 0 ? $qty : 1,
                    'total_amount' => $amount,
                    'discount' => 0,
                    'rugound' => 0,
                    'net_amount' => $amount,
                    'status' => 'Active',
                ];
            }
        }

        // ----------------------------
        // IPD specific charges
        // ----------------------------

        // Room Rent (daily calculation based on started_at/ended_at)
        $roomRentCharges = IpdRoomRentCharge::query()
            ->with('bed')
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('ipd_patient_id', $ipdpatient->id)
            ->get();

        foreach ($roomRentCharges as $charge) {
            $start = $this->safeCarbon($charge->started_at) ?? $admissionAt;
            $end = $this->safeCarbon($charge->ended_at) ?? $dischargeAt;

            $start = $start->greaterThan($admissionAt) ? $start : $admissionAt;
            $end = $end->lessThan($dischargeAt) ? $end : $dischargeAt;

            if ($end->lt($start)) {
                continue;
            }

            $days = $this->calculateBillableDays($start, $end);
            $rate = (float) ($charge->rate_per_day ?? 0);
            $amount = $rate * $days;

            if ($days <= 0 || $amount <= 0) {
                continue;
            }

            $bedName = (string) ($charge->bed?->name ?? $ipdpatient->bed?->name ?? '');
            $label = 'Room Rent' . ($bedName !== '' ? (' (Bed: ' . $bedName . ')') : '') . ' [' . $start->toDateString() . ' to ' . $end->toDateString() . ']';

            $lines[] = [
                'item_id' => 0,
                'item_name' => $label,
                'category' => $this->normalizeBillItemCategory('Room Rent'),
                'unit_price' => $rate,
                'quantity' => $days,
                'total_amount' => $amount,
                'discount' => 0,
                'rugound' => 0,
                'net_amount' => $amount,
                'status' => 'Active',
            ];
        }

        // Bed Charge (daily calculation based on started_at/ended_at)
        $bedCharges = IpdBedCharge::query()
            ->with('bed')
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('ipd_patient_id', $ipdpatient->id)
            ->get();

        foreach ($bedCharges as $charge) {
            $start = $this->safeCarbon($charge->started_at) ?? $admissionAt;
            $end = $this->safeCarbon($charge->ended_at) ?? $dischargeAt;

            $start = $start->greaterThan($admissionAt) ? $start : $admissionAt;
            $end = $end->lessThan($dischargeAt) ? $end : $dischargeAt;

            if ($end->lt($start)) {
                continue;
            }

            $days = $this->calculateBillableDays($start, $end);
            $rate = (float) ($charge->rate_per_day ?? 0);
            $amount = $rate * $days;

            if ($days <= 0 || $amount <= 0) {
                continue;
            }

            $bedName = (string) ($charge->bed?->name ?? $ipdpatient->bed?->name ?? '');
            $label = 'Bed Charge' . ($bedName !== '' ? (' (Bed: ' . $bedName . ')') : '') . ' [' . $start->toDateString() . ' to ' . $end->toDateString() . ']';

            $lines[] = [
                'item_id' => 0,
                'item_name' => $label,
                'category' => $this->normalizeBillItemCategory('Bed Charge'),
                'unit_price' => $rate,
                'quantity' => $days,
                'total_amount' => $amount,
                'discount' => 0,
                'rugound' => 0,
                'net_amount' => $amount,
                'status' => 'Active',
            ];
        }

        // OT Charges
        $otCharges = IpdOtCharge::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('ipd_patient_id', $ipdpatient->id)
            ->get();

        foreach ($otCharges as $charge) {
            $performedAt = $this->safeCarbon($charge->performed_at);

            if ($performedAt && ($performedAt->lt($admissionAt) || $performedAt->gt($dischargeAt))) {
                continue;
            }

            $qty = (float) ($charge->quantity ?? 1);
            if ($qty <= 0) {
                $qty = 1;
            }

            $unit = (float) ($charge->unit_price ?? 0);
            $amount = (float) ($charge->total_amount ?? 0);
            if ($amount <= 0) {
                $amount = $unit * $qty;
            }

            if ($amount <= 0) {
                continue;
            }

            $name = trim((string) ($charge->charge_name ?? ''));
            if ($name === '') {
                $name = trim((string) ($charge->procedure_name ?? ''));
            }
            if ($name === '') {
                $name = 'OT Charge';
            }
            if ($performedAt) {
                $name .= ' (' . $performedAt->toDateString() . ')';
            }

            $lines[] = [
                'item_id' => (int) ($charge->charge_id ?? 0),
                'item_name' => $name,
                'category' => $this->normalizeBillItemCategory('OT'),
                'unit_price' => $unit,
                'quantity' => $qty,
                'total_amount' => $amount,
                'discount' => 0,
                'rugound' => 0,
                'net_amount' => $amount,
                'status' => 'Active',
            ];
        }

        // Doctor Visit Charges
        $doctorVisitCharges = IpdDoctorVisitCharge::query()
            ->with('doctor')
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('ipd_patient_id', $ipdpatient->id)
            ->get();

        foreach ($doctorVisitCharges as $charge) {
            $visitedAt = $this->safeCarbon($charge->visited_at);

            if ($visitedAt && ($visitedAt->lt($admissionAt) || $visitedAt->gt($dischargeAt))) {
                continue;
            }

            $qty = (float) ($charge->visit_count ?? 1);
            if ($qty <= 0) {
                $qty = 1;
            }

            $unit = (float) ($charge->fee_per_visit ?? 0);
            $amount = (float) ($charge->total_amount ?? 0);
            if ($amount <= 0) {
                $amount = $unit * $qty;
            }

            if ($amount <= 0) {
                continue;
            }

            $doctorName = trim((string) ($charge->doctor_name ?? $charge->doctor?->name ?? $ipdpatient->doctor?->name ?? ''));
            $name = 'Doctor Visit' . ($doctorName !== '' ? (' - ' . $doctorName) : '');
            if ($visitedAt) {
                $name .= ' (' . $visitedAt->toDateString() . ')';
            }

            $lines[] = [
                'item_id' => (int) ($charge->doctor_id ?? 0),
                'item_name' => $name,
                'category' => $this->normalizeBillItemCategory('Doctor Visit'),
                'unit_price' => $unit,
                'quantity' => $qty,
                'total_amount' => $amount,
                'discount' => 0,
                'rugound' => 0,
                'net_amount' => $amount,
                'status' => 'Active',
            ];
        }

        return $lines;
    }

    private function calculateBillableDays(Carbon $start, Carbon $end): float
    {
        // Count by calendar days and keep minimum 1.
        $s = $start->copy()->startOfDay();
        $e = $end->copy()->startOfDay();

        $days = $s->diffInDays($e) + 1;
        return (float) max($days, 1);
    }

    private function determinePaymentStatus(float $paidAmount, float $totalAmount): string
    {
        if ($totalAmount <= 0) {
            return $paidAmount > 0 ? 'Paid' : 'Pending';
        }

        if ($paidAmount <= 0) {
            return 'Pending';
        }

        if ($paidAmount >= $totalAmount) {
            return 'Paid';
        }

        return 'Partial';
    }

    private function normalizeGender($gender): string
    {
        $g = strtolower(trim((string) $gender));

        if (in_array($g, ['male', 'm'], true)) {
            return 'Male';
        }
        if (in_array($g, ['female', 'f'], true)) {
            return 'Female';
        }

        return 'Others';
    }

    private function safeCarbon($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $err) {
            return null;
        }
    }

    private function normalizeBillItemCategory(string $category): string
    {
        $category = trim($category);
        if ($category === '') {
            return 'Medicine';
        }
        $allowed = ['Pathology', 'Radiology', 'Medicine'];
        return in_array($category, $allowed, true) ? $category : 'Medicine';
    }
}
