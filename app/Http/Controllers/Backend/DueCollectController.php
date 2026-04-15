<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\OpdPatient;
use Carbon\Carbon;
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

        // safety check
        if ($billing->due_amount <= 0) {
            return redirect()
                ->route('backend.billing.list')
                ->with('error', 'No due amount available');
        }

        return view('backend.due_collect.index', compact('billing', 'redirectTo', 'returnTo'));
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

        // save due collection
        DueCollection::create([
            'billing_id'       => $billing->id,
            'collected_amount' => $collectedAmount,
            'collected_at'     => now(),
        ]);

        // update billing
        $billing->paid_amt += $collectedAmount;
        $billing->due_amount -= $collectedAmount;

        if ($billing->due_amount <= 0) {
            $billing->payment_status = 'Paid';
            $billing->due_amount = 0;
        } else {
            $billing->payment_status = 'Partial';
        }

        $billing->save();

        $invoiceNo = $billing->invoice_number ?: $billing->bill_number;
        $message = 'Due collected from Invoice ' . $invoiceNo
            . ' | Collected: ' . number_format($collectedAmount, 2)
            . ' | Remaining Due: ' . number_format((float) $billing->due_amount, 2);

        $redirectUrl = $this->isInternalRedirectUrl($returnTo)
            ? $returnTo
            : route('backend.billing.list');

        return redirect()
            ->to($redirectUrl)
            ->with('successMessage', $message);
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

        return view('backend.due_collect.opd', compact('opdPatient'));
    }

    public function opdStore(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $opdPatient = OpdPatient::findOrFail($id);
        $collectAmount = (float) $request->amount;
        $currentDue = (float) $opdPatient->balance_amount;

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

        DueCollection::create([
            'billing_id' => null,
            'collected_amount' => $collectAmount,
            'collected_at' => now(),
            'payment_method' => 'opd',
            'note' => 'OPD due collected for opd_patient_id:' . $opdPatient->id,
            'created_by' => auth('admin')->id(),
        ]);

        return redirect()
            ->route('backend.billing.list')
            ->with('successMessage', 'OPD due collected successfully');
    }
}