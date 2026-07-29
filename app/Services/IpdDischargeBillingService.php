<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\BillItem;
use App\Models\DueCollection;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class IpdDischargeBillingService
{
    /**
     * Simple in-request cache to avoid recalculating identical bill lines
     * when the service is invoked multiple times during one request.
     * Keyed by ipd_id + admission + discharge + existing items count.
     */
    private $collectBillItemLinesCache = [];
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
        $total = round((float) collect($lines)->sum('net_amount'), 2);

        $paymentInfo = $this->getPaymentInfo($ipdpatient, null);
        $paidAmount = round((float) $paymentInfo['paid_amount'], 2);

        $billing = $ipdpatient->billing ?? null;
        $payable = $billing ? $this->calculateBillingPayable($total, $billing) : $total;

        $dueAmount = max($payable - $paidAmount, 0);
        $changeAmount = max($paidAmount - $payable, 0);

        return [
            'as_of' => $asOf->toDateTimeString(),
            'items_count' => count($lines),
            'total' => $total,
            'paid' => $paidAmount,
            'due' => $dueAmount,
            'change' => $changeAmount,
            'payment_status' => $this->determinePaymentStatus($paidAmount, $payable),
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
        $total = round((float) collect($lines)->sum('net_amount'), 2);

        $paymentInfo = $this->getPaymentInfo($ipdpatient, null);
        $paidAmount = round((float) $paymentInfo['paid_amount'], 2);

        $billing = $ipdpatient->billing ?? null;
        $payable = $billing ? $this->calculateBillingPayable($total, $billing) : $total;

        $dueAmount = max($payable - $paidAmount, 0);
        $changeAmount = max($paidAmount - $payable, 0);

        return [
            'ipdpatient' => $ipdpatient,
            'lines' => $lines,
            'summary' => [
                'as_of' => $asOf->toDateTimeString(),
                'total' => $total,
                'paid' => $paidAmount,
                'due' => $dueAmount,
                'change' => $changeAmount,
                'payment_status' => $this->determinePaymentStatus($paidAmount, $payable),
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
        
        $total = round((float) collect($lines)->sum('net_amount'), 2);

        $paymentInfo = $this->getPaymentInfo($ipdpatient, null);
        $paidAmount = round((float) $paymentInfo['paid_amount'], 2);
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
            'remarks' => '',

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

        $existingBillingItems = BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereNull('deleted_at')
            ->get();

        // Soft-delete old bill items then re-create.
        BillItem::query()->where('billing_id', $billing->id)->delete();

        $lines = $this->collectBillItemLines($ipdpatient, $admissionAt, $dischargeAt, $existingBillingItems);
        $total = round((float) collect($lines)->sum('net_amount'), 2);

        $paymentInfo = $this->getPaymentInfo($ipdpatient, $billing->id);
        $paidAmount = round((float) $paymentInfo['paid_amount'], 2);
        $lastMethod = $paymentInfo['last_method'];

        $payable = $this->calculateBillingPayable($total, $billing);
        $dueAmount = max($payable - $paidAmount, 0);
        $changeAmount = max($paidAmount - $payable, 0);

        $billing->fill([
            'card_type' => $lastMethod ?: ($billing->card_type ?: 'Cash'),
            'pay_mode' => $lastMethod ?: ($billing->pay_mode ?: 'Cash'),

            'total' => $total,
            'payable_amount' => $payable,
            'paid_amt' => $paidAmount,
            'change_amt' => $changeAmount,
            'receiving_amt' => $paidAmount,
            'due_amount' => $dueAmount,

            'delivery_date' => $dischargeAt,
            'remarks' => $billing->remarks ?? '',

            'payment_status' => $this->determinePaymentStatus($paidAmount, $payable),
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

        // Rebuild bill items from the live running-bill data so stale billing rows
        // do not keep the final invoice totals out of sync with the overview/running bill.
        $billing = $this->regenerateForDischarge($ipdpatient, $actorId > 0 ? $actorId : null);
        $billing->loadMissing('billItems');
        $total = round((float) ($billing->billItems?->sum('net_amount') ?? 0), 2);

        $paymentInfo = $this->getPaymentInfo($ipdpatient, $billing->id);
        $paidAmount = round((float) $paymentInfo['paid_amount'], 2);

        $payable = $this->calculateBillingPayable($total, $billing);
        $dueAmount = max($payable - $paidAmount, 0);
        $changeAmount = max($paidAmount - $payable, 0);

        $billing->fill([
            'total' => $total,
            'payable_amount' => $payable,
            'paid_amt' => $paidAmount,
            'change_amt' => $changeAmount,
            'receiving_amt' => $paidAmount,
            'due_amount' => $dueAmount,
            'payment_status' => $this->determinePaymentStatus($paidAmount, $payable),
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
        $effectiveBillingId = $billingId ?? (int) ($ipdpatient->billing_id ?? 0);

        $query = Payment::query();
        if ((bool) Schema::hasColumn((new Payment())->getTable(), 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $query->where('status', 'Active')
            ->where(function ($q) use ($ipdpatient, $effectiveBillingId) {
                $q->where('ipd_patient_id', $ipdpatient->id);
                if ($effectiveBillingId > 0) {
                    $q->orWhere('billing_id', $effectiveBillingId);
                }
            });

        $paidAmount = (float) $query->sum('amount');

        $billingIds = collect();
        if ($effectiveBillingId > 0) {
            $billingIds->push($effectiveBillingId);
        }

        $paymentBillingQuery = Payment::query();
        if ((bool) Schema::hasColumn((new Payment())->getTable(), 'deleted_at')) {
            $paymentBillingQuery->whereNull('deleted_at');
        }
        $billingIds = $billingIds->merge(
            $paymentBillingQuery
                ->where('status', 'Active')
                ->where('ipd_patient_id', $ipdpatient->id)
                ->whereNotNull('billing_id')
                ->pluck('billing_id')
                ->filter(fn($value) => (int) $value > 0)
                ->unique()
        );

        $dueCollectionQuery = DueCollection::query();
        if ((bool) Schema::hasColumn((new DueCollection())->getTable(), 'deleted_at')) {
            $dueCollectionQuery->whereNull('deleted_at');
        }

        $dueCollectionQuery->where(function ($q) use ($billingIds, $ipdpatient, $effectiveBillingId) {
            if ($billingIds->isNotEmpty()) {
                $q->whereIn('billing_id', $billingIds->all());
            }

            if ((int) ($ipdpatient->id ?? 0) > 0) {
                $q->orWhere(function ($sub) use ($ipdpatient) {
                    $sub->where(function ($noteQuery) use ($ipdpatient) {
                        $noteQuery->where('note', 'like', '%ipd_patient_id:' . $ipdpatient->id . '%')
                            ->orWhere('note', 'like', '%ipd_patient_id: ' . $ipdpatient->id . '%')
                            ->orWhere('note', 'like', '%Collected via IPD payment%');
                    });
                });
            }
        });

        $paidAmount += (float) $dueCollectionQuery->sum('collected_amount');

        $lastMethod = (string) ($query->latest('id')->value('payment_method') ?? 'Cash');

        return [
            'paid_amount' => $paidAmount,
            'last_method' => $lastMethod,
        ];
    }

    private function attachBillingToPayments(IpdPatient $ipdpatient, int $billingId): void
    {
        $attachQuery = Payment::query();
        if ((bool) Schema::hasColumn((new Payment())->getTable(), 'deleted_at')) {
            $attachQuery->whereNull('deleted_at');
        }
        $attachQuery->where('status', 'Active')
            ->where('ipd_patient_id', $ipdpatient->id)
            ->whereNull('billing_id')
            ->update(['billing_id' => $billingId]);
    }

    private function collectBillItemLines(IpdPatient $ipdpatient, Carbon $admissionAt, Carbon $dischargeAt, $existingBillingItems = null): array
    {
        // Build a stable cache key for identical requests within the same PHP process/request
        try {
            $existingCount = is_iterable($existingBillingItems) ? count($existingBillingItems) : (int) ($existingBillingItems ? 1 : 0);
        } catch (\Throwable $e) {
            $existingCount = 0;
        }

        $cacheKey = md5(implode('|', [
            (int) ($ipdpatient->id ?? 0),
            $admissionAt->toDateTimeString(),
            $dischargeAt->toDateTimeString(),
            (string) $existingCount,
        ]));

        if (isset($this->collectBillItemLinesCache[$cacheKey])) {
            // small debug log for visibility when cache is hit
            Log::debug('IpdDischargeBillingService::collectBillItemLines - cache hit', ['ipd_id' => $ipdpatient->id, 'key' => $cacheKey]);
            return $this->collectBillItemLinesCache[$cacheKey];
        }
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

        // Include any existing Billing->BillItems that were created via
        // IPD "Add from Hospital Charges" flow. These are stored as
        // BillItem rows on a Billing and should appear in the running
        // bill before discharge. We normalize category names and mark
        // pathology/radiology item ids to avoid duplicate lines later.
        $billItemSeed = collect($existingBillingItems ?? []);
        if ($billItemSeed->isEmpty() && !empty($ipdpatient->billing_id)) {
            $billing = Billing::query()->with('billItems')->find($ipdpatient->billing_id);
            if ($billing && $billing->billItems->isNotEmpty()) {
                $billItemSeed = collect($billing->billItems);
            }
        }

        if ($billItemSeed->isNotEmpty()) {
            foreach ($billItemSeed as $bi) {
                $category = $this->normalizeBillItemCategory((string) ($bi->category ?? ''));
                $itemId = (int) ($bi->item_id ?? 0);

                // Legacy safeguard: old IPD manual/admission lines were saved as
                // category=Medicine with null/0 item_id. In IPD running/final bill,
                // these should be treated as IPD, not pharmacy medicine.
                if ($category === 'Medicine' && $itemId <= 0) {
                    $category = 'IPD';
                }

                if (in_array($category, ['Pathology', 'Radiology'], true) && $itemId > 0) {
                    $includedTestIds[$itemId] = true;
                }

                $lines[] = [
                    'item_id' => $itemId,
                    'item_name' => (string) ($bi->item_name ?? ''),
                    'category' => $category,
                    'unit_price' => (float) ($bi->unit_price ?? 0),
                    'quantity' => (float) ($bi->quantity ?? 1),
                    'total_amount' => (float) ($bi->total_amount ?? 0),
                    'discount' => (float) ($bi->discount ?? 0),
                    'rugound' => (float) ($bi->rugound ?? 0),
                    'net_amount' => (float) ($bi->net_amount ?? 0),
                    'status' => (string) ($bi->status ?? 'Active'),
                ];
            }
        }

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
                ->whereIn('category_type', ['Pathology', 'Radiology', 'ECG', 'Ultrasound'])
                ->whereRaw('LOWER(test_name) = ?', [$key])
                ->first();

            if (!$test) {
                // fallback match on short name
                $test = Test::query()
                    ->whereNull('deleted_at')
                    ->where('status', 'Active')
                    ->whereIn('category_type', ['Pathology', 'Radiology', 'ECG', 'Ultrasound'])
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

        // If we only found bed charges, try a relaxed fallback window (±1 day)
        try {
            $foundCategories = collect($lines)->map(function ($ln) { return strtolower(trim($ln['category'] ?? '')) ?: 'ipd'; })->unique()->values()->all();
            $onlyBed = count($foundCategories) === 1 && in_array('bed charge', $foundCategories, true);
        } catch (\Throwable $e) {
            $onlyBed = false;
        }

        if ($onlyBed) {
            try {
                $fallbackStart = $admissionAt->copy()->subDay();
                $fallbackEnd = $dischargeAt->copy()->addDay();

                // Pathology fallback
                $pathFallback = Pathology::query()
                    ->whereNull('deleted_at')
                    ->where('status', 'Active')
                    ->where('patient_id', $ipdpatient->patient_id)
                    ->whereBetween('date', [$fallbackStart->toDateString(), $fallbackEnd->toDateString()]);

                if ($reference !== '' && Pathology::query()->where('case_id', $reference)->exists()) {
                    $pathFallback->where('case_id', $reference);
                }

                foreach ($pathFallback->get() as $pathology) {
                    $tests = is_string($pathology->tests) ? json_decode($pathology->tests, true) : $pathology->tests;
                    if (!is_array($tests)) {
                        continue;
                    }

                    foreach ($tests as $row) {
                        $testId = $row['testId'] ?? $row['test_id'] ?? null;
                        if (!$testId) {
                            continue;
                        }

                        if (!empty($includedTestIds[(int) $testId])) {
                            continue;
                        }

                        $testInfo = Test::query()->find($testId);
                        $amount = (float) ($row['amount'] ?? $testInfo?->amount ?? $testInfo?->standard_charge ?? 0);
                        if ($amount <= 0) {
                            continue;
                        }

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

                // Radiology fallback
                $radFallback = Radiology::query()
                    ->whereNull('deleted_at')
                    ->where('status', 'Active')
                    ->where('patient_id', $ipdpatient->patient_id)
                    ->whereBetween('created_at', [$fallbackStart, $fallbackEnd]);

                if ($reference !== '' && Radiology::query()->where('case_id', $reference)->exists()) {
                    $radFallback->where('case_id', $reference);
                }

                foreach ($radFallback->get() as $radiology) {
                    $tests = is_string($radiology->test_details) ? json_decode($radiology->test_details, true) : $radiology->test_details;
                    if (!is_array($tests)) {
                        continue;
                    }

                    foreach ($tests as $row) {
                        $testId = $row['testId'] ?? $row['test_id'] ?? null;
                        if (!$testId) {
                            continue;
                        }

                        if (!empty($includedTestIds[(int) $testId])) {
                            continue;
                        }

                        $testInfo = Test::query()->find($testId);
                        $amount = (float) ($row['amount'] ?? $row['net_amount'] ?? $testInfo?->amount ?? $testInfo?->standard_charge ?? 0);
                        if ($amount <= 0) {
                            continue;
                        }

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

                // Pharmacy fallback
                $pharmFallback = PharmacyBill::query()
                    ->whereNull('deleted_at')
                    ->where('status', 'Active')
                    ->where('patient_id', $ipdpatient->patient_id)
                    ->whereBetween('date', [$fallbackStart->toDateString(), $fallbackEnd->toDateString()]);

                if ($reference !== '' && PharmacyBill::query()->where('case_id', $reference)->exists()) {
                    $pharmFallback->where('case_id', $reference);
                }

                foreach ($pharmFallback->get() as $pharmacyBill) {
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

                Log::info('IpdDischargeBillingService::collectBillItemLines - fallback added', ['ipd_id' => $ipdpatient->id, 'from' => $fallbackStart->toDateString(), 'to' => $fallbackEnd->toDateString()]);
            } catch (\Throwable $e) {
                Log::warning('IpdDischargeBillingService::collectBillItemLines - fallback failed', ['err' => $e->getMessage(), 'ipd_id' => $ipdpatient->id]);
            }
        }

        $lines = $this->deduplicateLines($lines);

        // Diagnostic logging: group by category to help debug missing items
        try {
            $counts = collect($lines)
                ->groupBy(function ($ln) {
                    return strtolower(trim($ln['category'] ?? '')) ?: 'ipd';
                })->map->count()->toArray();

            Log::info('IpdDischargeBillingService::collectBillItemLines - counts', [
                'ipd_id' => $ipdpatient->id,
                'admission_at' => $admissionAt->toDateTimeString(),
                'discharge_at' => $dischargeAt->toDateTimeString(),
                'reference' => $ipdpatient->reference ?? null,
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            Log::warning('IpdDischargeBillingService::collectBillItemLines - logging failed', ['err' => $e->getMessage(), 'ipd_id' => $ipdpatient->id]);
        }

        // store in in-request cache to avoid re-calculating for identical params
        try {
            if (!empty($cacheKey)) {
                $this->collectBillItemLinesCache[$cacheKey] = $lines;
            }
        } catch (\Throwable $e) {
            // ignore cache store errors
        }

        return $lines;
    }

    protected function deduplicateLines(array $lines): array
    {
        $uniqueLines = [];
        $seenSignatures = [];

        foreach ($lines as $line) {
            $lineArray = is_array($line) ? $line : (array) $line;
            $signature = $this->buildLineSignature($lineArray);
            if ($signature === '' || isset($seenSignatures[$signature])) {
                continue;
            }

            $seenSignatures[$signature] = true;
            $uniqueLines[] = $line;
        }

        return $uniqueLines;
    }

    protected function buildLineSignature(array $line): string
    {
        $itemId = (int) ($line['item_id'] ?? $line['id'] ?? 0);
        $itemName = strtolower(trim((string) ($line['item_name'] ?? $line['description'] ?? '')));
        $category = strtolower(trim((string) ($line['category'] ?? '')));
        $unitPrice = number_format((float) ($line['unit_price'] ?? $line['rate'] ?? 0), 2, '.', '');
        $quantity = number_format((float) ($line['quantity'] ?? 1), 2, '.', '');
        $netAmount = number_format((float) ($line['net_amount'] ?? $line['total_amount'] ?? 0), 2, '.', '');

        return implode('|', [$category, (string) $itemId, $itemName, $unitPrice, $quantity, $netAmount]);
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

    private function calculateBillingPayable(float $total, Billing $billing): float
    {
        $discount = max(0, (float) ($billing->discount ?? 0));
        $extraFlatDiscount = max(0, (float) ($billing->extra_flat_discount ?? 0));
        $discountAmount = 0.0;

        if ($discount > 0) {
            if (($billing->discount_type ?? 'flat') === 'percentage') {
                $discountAmount = $total * ($discount / 100);
            } else {
                $discountAmount = $discount;
            }
        }

        return max(0, round($total - $discountAmount - $extraFlatDiscount, 2));
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
        $c = strtolower(trim($category));
        if ($c === '') {
            return 'IPD';
        }

        if (str_contains($c, 'path')) {
            return 'Pathology';
        }

        if (str_contains($c, 'radio') || str_contains($c, 'xray') || str_contains($c, 'ct') || str_contains($c, 'mri') || str_contains($c, 'ultra') || str_contains($c, 'ecg') || str_contains($c, 'sonogram')) {
            return 'Radiology';
        }

        if (str_contains($c, 'med') || str_contains($c, 'pharm') || str_contains($c, 'drug') || str_contains($c, 'medicine')) {
            return 'Medicine';
        }

        // IPD-specific charges: return explicit DB enum values
        if (str_contains($c, 'room') || str_contains($c, 'rent')) {
            return 'Room Rent';
        }

        if (str_contains($c, 'bed')) {
            return 'Bed Charge';
        }

        if (str_contains($c, 'ot')) {
            return 'OT';
        }

        if (str_contains($c, 'doctor') || str_contains($c, 'visit')) {
            return 'Doctor Visit';
        }

        if (str_contains($c, 'ipd') || str_contains($c, 'admission') || str_contains($c, 'indoor')) {
            return 'IPD';
        }

        if (str_contains($c, 'opd') || str_contains($c, 'outdoor')) {
            return 'OPD';
        }

        return 'IPD';
    }
}
