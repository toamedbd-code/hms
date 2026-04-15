<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BillItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportDeliveryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:report-delivery');
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $datas = BillItem::query()
            ->whereIn('category', ['Pathology', 'Radiology'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('item_name', 'like', "%{$search}%")
                        ->orWhereHas('billing', function ($billingQuery) use ($search) {
                            $billingQuery
                                ->where('bill_number', 'like', "%{$search}%")
                                ->orWhereHas('patient', function ($patientQuery) use ($search) {
                                    $patientQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->with([
                'billing.patient',
                'collectedBy',
                'reportedBy',
                'deliveredBy',
            ])
            ->orderByDesc('reported_at')
            ->orderByDesc('sample_collected_at')
            ->orderByDesc('id')
            ->paginate($request->input('numOfData', 10))
            ->withQueryString();

        return Inertia::render('Backend/ReportDelivery/Index', [
            'pageTitle' => 'Report Delivery',
            'datas' => $datas,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function send(BillItem $billItem)
    {
        $billItem->sent_at = now();
        $billItem->sent_via = 'manual';
        $billItem->save();

        return back()->with('success', 'Report marked as sent.');
    }

    public function deliver(BillItem $billItem)
    {
        $billItem->delivered_at = now();
        $billItem->delivered_by = auth('admin')->id();
        $billItem->save();

        return back()->with('success', 'Report marked as delivered.');
    }
}
