<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BillItem;
use App\Models\BillItemParameterResult;
use App\Models\Billing;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportDeliveryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        // Allow users with general report-delivery permission OR reporting permission
        // OR department-specific reporting permissions (pathology, ultrasound, xray) to access delivery actions.
        $this->middleware('permission:reporting|report-delivery|pathology-reporting|ultrasound-reporting|xray-reporting');
    }

    /**
     * Mark all reported bill items of a billing as sent.
     */
    public function sendAll(Billing $billing)
    {
        // Prevent sending reports for IPD-generated billings
        if (str_starts_with((string) ($billing->case_number ?? ''), 'IPD-')) {
            return back()->with('error', 'Cannot send reports for IPD invoices.');
        }

        $allowedCategories = ['Pathology', 'Radiology', 'Ultrasound', 'X-ray', 'ECG'];

        if ((float) ($billing->due_amount ?? 0) > 0) {
            return back()->with('error', 'Cannot send report while due amount exists.');
        }

        BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereIn('category', $allowedCategories)
            ->whereNotNull('reported_at')
            ->whereNull('sent_at')
            ->update([
                'sent_at' => now(),
                'sent_via' => 'manual',
            ]);

        return back()->with('success', 'Reports marked as sent.');
    }

    /**
     * Mark all reported bill items of a billing as delivered.
     */
    public function deliverAll(Billing $billing)
    {
        // Prevent delivering reports for IPD-generated billings
        if (str_starts_with((string) ($billing->case_number ?? ''), 'IPD-')) {
            return back()->with('error', 'Cannot deliver reports for IPD invoices.');
        }

        if ((float) ($billing->due_amount ?? 0) > 0) {
            return back()->with('error', 'Cannot deliver reports while due amount exists.');
        }

        $updated = BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereNotNull('reported_at')
            ->whereNull('delivered_at')
            ->update([
                'delivered_at' => now(),
                'delivered_by' => auth('admin')->id(),
            ]);

        return back()->with('success', $updated > 0
            ? "Reports marked as delivered ({$updated} item(s))."
            : 'No pending reports to deliver.');
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));

        $allowedCategories = ['Pathology', 'Radiology'];

        $datas = Billing::query()
            ->where('status', 'Active')
            // Exclude IPD-generated billings (case_number starting with 'IPD-')
            ->where(function ($q) {
                $q->whereNull('case_number')
                    ->orWhere('case_number', 'not like', 'IPD-%');
            })
            ->whereHas('billItems', function ($q) use ($allowedCategories) {
                $q->whereIn('category', $allowedCategories);
            })
            ->when($search !== '', function ($query) use ($search, $allowedCategories) {
                $query->where(function ($q) use ($search, $allowedCategories) {
                    $q->where('bill_number', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($patientQuery) use ($search) {
                            $patientQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('billItems', function ($itemQuery) use ($search, $allowedCategories) {
                            $itemQuery->whereIn('category', $allowedCategories)
                                ->where('item_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', function ($query) use ($status, $allowedCategories) {
                if ($status === 'pending') {
                    // No sample collected for any relevant (non-disposable) item
                    $query->whereDoesntHave('billItems', function ($q) use ($allowedCategories) {
                        $q->whereIn('category', $allowedCategories)
                            ->where('requires_sample', true)
                            ->whereNotNull('sample_collected_at');
                    });
                } elseif ($status === 'processing') {
                    // Has at least one collected and at least one not yet reported among relevant items
                    $query->whereHas('billItems', function ($q) use ($allowedCategories) {
                        $q->whereIn('category', $allowedCategories)
                            ->where('requires_sample', true)
                            ->whereNotNull('sample_collected_at');
                    })->whereHas('billItems', function ($q) use ($allowedCategories) {
                        $q->whereIn('category', $allowedCategories)
                            ->where('requires_sample', true)
                            ->whereNull('reported_at');
                    });
                } elseif ($status === 'complete') {
                    // All relevant (non-disposable) items reported
                    $query->whereDoesntHave('billItems', function ($q) use ($allowedCategories) {
                        $q->whereIn('category', $allowedCategories)
                            ->where('requires_sample', true)
                            ->whereNull('reported_at');
                    });
                }
            })
            ->with([
                'patient',
                'billItems' => function ($q) use ($allowedCategories) {
                    $q->whereIn('category', $allowedCategories)
                        ->with(['collectedBy', 'reportedBy', 'deliveredBy'])
                        ->withCount('parameterResults');
                },
            ])
            ->orderByDesc('id')
            ->paginate($request->input('numOfData', 10))
            ->withQueryString();

        return Inertia::render('Backend/ReportDelivery/Index', [
            'pageTitle' => 'Report Delivery',
            'datas' => $datas,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'numOfData' => (int) $request->input('numOfData', 10),
            ],
        ]);
    }

    public function send(BillItem $billItem)
    {
        // Prevent sending individual report for IPD-generated bill items
        if (str_starts_with((string) ($billItem->billing->case_number ?? ''), 'IPD-')) {
            return back()->with('error', 'Cannot send report for IPD invoice item.');
        }

        $billItem->sent_at = now();
        $billItem->sent_via = 'manual';
        $billItem->save();

        return back()->with('success', 'Report marked as sent.');
    }

    public function deliver(Request $request, BillItem $billItem)
    {
        $billItem->loadMissing('billing');

        // Prevent delivering individual report for IPD-generated bill items
        if (str_starts_with((string) ($billItem->billing->case_number ?? ''), 'IPD-')) {
            return $this->deliveryError($request, 'Cannot deliver report for IPD invoice item.', 422);
        }

        if ((float) ($billItem->billing->due_amount ?? 0) > 0) {
            return $this->deliveryError($request, 'Cannot deliver report while due amount exists.', 422);
        }

        if (!in_array($billItem->category, ['Pathology', 'Radiology'], true)) {
            return $this->deliveryError($request, 'This report cannot be marked as delivered here.', 422);
        }

        $hasSavedReportResult = trim(str_replace(
            '[[FULL_PAGE]]',
            '',
            strip_tags((string) ($billItem->report_note ?? ''))
        )) !== ''
            || trim((string) ($billItem->report_range ?? '')) !== ''
            || trim((string) ($billItem->report_file ?? '')) !== ''
            || BillItemParameterResult::query()->where('bill_item_id', $billItem->id)->exists();

        if (empty($billItem->reported_at) || !$hasSavedReportResult) {
            return $this->deliveryError($request, 'Cannot deliver a report before its result is saved.', 422);
        }

        $hasUnreportedItem = BillItem::query()
            ->where('billing_id', $billItem->billing_id)
            ->whereIn('category', ['Pathology', 'Radiology'])
            ->where('requires_sample', true)
            ->whereNull('reported_at')
            ->exists();

        if ($hasUnreportedItem) {
            return $this->deliveryError($request, 'Cannot deliver reports while this bill is still processing.', 422);
        }

        if ($billItem->delivered_at) {
            return $request->expectsJson()
                ? response()->json(['delivered_at' => $billItem->delivered_at])
                : back()->with('success', 'Report is already marked as delivered.');
        }

        $billItem->delivered_at = now();
        $billItem->delivered_by = auth('admin')->id();
        $billItem->save();

        if ($request->expectsJson()) {
            return response()->json(['delivered_at' => $billItem->delivered_at]);
        }

        return back()->with('success', 'Report marked as delivered.');
    }

    private function deliveryError(Request $request, string $message, int $status)
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], $status)
            : back()->with('error', $message);
    }
}
