<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\OpdPatient;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DueCollectController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:billing-due-collect');
    }
    /**
     * 🔹 Due Collect Form
     */
    public function index($id)
    {//
       // dd($id);
        $billing = Billing::findOrFail($id);
        $redirectTo = request()->query('redirect_to');
        $returnTo = (string) request()->query('return_to', '');
        if ($returnTo === '') {
            $previousUrl = url()->previous();
            if ($this->isInternalRedirectUrl($previousUrl)) {
                $returnTo = $previousUrl;
            }
        }

        $vatAmount = round((float) ($billing->vat_amount ?? 0), 2);
        $discountAmount = round((float) ($billing->discount ?? 0), 2);
        $extraDiscountAmount = round((float) ($billing->extra_flat_discount ?? 0), 2);
        $effectiveNetAmount = max(0, (float) ($billing->payable_amount ?? ($billing->total - $discountAmount - $extraDiscountAmount + $vatAmount)));
        $effectiveDueAmount = max(0, $effectiveNetAmount - (float) ($billing->paid_amt ?? 0));

        // safety check
        if ($effectiveDueAmount <= 0) {
            return redirect()
                ->route('backend.billing.list')
                ->with('error', 'No due amount available');
        }

        $submissionToken = (string) Str::uuid();

        return view('backend.due_collect.index', compact('billing', 'redirectTo', 'returnTo', 'submissionToken', 'effectiveNetAmount', 'effectiveDueAmount'));
    }

    /**
     * 🔹 Store Due Payment (FINAL LOGIC)
     */
    public function store(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $billing = Billing::findOrFail($id);
        $collectedAmount = (float) $request->amount;
        $returnTo = (string) $request->input('return_to', '');
        $ipdPatientId = (int) $request->input('ipd_patient_id', 0);
        $submissionToken = (string) $request->input('submission_token', '');
        $cacheKey = 'due_collect.billing.' . $billing->id . '.' . $submissionToken;

        if ($submissionToken !== '' && Cache::has($cacheKey)) {
            return redirect()
                ->back()
                ->withErrors(['amount' => 'This due collection has already been submitted. Please refresh the page and try again.'])
                ->withInput();
        }

        $currentDue = max(0, (float) ($billing->payable_amount ?? 0) - (float) ($billing->paid_amt ?? 0));
        if ($collectedAmount > $currentDue) {
            return redirect()
                ->back()
                ->withErrors(['amount' => 'Collect amount cannot exceed due amount.'])
                ->withInput();
        }

        // save due collection
        DueCollection::create([
            'billing_id'       => $billing->id,
            'collected_amount' => $collectedAmount,
            'collected_at'     => now(),
            'created_by'       => auth('admin')->id(),
        ]);

        // update billing
        $billing->paid_amt += $collectedAmount;
        $billing->due_amount = max(0, $currentDue - $collectedAmount);

        if ($billing->due_amount <= 0) {
            $billing->payment_status = 'Paid';
        } else {
            $billing->payment_status = 'Partial';
        }

        $billing->save();

        ActivityLogService::log(
            'Due Collection',
            'COLLECT',
            'Collected billing due for Invoice ' . ($billing->invoice_number ?: $billing->bill_number),
            [
                'billing_id' => $billing->id,
                'bill_number' => $billing->bill_number,
                'invoice_number' => $billing->invoice_number,
                'collected_amount' => $collectedAmount,
                'remaining_due_amount' => (float) $billing->due_amount,
                'payment_status' => $billing->payment_status,
            ]
        );

        if ($submissionToken !== '') {
            Cache::put($cacheKey, true, now()->addMinutes(30));
        }

        $invoiceNo = $billing->invoice_number ?: $billing->bill_number;
        $message = 'Due collected from Invoice ' . $invoiceNo
            . ' | Collected: ' . number_format($collectedAmount, 2)
            . ' | Remaining Due: ' . number_format((float) $billing->due_amount, 2);

        $redirectUrl = $this->resolvePostCollectionRedirectUrl($returnTo, $ipdPatientId);

        return redirect()
            ->to($redirectUrl)
            ->with('successMessage', $message);
    }

    private function resolvePostCollectionRedirectUrl(?string $returnTo, int $ipdPatientId): string
    {
        if ($this->isDischargeCertificateRedirect($returnTo) && $ipdPatientId > 0) {
            $showUrl = route('backend.ipdpatient.show', $ipdPatientId);
            $showUrl = rtrim($showUrl, '/') . '?open_certificate=1&certificate_route=' . urlencode($returnTo);

            return $showUrl;
        }

        return $this->isInternalRedirectUrl($returnTo)
            ? $returnTo
            : route('backend.billing.list');
    }

    private function isDischargeCertificateRedirect(?string $url): bool
    {
        $url = trim((string) $url);

        return $url !== '' && Str::contains($url, '/discharge-certificate/');
    }

    private function isInternalRedirectUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        if (Str::startsWith($url, '/')) {
            return true;
        }

        return Str::startsWith($url, url('/'));
    }

    public function opdIndex($id)
    {
        $opdPatient = OpdPatient::findOrFail($id);

        if ((float) $opdPatient->balance_amount <= 0) {
            return redirect()
                ->route('backend.billing.list')
                ->with('errorMessage', 'No due amount available for this OPD invoice.');
        }

        $submissionToken = (string) Str::uuid();

        return view('backend.due_collect.opd', compact('opdPatient', 'submissionToken'));
    }

    public function opdStore(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $opdPatient = OpdPatient::findOrFail($id);
        $collectAmount = (float) $request->amount;
        $currentDue = (float) $opdPatient->balance_amount;
        $submissionToken = (string) $request->input('submission_token', '');
        $cacheKey = 'due_collect.opd.' . $opdPatient->id . '.' . $submissionToken;

        if ($submissionToken !== '' && Cache::has($cacheKey)) {
            return redirect()
                ->back()
                ->withErrors(['amount' => 'This due collection has already been submitted. Please refresh the page and try again.'])
                ->withInput();
        }

        if ($collectAmount > $currentDue) {
            return redirect()
                ->back()
                ->withErrors(['amount' => 'Collect amount cannot exceed due amount.'])
                ->withInput();
        }

        $opdPatient->paid_amount = (float) $opdPatient->paid_amount + $collectAmount;
        $opdPatient->balance_amount = max(0, $currentDue - $collectAmount);

        if ((float) $opdPatient->balance_amount <= 0) {
            $opdPatient->balance_amount = 0;
            $opdPatient->payment_status = 'Paid';
        } else {
            $opdPatient->payment_status = 'Partial';
        }

        $opdPatient->save();
        if ($submissionToken !== '') {
            Cache::put($cacheKey, true, now()->addMinutes(30));
        }

        DueCollection::create([
            'billing_id' => null,
            'collected_amount' => $collectAmount,
            'collected_at' => now(),
            'payment_method' => 'opd',
            'note' => 'OPD due collected for opd_patient_id:' . $opdPatient->id,
            'created_by' => auth('admin')->id(),
        ]);

        ActivityLogService::log(
            'OPD Due Collection',
            'COLLECT',
            'Collected OPD due for invoice ' . ($opdPatient->invoice_no ?? ('OPD#' . $opdPatient->id)),
            [
                'opd_patient_id' => $opdPatient->id,
                'invoice_no' => $opdPatient->invoice_no,
                'collected_amount' => $collectAmount,
                'remaining_due_amount' => (float) $opdPatient->balance_amount,
                'payment_status' => $opdPatient->payment_status,
            ]
        );

        return redirect()
            ->route('backend.billing.list')
            ->with('successMessage', 'OPD due collected successfully');
    }
}