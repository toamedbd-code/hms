<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BillItem;
use App\Models\Billing;
use App\Models\WebSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Milon\Barcode\DNS1D;

class SampleCollectionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:sample-collection');
    }

    public function index(Request $request)
    {
        $allowedCategories = $this->resolveDepartmentCategories();
        $search = trim((string) $request->input('search', ''));

        $datas = Billing::query()
            ->where('status', 'Active')
            ->when($search !== '', function ($query) use ($search, $allowedCategories) {
                $query->where(function ($searchQuery) use ($search, $allowedCategories) {
                    $searchQuery
                        ->where('bill_number', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($patientQuery) use ($search) {
                            $patientQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('billItems', function ($itemQuery) use ($search, $allowedCategories) {
                            $itemQuery
                                ->whereIn('category', $allowedCategories)
                                ->whereNull('sample_collected_at')
                                ->where('item_name', 'like', "%{$search}%");
                        });
                });
            })
            ->whereHas('billItems', function ($query) use ($allowedCategories) {
                $query->whereIn('category', $allowedCategories)
                    ->whereNull('sample_collected_at');
            })
            ->with([
                'patient',
                'billItems' => function ($query) use ($allowedCategories) {
                    $query->whereIn('category', $allowedCategories)
                        ->whereNull('sample_collected_at')
                        ->with('collectedBy');
                },
            ])
            ->orderByDesc('id')
            ->paginate($request->input('numOfData', 10))
            ->withQueryString();

        return Inertia::render('Backend/SampleCollection/Index', [
            'pageTitle' => 'Sample Collection',
            'datas' => $datas,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function collect(Billing $billing)
    {
        $allowedCategories = $this->resolveDepartmentCategories();

        BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereIn('category', $allowedCategories)
            ->whereNull('sample_collected_at')
            ->update([
                'sample_collected_at' => now(),
                'sample_collected_by' => auth('admin')->id(),
            ]);

        return back()->with('success', 'Sample collected successfully.');
    }

    public function barcode(Billing $billing)
    {
        $allowedCategories = $this->resolveDepartmentCategories();
        $settings = WebSetting::query()->first();
        $scale = (float) ($settings?->barcode_scale ?? 2.2);
        $height = (int) ($settings?->barcode_height ?? 52);

        $items = BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereIn('category', $allowedCategories)
            ->whereNull('sample_collected_at')
            ->get();

        $dns1d = new DNS1D();

        $barcodes = $items->map(function ($item) use ($dns1d, $billing, $scale, $height) {
            $code = ($billing->bill_number ?? 'BILL') . '-' . $item->id;
            $barcode = 'data:image/png;base64,' . $dns1d->getBarcodePNG($code, 'C128', $scale, $height);

            return [
                'code' => $code,
                'name' => $item->item_name,
                'category' => $item->category,
                'barcode' => $barcode,
            ];
        });

        return view('backend.sample_collection.barcode', [
            'billing' => $billing,
            'barcodes' => $barcodes,
        ]);
    }

    private function resolveDepartmentCategories(): array
    {
        $departmentName = strtolower(trim((string) data_get(auth('admin')->user(), 'details.department.name', '')));
        $designationName = strtolower(trim((string) data_get(auth('admin')->user(), 'details.designation.name', '')));
        $scopeText = trim($departmentName . ' ' . $designationName);

        if (str_contains($scopeText, 'pathology') || str_contains($scopeText, 'patholog')) {
            return ['Pathology'];
        }

        if (str_contains($scopeText, 'radiology') || str_contains($scopeText, 'radiolog')) {
            return ['Radiology'];
        }

        if (
            str_contains($scopeText, 'ultrasonogram')
            || str_contains($scopeText, 'ultrasonography')
            || str_contains($scopeText, 'usg')
        ) {
            return ['Ultrasonogram', 'Ultrasonography'];
        }

        if (str_contains($scopeText, 'ecg') || str_contains($scopeText, 'e.c.g')) {
            return ['ECG'];
        }

        return ['Pathology', 'Radiology', 'Ultrasonogram', 'Ultrasonography', 'ECG'];
    }
}
