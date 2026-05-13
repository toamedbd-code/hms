<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BillItem;
use App\Models\Billing;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportDeliveryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:report-delivery');
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

        $allowedCategories = ['Pathology', 'Radiology'];

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

        $allowedCategories = ['Pathology', 'Radiology'];

        if ((float) ($billing->due_amount ?? 0) > 0) {
            return back()->with('error', 'Cannot deliver reports while due amount exists.');
        }

        BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereIn('category', $allowedCategories)
            ->whereNotNull('reported_at')
            ->whereNull('delivered_at')
            ->update([
                'delivered_at' => now(),
                'delivered_by' => auth('admin')->id(),
            ]);

        return back()->with('success', 'Reports marked as delivered.');
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
            ->when($status !== '', function ($query) use ($status) {
                if ($status === 'pending') {
                    // No sample collected for any item
                    $query->whereDoesntHave('billItems', function ($q) {
                        $q->whereNotNull('sample_collected_at');
                    });
                } elseif ($status === 'processing') {
                    // Has at least one collected and at least one not yet reported
                    $query->whereHas('billItems', function ($q) {
                        $q->whereNotNull('sample_collected_at');
                    })->whereHas('billItems', function ($q) {
                        $q->whereNull('reported_at');
                    });
                } elseif ($status === 'complete') {
                    // All relevant items reported
                    $query->whereDoesntHave('billItems', function ($q) {
                        $q->whereNull('reported_at');
                    });
                }
            })
            ->with([
                'patient',
                'billItems' => function ($q) use ($allowedCategories) {
                    $q->whereIn('category', $allowedCategories)
                        ->with(['collectedBy', 'reportedBy', 'deliveredBy']);
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

    public function deliver(BillItem $billItem)
    {
        // Prevent delivering individual report for IPD-generated bill items
        if (str_starts_with((string) ($billItem->billing->case_number ?? ''), 'IPD-')) {
            return back()->with('error', 'Cannot deliver report for IPD invoice item.');
        }

        $billItem->delivered_at = now();
        $billItem->delivered_by = auth('admin')->id();
        $billItem->save();

        return back()->with('success', 'Report marked as delivered.');
    }
}
