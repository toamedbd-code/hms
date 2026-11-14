<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BillItem;
use App\Models\Billing;
use App\Models\WebSetting;
use App\Models\TestCategory;
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
            // Exclude IPD-generated billings (case_number starting with 'IPD-')
            ->where(function ($q) {
                $q->whereNull('case_number')
                    ->orWhere('case_number', 'not like', 'IPD-%');
            })
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
                                ->where('requires_sample', true)
                                ->whereNull('sample_collected_at')
                                ->where('item_name', 'like', "%{$search}%");
                        });
                });
            })
            ->whereHas('billItems', function ($query) use ($allowedCategories) {
                $query->whereIn('category', $allowedCategories)
                    ->where('requires_sample', true)
                    ->whereNull('sample_collected_at');
            })
            ->with([
                'patient',
                'billItems' => function ($query) use ($allowedCategories) {
                    $query->whereIn('category', $allowedCategories)
                        ->where('requires_sample', true)
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
            'allowedCategories' => $allowedCategories,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function collect(Billing $billing)
    {
        // Prevent sample collection for IPD invoices
        if (str_starts_with((string) ($billing->case_number ?? ''), 'IPD-')) {
            return back()->with('warning', 'Cannot collect samples for IPD invoices.');
        }

        $allowedCategories = $this->resolveDepartmentCategories();

        BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereIn('category', $allowedCategories)
            ->where('requires_sample', true)
            ->whereNull('sample_collected_at')
            ->update([
                'sample_collected_at' => now(),
                'sample_collected_by' => auth('admin')->id(),
            ]);

        return back()->with('success', 'Sample collected successfully.');
    }

    /**
     * Collect a single bill item (sample) via AJAX/post from UI.
     */
    public function collectItem(BillItem $billItem)
    {
        // Prevent collecting individual items for IPD invoices
        if (str_starts_with((string) ($billItem->billing->case_number ?? ''), 'IPD-')) {
            return response()->json(['error' => 'Cannot collect sample for IPD invoice'], 403);
        }

        $allowedCategories = $this->resolveDepartmentCategories();

        if (! in_array($billItem->category, $allowedCategories) || ! $billItem->requires_sample) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($billItem->sample_collected_at) {
            return response()->json(['warning' => 'Already collected'], 200);
        }

        $billItem->update([
            'sample_collected_at' => now(),
            'sample_collected_by' => auth('admin')->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Sample collected.']);
    }

    public function barcode(Billing $billing)
    {
        // No barcodes for IPD-generated billings
        if (str_starts_with((string) ($billing->case_number ?? ''), 'IPD-')) {
            return back()->with('warning', 'No sample barcodes for IPD invoices.');
        }

        $allowedCategories = $this->resolveDepartmentCategories();
        $settings = WebSetting::query()->first();
        $scale = (float) ($settings?->barcode_scale ?? 2.2);
        $height = (int) ($settings?->barcode_height ?? 52);

        $items = BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereIn('category', $allowedCategories)
            ->where('requires_sample', true)
            ->whereNull('sample_collected_at')
            ->get();

        $dns1d = new DNS1D();

        $sampleId = trim((string) $billing->bill_number);
        $barcodes = $items->map(function ($item) use ($dns1d, $sampleId, $scale, $height) {
            $code = $sampleId;
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
        $dynamicCategories = TestCategory::query()
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->all();

        $departmentName = strtolower(trim((string) data_get(auth('admin')->user(), 'details.department.name', '')));
        $designationName = strtolower(trim((string) data_get(auth('admin')->user(), 'details.designation.name', '')));
        $scopeText = trim($departmentName . ' ' . $designationName);

        if (str_contains($scopeText, 'pathology') || str_contains($scopeText, 'patholog')) {
            return $this->dynamicCategoriesMatching(['pathology', 'micro', 'biology'], ['Pathology']);
        }

        if (str_contains($scopeText, 'radiology') || str_contains($scopeText, 'radiolog')) {
            return $this->dynamicCategoriesMatching(['radiology', 'radiolog', 'xray', 'x-ray'], ['Radiology']);
        }

        if (
            str_contains($scopeText, 'ultrasonogram')
            || str_contains($scopeText, 'ultrasonography')
            || str_contains($scopeText, 'usg')
        ) {
            return $this->dynamicCategoriesMatching(['ultrasound', 'ultrasonogram', 'ultrasonography'], ['Ultrasonogram', 'Ultrasonography']);
        }

        if (str_contains($scopeText, 'ecg') || str_contains($scopeText, 'e.c.g')) {
            return $this->dynamicCategoriesMatching(['ecg', 'echo'], ['ECG']);
        }

        return array_values(array_unique(array_merge(
            ['Pathology', 'Radiology', 'Ultrasonogram', 'Ultrasonography', 'ECG'],
            $dynamicCategories
        ), SORT_STRING));
    }

    private function dynamicCategoriesMatching(array $keywords, array $fallback): array
    {
        $categories = TestCategory::query()
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->filter(function ($name) use ($keywords) {
                $normalized = strtolower($name);
                return collect($keywords)->contains(fn ($keyword) => str_contains($normalized, $keyword));
            })
            ->all();

        return array_values(array_unique(array_merge($fallback, $categories), SORT_STRING));
    }
}
