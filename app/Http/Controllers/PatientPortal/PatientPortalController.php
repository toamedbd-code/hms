<?php

namespace App\Http\Controllers\PatientPortal;

use App\Http\Controllers\Controller;
use App\Models\Appoinment;
use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\InvoiceDesign;
use App\Models\IpdPatient;
use App\Models\OpdPatient;
use App\Models\Patient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientPortalController extends Controller
{
    public function loginForm(Request $request)
    {
        $this->ensurePortalEnabled();

        $prefillPhone = (string) $request->query('phone', '');
        $prefillPatientId = (string) $request->query('patient_id', '');

        $tokenPayload = $this->decodePortalToken((string) $request->query('token', ''));
        if (!empty($tokenPayload)) {
            $prefillPhone = (string) ($tokenPayload['phone'] ?? $prefillPhone);
            $prefillPatientId = (string) ($tokenPayload['patient_id'] ?? $prefillPatientId);
        }

        return view('patient-portal.login', [
            'prefill' => [
                'phone' => $prefillPhone,
                'patient_id' => $prefillPatientId,
                'token' => (string) $request->query('token', ''),
            ],
        ]);
    }

    public function login(Request $request)
    {
        $this->ensurePortalEnabled();

        $data = $request->validate([
            'phone' => 'required|string|max:30',
            'patient_id' => 'required|string|max:40',
            'token' => 'nullable|string',
        ]);

        $identifierRaw = trim((string) $data['patient_id']);
        $patient = null;
        $billingFromIdentifier = null;

        if (preg_match('/^\d+$/', $identifierRaw) === 1) {
            $patientId = (int) $identifierRaw;
            if ($patientId > 0) {
                $patient = Patient::query()
                    ->whereNull('deleted_at')
                    ->where('id', $patientId)
                    ->first();
            }
        } else {
            $normalizedBillNumber = strtoupper(preg_replace('/\s+/', '', $identifierRaw));

            $billingFromIdentifier = Billing::query()
                ->whereRaw('UPPER(REPLACE(bill_number, " ", "")) = ?', [$normalizedBillNumber])
                ->first();

            if ($billingFromIdentifier && (int) ($billingFromIdentifier->patient_id ?? 0) > 0) {
                $patient = Patient::query()
                    ->whereNull('deleted_at')
                    ->where('id', (int) $billingFromIdentifier->patient_id)
                    ->first();
            }
        }

        if (!$patient) {
            return back()->with('error', 'Patient record পাওয়া যায়নি। Patient ID বা Bill No আবার চেক করুন।')->withInput();
        }

        $status = strtolower(trim((string) ($patient->status ?? '')));
        if ($status !== '' && $status !== 'active') {
            return back()->with('error', 'এই patient account inactive।')->withInput();
        }

        $submittedPhone = (string) $data['phone'];
        $patientPhone = (string) ($patient->phone ?? '');
        $billingPhone = (string) ($billingFromIdentifier?->patient_mobile ?? '');

        $tokenPayload = $this->decodePortalToken((string) ($data['token'] ?? ''));
        $tokenPhone = (string) ($tokenPayload['phone'] ?? '');
        $tokenPatientId = isset($tokenPayload['patient_id']) ? (int) $tokenPayload['patient_id'] : null;

        $hasValidTokenForPatient = !empty($tokenPayload) && $tokenPatientId === (int) $patient->id;
        $isPhoneMatch = $this->phonesMatch($submittedPhone, $patientPhone)
            || ($billingPhone !== '' && $this->phonesMatch($submittedPhone, $billingPhone))
            || ($tokenPhone !== '' && $this->phonesMatch($submittedPhone, $tokenPhone));

        if (!$isPhoneMatch && !$hasValidTokenForPatient) {
            return back()->with('error', 'Phone অথবা Patient ID মিলছে না।')->withInput();
        }

        Auth::guard('patient')->login($patient);
        $request->session()->regenerate();

        return redirect()->route('backend.patient.portal.dashboard');
    }

    public function dashboard(Request $request)
    {
        $this->ensurePortalEnabled();

        /** @var Patient $patient */
        $patient = Auth::guard('patient')->user();

        $fromDate = (string) $request->input('from_date', '');
        $toDate = (string) $request->input('to_date', '');

        $appointmentsQuery = Appoinment::query()
            ->where('patient_id', $patient->id)
            ->when($fromDate !== '', fn ($q) => $q->whereDate('appoinment_date', '>=', $fromDate))
            ->when($toDate !== '', fn ($q) => $q->whereDate('appoinment_date', '<=', $toDate));

        $appointments = (clone $appointmentsQuery)
            ->orderByDesc('appoinment_date')
            ->limit(10)
            ->get(['id', 'appoinment_date', 'slot', 'appoinment_status', 'doctor_fee']);

        $billingsQuery = Billing::query()
            ->where('patient_id', $patient->id)
            ->when($fromDate !== '', fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate !== '', fn ($q) => $q->whereDate('created_at', '<=', $toDate));

        $billings = (clone $billingsQuery)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'bill_number', 'total', 'paid_amt', 'due_amount', 'payment_status', 'created_at']);

        $opdVisitsQuery = OpdPatient::query()
            ->where('patient_id', $patient->id)
            ->when($fromDate !== '', fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate !== '', fn ($q) => $q->whereDate('created_at', '<=', $toDate));

        $opdVisits = (clone $opdVisitsQuery)
            ->orderByDesc('id')
            ->limit(10)
            ->get($this->opdSelectColumns());

        $ipdAdmissionsQuery = IpdPatient::query()
            ->where('patient_id', $patient->id)
            ->when($fromDate !== '', fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate !== '', fn ($q) => $q->whereDate('created_at', '<=', $toDate));

        $ipdAdmissions = (clone $ipdAdmissionsQuery)
            ->orderByDesc('id')
            ->limit(10)
            ->get($this->ipdSelectColumns());

        $billingSummary = (clone $billingsQuery)
            ->selectRaw('COALESCE(SUM(total),0) as total_amount')
            ->selectRaw('COALESCE(SUM(paid_amt),0) as total_paid')
            ->selectRaw('COALESCE(SUM(due_amount),0) as total_due')
            ->first();

        if ((string) $request->input('export', '') === 'csv') {
            return $this->exportDashboardCsv($patient, $fromDate, $toDate, $appointmentsQuery, $billingsQuery, $opdVisitsQuery, $ipdAdmissionsQuery);
        }

        return view('patient-portal.dashboard', [
            'patient' => $patient,
            'appointments' => $appointments,
            'billings' => $billings,
            'opdVisits' => $opdVisits,
            'ipdAdmissions' => $ipdAdmissions,
            'billingSummary' => $billingSummary,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    public function paymentGateway(Request $request, ?int $billing = null)
    {
        $this->ensurePortalEnabled();

        /** @var Patient $patient */
        $patient = Auth::guard('patient')->user();

        $dueBillsQuery = Billing::query()
            ->where('patient_id', $patient->id)
            ->where('due_amount', '>', 0)
            ->orderByDesc('id');

        $dueBills = (clone $dueBillsQuery)
            ->get(['id', 'bill_number', 'due_amount', 'created_at']);

        if ($dueBills->isEmpty()) {
            return redirect()->route('backend.patient.portal.dashboard')->with('success', 'আপনার কোনো due নেই। আপনি report download করতে পারবেন।');
        }

        $selectedBill = null;
        if ($billing !== null) {
            $selectedBill = $dueBills->firstWhere('id', $billing);
            if (!$selectedBill) {
                abort(404, 'Requested due bill not found for this patient.');
            }
        }

        $selectedAmount = (float) ($selectedBill->due_amount ?? 0);
        $totalDueAmount = (float) $dueBills->sum('due_amount');
        $payAmount = $selectedBill ? $selectedAmount : $totalDueAmount;

        $paymentGatewayBaseUrl = trim((string) config('services.patient_portal.payment_gateway_url', ''));
        $paymentGatewayUrl = '';

        $settings = get_cached_web_setting();

        $personalBkashNumber = trim((string) ($settings?->personal_bkash_number ?? ''));
        $personalNagadNumber = trim((string) ($settings?->personal_nagad_number ?? ''));

        if ($paymentGatewayBaseUrl !== '') {
            $paymentCallbackToken = $this->encodePortalToken([
                'patient_id' => (int) $patient->id,
                'billing_id' => $selectedBill?->id,
                'phone' => (string) $patient->phone,
            ], now()->addHours(2));

            $query = [
                'patient_id' => $patient->id,
                'phone' => $patient->phone,
                'amount' => number_format($payAmount, 2, '.', ''),
                'bill_id' => $selectedBill?->id,
                'callback_url' => route('backend.patient.portal.payment.callback', ['token' => $paymentCallbackToken]),
                'return_url' => route('backend.patient.portal.payment.callback', ['token' => $paymentCallbackToken]),
            ];

            $paymentGatewayUrl = $paymentGatewayBaseUrl . (str_contains($paymentGatewayBaseUrl, '?') ? '&' : '?') . http_build_query($query);
        }

        return view('patient-portal.payment', [
            'patient' => $patient,
            'dueBills' => $dueBills,
            'selectedBill' => $selectedBill,
            'payAmount' => $payAmount,
            'totalDueAmount' => $totalDueAmount,
            'paymentGatewayUrl' => $paymentGatewayUrl,
            'personalBkashNumber' => $personalBkashNumber,
            'personalNagadNumber' => $personalNagadNumber,
        ]);
    }

    public function paymentCallback(Request $request)
    {
        $this->ensurePortalEnabled();

        $callbackPayload = $this->extractGatewayCallbackPayload($request);

        if (!$callbackPayload['is_success']) {
            return redirect()->route('backend.patient.portal.login')->with('error', 'Payment not completed. Please try again.');
        }

        $tokenPayload = $this->decodePortalToken((string) $callbackPayload['token']);
        if (empty($tokenPayload['patient_id'])) {
            return redirect()->route('backend.patient.portal.login')->with('error', 'Invalid payment callback token.');
        }

        $patientId = (int) $tokenPayload['patient_id'];
        $phone = (string) ($tokenPayload['phone'] ?? '');
        $billingId = isset($tokenPayload['billing_id']) && $tokenPayload['billing_id'] !== null
            ? (int) $tokenPayload['billing_id']
            : null;

        $billingIdFromCallback = $callbackPayload['billing_id'];
        if ($billingId === null && $billingIdFromCallback !== null) {
            $billingId = $billingIdFromCallback;
        }

        $amountFromGateway = (float) $callbackPayload['amount'];
        $transactionRef = (string) $callbackPayload['transaction_ref'];

        $updatedDue = 0;
        $collectedAmount = 0;

        DB::transaction(function () use ($patientId, $billingId, $amountFromGateway, $transactionRef, &$updatedDue, &$collectedAmount) {
            $dueBillsQuery = Billing::query()
                ->where('patient_id', $patientId)
                ->where('due_amount', '>', 0)
                ->orderBy('id');

            if ($billingId !== null) {
                $dueBillsQuery->where('id', $billingId);
            }

            $dueBills = $dueBillsQuery->lockForUpdate()->get();
            if ($dueBills->isEmpty()) {
                return;
            }

            if ($transactionRef !== '') {
                $billingIds = $dueBills->pluck('id')->all();
                $alreadyProcessed = DueCollection::query()
                    ->whereIn('billing_id', $billingIds)
                    ->where('payment_method', 'Online Gateway')
                    ->where('note', 'like', '%trx: ' . $transactionRef . '%')
                    ->exists();

                if ($alreadyProcessed) {
                    $updatedDue = (float) Billing::query()
                        ->where('patient_id', $patientId)
                        ->sum('due_amount');
                    return;
                }
            }

            $totalDue = (float) $dueBills->sum('due_amount');
            $remaining = $amountFromGateway > 0 ? min($amountFromGateway, $totalDue) : $totalDue;

            foreach ($dueBills as $bill) {
                if ($remaining <= 0) {
                    break;
                }

                $billDue = (float) ($bill->due_amount ?? 0);
                if ($billDue <= 0) {
                    continue;
                }

                $payNow = min($billDue, $remaining);
                if ($payNow <= 0) {
                    continue;
                }

                DueCollection::query()->create([
                    'billing_id' => $bill->id,
                    'collected_amount' => $payNow,
                    'collected_at' => now(),
                    'payment_method' => 'Online Gateway',
                    'note' => $transactionRef !== ''
                        ? 'Patient portal gateway payment, trx: ' . $transactionRef
                        : 'Patient portal gateway payment',
                    'created_by' => null,
                ]);

                $bill->paid_amt = (float) ($bill->paid_amt ?? 0) + $payNow;
                $bill->due_amount = max(0, $billDue - $payNow);
                $bill->payment_status = $bill->due_amount <= 0 ? 'Paid' : 'Partial';
                $bill->save();

                $collectedAmount += $payNow;
                $remaining -= $payNow;
            }

            $updatedDue = (float) Billing::query()
                ->where('patient_id', $patientId)
                ->sum('due_amount');
        });

        if (Auth::guard('patient')->check() && (int) Auth::guard('patient')->id() === $patientId) {
            return redirect()->route('backend.patient.portal.dashboard')->with('success', 'Payment সফল হয়েছে। Collected: Tk ' . number_format($collectedAmount, 2) . ' | Remaining Due: Tk ' . number_format($updatedDue, 2));
        }

        return redirect()->route('backend.patient.portal.login', [
            'token' => $this->encodePortalToken([
                'patient_id' => $patientId,
                'phone' => $phone,
            ], now()->addMinutes(30)),
        ])->with('success', 'Payment সফল হয়েছে। এখন login করে report download করুন।');
    }

    private function extractGatewayCallbackPayload(Request $request): array
    {
        $token = trim((string) $this->extractCallbackValue($request, [
            'token',
            'callback_token',
            'custom_token',
            'value_d',
        ], ''));

        $statusRaw = strtolower(trim((string) $this->extractCallbackValue($request, [
            'status',
            'payment_status',
            'tran_status',
            'pay_status',
            'gateway_status',
        ], 'success')));

        $successStates = [
            'success',
            'successful',
            'paid',
            'completed',
            'complete',
            'valid',
            'validated',
            'succeeded',
        ];

        $amount = (float) $this->extractCallbackValue($request, [
            'amount',
            'total_amount',
            'payable_amount',
            'paid_amount',
            'trx_amount',
        ], 0);

        $transactionRef = trim((string) $this->extractCallbackValue($request, [
            'trx_id',
            'transaction_id',
            'tran_id',
            'bank_trx_id',
            'payment_id',
            'gateway_trx_id',
            'value_a',
        ], ''));

        $billingId = $this->extractCallbackValue($request, [
            'bill_id',
            'billing_id',
            'invoice_id',
            'value_b',
        ], null);

        $billingId = is_numeric($billingId) ? (int) $billingId : null;

        return [
            'token' => $token,
            'status' => $statusRaw,
            'is_success' => in_array($statusRaw, $successStates, true),
            'amount' => $amount,
            'transaction_ref' => $transactionRef,
            'billing_id' => $billingId,
        ];
    }

    private function extractCallbackValue(Request $request, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            $value = $request->query($key);
            if ($value !== null && $value !== '') {
                return $value;
            }

            $value = $request->input($key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    public function downloadBillingReport(Request $request, int $billing)
    {
        $this->ensurePortalEnabled();

        /** @var Patient $patient */
        $patient = Auth::guard('patient')->user();

        $totalDue = (float) Billing::query()
            ->where('patient_id', $patient->id)
            ->sum('due_amount');

        if ($totalDue > 0) {
            return redirect()->route('backend.patient.portal.payment')->with('error', 'Report download করতে আগে due clear করুন।');
        }

        $billingRecord = Billing::query()
            ->with([
                'billItems',
                'billItems.collectedBy',
                'billItems.reportedBy.details.designation',
                'dueCollections',
                'payments',
                'admin',
                'patient',
            ])
            ->where('patient_id', $patient->id)
            ->findOrFail($billing);

        if ((float) ($billingRecord->due_amount ?? 0) > 0) {
            return redirect()->route('backend.patient.portal.payment', ['billing' => $billingRecord->id])->with('error', 'এই bill-এ due আছে। আগে payment complete করুন।');
        }

        $reportItems = ($billingRecord->billItems ?? collect())
            ->filter(function ($item) {
                return in_array((string) ($item->category ?? ''), ['Pathology', 'Radiology'], true)
                    && !empty($item->reported_at);
            })
            ->filter(function ($item) {
                $reportNote = trim((string) ($item->report_note ?? ''));
                $reportRange = trim((string) ($item->report_range ?? ''));
                $reportFile = trim((string) ($item->report_file ?? ''));

                return $reportNote !== '' || $reportRange !== '' || $reportFile !== '';
            })
            ->sortBy([
                fn ($item) => strtolower((string) ($item->category ?? '')),
                fn ($item) => strtolower(trim((string) ($item->item_name ?? ''))),
                fn ($item) => (int) ($item->id ?? 0),
            ])
            ->unique(function ($item) {
                return strtolower(trim((string) ($item->category ?? '')))
                    . '|' . strtolower(trim((string) ($item->item_name ?? '')))
                    . '|' . strtolower(trim((string) ($item->report_note ?? '')))
                    . '|' . strtolower(trim((string) ($item->report_range ?? '')));
            })
            ->values();

        if ($reportItems->isEmpty()) {
            return redirect()->route('backend.patient.portal.dashboard')
                ->with('error', 'এই bill-এর কোনো ready report পাওয়া যায়নি।');
        }

        $groupedReportItems = $reportItems
            ->groupBy(function ($item) {
                $category = trim((string) ($item->category ?? 'Other'));
                return $category !== '' ? $category : 'Other';
            })
            ->map(fn ($group) => $group->values())
            ->all();

        $reportedAt = $reportItems
            ->pluck('reported_at')
            ->filter()
            ->map(function ($dt) {
                try {
                    return \Illuminate\Support\Carbon::parse($dt);
                } catch (\Throwable $e) {
                    return null;
                }
            })
            ->filter()
            ->sort()
            ->last();

        $viewData = [
            'billing' => $billingRecord,
            'patient' => $patient,
            'groupedReportItems' => $groupedReportItems,
            'generatedAt' => now()->timezone('Asia/Dhaka')->format('d-M-Y h:i A'),
            'reportedAt' => $reportedAt ? $reportedAt->format('d-M-Y h:i A') : now()->format('d-M-Y h:i A'),
            'contactNo' => (string) ($billingRecord->patient_mobile ?: ($patient->phone ?? 'N/A')),
            'gender' => (string) ($billingRecord->gender ?: ($patient->gender ?? 'N/A')),
            'doctorName' => (string) ($billingRecord->doctor_name ?? 'N/A'),
        ];

        $safeBillNo = Str::of((string) ($billingRecord->bill_number ?? $billingRecord->id))
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
            ->toString();

        $pdf = Pdf::loadView('patient-portal.report-pdf', $viewData);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'defaultFont' => 'dejavu sans',
            'dpi' => 96,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => false,
        ]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="patient_report_' . $safeBillNo . '.pdf"',
        ]);
    }

    private function exportDashboardCsv(
        Patient $patient,
        string $fromDate,
        string $toDate,
        $appointmentsQuery,
        $billingsQuery,
        $opdVisitsQuery,
        $ipdAdmissionsQuery
    ): StreamedResponse {
        $filename = 'patient_dashboard_' . $patient->id . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($patient, $fromDate, $toDate, $appointmentsQuery, $billingsQuery, $opdVisitsQuery, $ipdAdmissionsQuery) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Patient Dashboard Export']);
            fputcsv($handle, ['Patient ID', $patient->id]);
            fputcsv($handle, ['Patient Name', (string) $patient->name]);
            fputcsv($handle, ['Phone', (string) $patient->phone]);
            fputcsv($handle, ['From Date', $fromDate !== '' ? $fromDate : '-']);
            fputcsv($handle, ['To Date', $toDate !== '' ? $toDate : '-']);
            fputcsv($handle, []);

            fputcsv($handle, ['Recent Appointments']);
            fputcsv($handle, ['ID', 'Date', 'Slot', 'Status', 'Fee']);
            foreach ((clone $appointmentsQuery)->orderByDesc('appoinment_date')->limit(200)->get(['id', 'appoinment_date', 'slot', 'appoinment_status', 'doctor_fee']) as $row) {
                fputcsv($handle, [(string) $row->id, (string) $row->appoinment_date, (string) $row->slot, (string) $row->appoinment_status, (string) $row->doctor_fee]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Recent Bills']);
            fputcsv($handle, ['Bill', 'Total', 'Paid', 'Due', 'Status', 'Created At']);
            foreach ((clone $billingsQuery)->orderByDesc('id')->limit(200)->get(['bill_number', 'total', 'paid_amt', 'due_amount', 'payment_status', 'created_at']) as $row) {
                fputcsv($handle, [(string) $row->bill_number, (string) $row->total, (string) $row->paid_amt, (string) $row->due_amount, (string) $row->payment_status, (string) $row->created_at]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Recent OPD Visits']);
            fputcsv($handle, ['OPD No', 'Problem', 'Symptoms', 'Created At']);
            foreach ((clone $opdVisitsQuery)->orderByDesc('id')->limit(200)->get($this->opdSelectColumns(false)) as $row) {
                fputcsv($handle, [(string) $row->opd_no, (string) $row->problem, (string) $row->symptoms_type, (string) $row->created_at]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Recent IPD Admissions']);
            fputcsv($handle, ['IPD No', 'Discharge Date', 'Status', 'Created At']);
            foreach ((clone $ipdAdmissionsQuery)->orderByDesc('id')->limit(200)->get($this->ipdSelectColumns(false)) as $row) {
                fputcsv($handle, [(string) $row->ipd_no, (string) $row->discharge_date, (string) $row->discharge_status, (string) $row->created_at]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('patient')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('backend.patient.portal.login');
    }

    private function ensurePortalEnabled(): void
    {
        $settings = get_cached_web_setting();

        $rawPatientPanel = $settings ? $settings->getRawOriginal('patient_panel') : null;
        $isExplicitlyDisabled = $rawPatientPanel !== null && (int) $rawPatientPanel === 0;

        if ($isExplicitlyDisabled) {
            abort(403, 'Patient portal is disabled by administrator.');
        }
    }

    private function storageInvoiceImageToDataUri(string $publicStoragePath): string
    {
        if ($publicStoragePath === '') {
            return '';
        }

        $relativePath = Str::after($publicStoragePath, '/storage/');
        if ($relativePath === '') {
            return '';
        }

        $storagePath = storage_path('app/public/' . ltrim($relativePath, '/'));
        if (!is_file($storagePath)) {
            return '';
        }

        $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION) ?: 'png');
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($storagePath));
    }

    private function encodePortalToken(array $payload, $expiresAt): string
    {
        $payload['exp'] = $expiresAt->timestamp;
        return Crypt::encryptString(json_encode($payload));
    }

    private function decodePortalToken(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $exp = (int) ($decoded['exp'] ?? 0);
        if ($exp > 0 && now()->timestamp > $exp) {
            return [];
        }

        return $decoded;
    }

    private function phonesMatch(string $left, string $right): bool
    {
        $leftNormalized = preg_replace('/\D+/', '', trim($left));
        $rightNormalized = preg_replace('/\D+/', '', trim($right));

        if ($leftNormalized === '' || $rightNormalized === '') {
            return false;
        }

        if ($leftNormalized === $rightNormalized) {
            return true;
        }

        $leftLocal = preg_replace('/^88/', '', $leftNormalized);
        $rightLocal = preg_replace('/^88/', '', $rightNormalized);

        return $leftLocal !== '' && $leftLocal === $rightLocal;
    }

    private function opdSelectColumns(bool $withId = true): array
    {
        $columns = [];
        if ($withId) {
            $columns[] = 'id';
        }

        if (Schema::hasColumn('opdpatients', 'opd_no')) {
            $columns[] = 'opd_no';
        } else {
            $columns[] = DB::raw("CONCAT('OPD-', id) as opd_no");
        }

        if (Schema::hasColumn('opdpatients', 'problem')) {
            $columns[] = 'problem';
        } elseif (Schema::hasColumn('opdpatients', 'symptom_title')) {
            $columns[] = DB::raw('symptom_title as problem');
        } elseif (Schema::hasColumn('opdpatients', 'note')) {
            $columns[] = DB::raw('note as problem');
        } else {
            $columns[] = DB::raw("'' as problem");
        }

        if (Schema::hasColumn('opdpatients', 'symptoms_type')) {
            $columns[] = 'symptoms_type';
        } elseif (Schema::hasColumn('opdpatients', 'symptom_type')) {
            $columns[] = DB::raw('symptom_type as symptoms_type');
        } else {
            $columns[] = DB::raw("'' as symptoms_type");
        }

        $columns[] = 'created_at';

        return $columns;
    }

    private function ipdSelectColumns(bool $withId = true): array
    {
        $columns = [];
        if ($withId) {
            $columns[] = 'id';
        }

        if (Schema::hasColumn('ipdpatients', 'ipd_no')) {
            $columns[] = 'ipd_no';
        } else {
            $columns[] = DB::raw("CONCAT('IPD-', id) as ipd_no");
        }

        if (Schema::hasColumn('ipdpatients', 'discharge_date')) {
            $columns[] = 'discharge_date';
        } elseif (Schema::hasColumn('ipdpatients', 'admission_date')) {
            $columns[] = DB::raw('admission_date as discharge_date');
        } else {
            $columns[] = DB::raw('NULL as discharge_date');
        }

        if (Schema::hasColumn('ipdpatients', 'discharge_status')) {
            $columns[] = 'discharge_status';
        } elseif (Schema::hasColumn('ipdpatients', 'status')) {
            $columns[] = DB::raw('status as discharge_status');
        } else {
            $columns[] = DB::raw("'' as discharge_status");
        }

        $columns[] = 'created_at';

        return $columns;
    }
}
