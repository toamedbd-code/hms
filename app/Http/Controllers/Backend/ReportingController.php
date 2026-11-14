<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BillItem;
use App\Models\Billing;
use App\Models\InvoiceDesign;
use App\Models\PathologyTestParameter;
use App\Models\BillItemParameterResult;
use App\Models\MainCategory;
use App\Models\RadiologyTest;
use App\Models\Test;
use App\Models\TestCategory;
use App\Models\WebSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ReportingController extends Controller
{
    /**
     * Shared upload rules for report attachments.
     */
    private function reportFileValidationRules(): array
    {
        return [
            'nullable',
            'file',
            'max:5120',
            // Keep extension check strict; allow legacy DOC/DOCX and common image/report formats.
            'extensions:pdf,jpg,jpeg,png,webp,doc,docx',
            // Some legacy DOC files are detected as octet-stream by browsers/servers.
            'mimetypes:application/pdf,image/jpeg,image/png,image/webp,application/msword,application/vnd.ms-word,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/octet-stream',
        ];
    }

    public function __construct()
    {
        $this->middleware('auth:admin');
        // Allow users with general reporting permission OR specific radiology reporting permissions
        // Allow users with general reporting permission OR specific department reporting permissions
        // Also allow report-delivery permission for report printing/delivery access
        $this->middleware('permission:reporting|report-delivery|pathology-reporting|ultrasound-reporting|xray-reporting');
    }

    public function sessionHeartbeat()
    {
        return response()
            ->json(['csrf_token' => csrf_token()])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Pathology-specific reporting list (only Pathology items).
     */
    public function pathologyIndex(Request $request)
    {
        $allowedCategories = $this->categoryNamesMatching(['pathology', 'micro', 'biology', 'hematology', 'haematology', 'cbc'], ['Pathology', 'Hematology', 'CBC']);
        $billNumber = trim((string) $request->input('bill_number', ''));
        $includeReported = $request->boolean('include_reported');

        $datas = Billing::query()
            ->where('status', 'Active')
            ->where(function ($q) {
                $q->whereNull('case_number')
                    ->orWhere('case_number', 'not like', 'IPD-%');
            })
            ->when($billNumber !== '', function ($query) use ($billNumber) {
                $query->where('bill_number', 'like', '%' . $billNumber . '%');
            })
            ->whereHas('billItems', function ($query) use ($includeReported, $allowedCategories) {
                $query->whereIn('category', $allowedCategories)
                    ->whereNotNull('sample_collected_at');

                if (!$includeReported) {
                    $query->whereNull('reported_at');
                }
            })
            ->with([
                'patient',
                'billItems' => function ($query) use ($includeReported, $allowedCategories) {
                    $query->whereIn('category', $allowedCategories)
                        ->whereNotNull('sample_collected_at')
                        ->with('collectedBy');

                    if (!$includeReported) {
                        $query->whereNull('reported_at');
                    }
                },
            ])
            ->orderByDesc('id')
            ->paginate($request->input('numOfData', 10))
            ->withQueryString();

        return Inertia::render('Backend/Reporting/Index', [
            'pageTitle' => 'Pathology Reporting',
            'department' => 'pathology',
            'datas' => $datas,
            'filters' => [
                'bill_number' => $billNumber,
                'include_reported' => $includeReported,
            ],
        ]);
    }

    /**
     * Ultrasound-specific reporting list (only items categorized as ultrasound/ultrasonogram).
     */
    public function ultrasoundIndex(Request $request)
    {
        // Ultrasound items are sometimes stored under `Radiology` category with names like "USG...".
        $allowedCategories = $this->categoryNamesMatching(['ultrasound', 'ultrasonogram', 'ultrasonography'], ['Ultrasound', 'Ultrasonogram', 'Ultrasonography', 'Radiology']);
        $billNumber = trim((string) $request->input('bill_number', ''));
        $includeReported = $request->boolean('include_reported');

        $datas = Billing::query()
            ->where('status', 'Active')
            ->where(function ($q) {
                $q->whereNull('case_number')
                    ->orWhere('case_number', 'not like', 'IPD-%');
            })
            ->when($billNumber !== '', function ($query) use ($billNumber) {
                $query->where('bill_number', 'like', '%' . $billNumber . '%');
            })
            ->whereHas('billItems', function ($query) use ($includeReported, $allowedCategories) {
                $query->whereIn('category', $allowedCategories)
                    ->whereNotNull('sample_collected_at')
                    ->where(function ($q2) {
                        $q2->where('item_name', 'like', '%usg%')
                            ->orWhere('item_name', 'like', '%ultrasound%')
                            ->orWhere('item_name', 'like', '%ultrasonogram%')
                            ->orWhere('item_name', 'like', '%ultrasonography%');
                    });

                if (!$includeReported) {
                    $query->whereNull('reported_at');
                }
            })
            ->with([
                'patient',
                'billItems' => function ($query) use ($includeReported, $allowedCategories) {
                    $query->whereIn('category', $allowedCategories)
                        ->whereNotNull('sample_collected_at')
                        ->where(function ($q2) {
                            $q2->where('item_name', 'like', '%usg%')
                                ->orWhere('item_name', 'like', '%ultrasound%')
                                ->orWhere('item_name', 'like', '%ultrasonogram%')
                                ->orWhere('item_name', 'like', '%ultrasonography%');
                        })
                        ->with('collectedBy');

                    if (!$includeReported) {
                        $query->whereNull('reported_at');
                    }
                },
            ])
            ->orderByDesc('id')
            ->paginate($request->input('numOfData', 10))
            ->withQueryString();

        return Inertia::render('Backend/Reporting/Index', [
            'pageTitle' => 'Ultrasound Reporting',
            'department' => 'ultrasound',
            'datas' => $datas,
            'filters' => [
                'bill_number' => $billNumber,
                'include_reported' => $includeReported,
            ],
        ]);
    }

    /**
     * X-ray specific reporting list (Radiology items filtered by name containing xray/radiograph).
     */
    public function xrayIndex(Request $request)
    {
        $allowedCategories = $this->categoryNamesMatching(['radiology', 'radiolog', 'xray', 'x-ray'], ['Radiology']);
        $billNumber = trim((string) $request->input('bill_number', ''));
        $includeReported = $request->boolean('include_reported');

        $datas = Billing::query()
            ->where('status', 'Active')
            ->where(function ($q) {
                $q->whereNull('case_number')
                    ->orWhere('case_number', 'not like', 'IPD-%');
            })
            ->when($billNumber !== '', function ($query) use ($billNumber) {
                $query->where('bill_number', 'like', '%' . $billNumber . '%');
            })
            ->whereHas('billItems', function ($query) use ($includeReported, $allowedCategories) {
                // Show all Radiology items that are NOT ultrasound/USG items.
                // This covers test names like "CXR", "PA View", "Chest AP", etc.
                $query->whereIn('category', $allowedCategories)
                    ->whereNotNull('sample_collected_at')
                    ->where(function ($q2) {
                        $q2->where('item_name', 'not like', '%usg%')
                            ->where('item_name', 'not like', '%ultrasound%')
                            ->where('item_name', 'not like', '%ultrasonogram%')
                            ->where('item_name', 'not like', '%ultrasonography%');
                    });

                if (!$includeReported) {
                    $query->whereNull('reported_at');
                }
            })
            ->with([
                'patient',
                'billItems' => function ($query) use ($includeReported, $allowedCategories) {
                    $query->whereIn('category', $allowedCategories)
                        ->whereNotNull('sample_collected_at')
                        ->where(function ($q2) {
                            $q2->where('item_name', 'not like', '%usg%')
                                ->where('item_name', 'not like', '%ultrasound%')
                                ->where('item_name', 'not like', '%ultrasonogram%')
                                ->where('item_name', 'not like', '%ultrasonography%');
                        })
                        ->with('collectedBy');

                    if (!$includeReported) {
                        $query->whereNull('reported_at');
                    }
                },
            ])
            ->orderByDesc('id')
            ->paginate($request->input('numOfData', 10))
            ->withQueryString();

        return Inertia::render('Backend/Reporting/Index', [
            'pageTitle' => 'X-ray Reporting',
            'department' => 'xray',
            'datas' => $datas,
            'filters' => [
                'bill_number' => $billNumber,
                'include_reported' => $includeReported,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $requestedDept = trim((string) $request->input('department', ''));
        $billNumber = trim((string) $request->input('bill_number', ''));
        $includeReported = $request->boolean('include_reported');

        $query = Billing::query()
            ->where('status', 'Active')
            // Exclude IPD-generated billings (case_number starting with 'IPD-')
            ->where(function ($q) {
                $q->whereNull('case_number')
                    ->orWhere('case_number', 'not like', 'IPD-%');
            })
            ->when($billNumber !== '', function ($q) use ($billNumber) {
                $q->where('bill_number', 'like', '%' . $billNumber . '%');
            });

        // Apply department-specific filters when requested (e.g., ultrasound)
        if ($requestedDept === 'ultrasound') {
            $allowedCategories = ['Ultrasound', 'Ultrasonogram', 'Ultrasonography', 'Radiology'];

            $query->whereHas('billItems', function ($q) use ($includeReported, $allowedCategories) {
                $q->whereIn('category', $allowedCategories)
                    ->whereNotNull('sample_collected_at')
                    ->where(function ($q2) {
                        $q2->where('item_name', 'like', '%usg%')
                            ->orWhere('item_name', 'like', '%ultrasound%')
                            ->orWhere('item_name', 'like', '%ultrasonogram%')
                            ->orWhere('item_name', 'like', '%ultrasonography%');
                    });

                if (!$includeReported) {
                    $q->whereNull('reported_at');
                }
            });

            $datas = $query->with([
                'patient',
                'billItems' => function ($q) use ($includeReported, $allowedCategories) {
                    $q->whereIn('category', $allowedCategories)
                        ->whereNotNull('sample_collected_at')
                        ->where(function ($q2) {
                            $q2->where('item_name', 'like', '%usg%')
                                ->orWhere('item_name', 'like', '%ultrasound%')
                                ->orWhere('item_name', 'like', '%ultrasonogram%')
                                ->orWhere('item_name', 'like', '%ultrasonography%');
                        })
                        ->with('collectedBy');

                    if (!$includeReported) {
                        $q->whereNull('reported_at');
                    }
                },
            ])->orderByDesc('id')
              ->paginate($request->input('numOfData', 10))
              ->withQueryString();

        } else {
            // Default behavior: determine allowed categories from user scope
            $allowedCategories = $this->resolveDepartmentCategories();

            $datas = $query->whereHas('billItems', function ($q) use ($includeReported, $allowedCategories) {
                $q->whereIn('category', $allowedCategories)
                    ->whereNotNull('sample_collected_at');

                if (!$includeReported) {
                    $q->whereNull('reported_at');
                }
            })
            ->with([
                'patient',
                'billItems' => function ($q) use ($includeReported, $allowedCategories) {
                    $q->whereIn('category', $allowedCategories)
                        ->whereNotNull('sample_collected_at')
                        ->with('collectedBy');

                    if (!$includeReported) {
                        $q->whereNull('reported_at');
                    }
                },
            ])
            ->orderByDesc('id')
            ->paginate($request->input('numOfData', 10))
            ->withQueryString();
        }

        return Inertia::render('Backend/Reporting/Index', [
            'pageTitle' => 'Reporting',
            'datas' => $datas,
            'filters' => [
                'bill_number' => $billNumber,
                'include_reported' => $includeReported,
            ],
        ]);
    }

    public function search(Request $request)
    {
        $requestedDept = trim((string) $request->input('department', ''));
        $billNumber = trim((string) $request->input('bill_number', ''));
        $includeReported = $request->boolean('include_reported');

        if ($billNumber === '') {
            return back()->with('warning', 'Please enter a bill number.');
        }

        // Determine allowed categories and optional name filters based on requested department
        if ($requestedDept === 'ultrasound') {
            $allowedCategories = ['Ultrasound', 'Ultrasonogram', 'Ultrasonography', 'Radiology'];
            $nameKeywords = ['usg', 'ultrasound', 'ultrasonogram', 'ultrasonography'];
        } elseif ($requestedDept === 'xray') {
            $allowedCategories = ['Radiology'];
            $nameKeywords = ['xray', 'x-ray', 'x ray', 'radiograph', 'radiography'];
        } elseif ($requestedDept === 'pathology') {
            $allowedCategories = ['Pathology', 'Hematology', 'CBC', 'Haematology'];
            $nameKeywords = [];
        } else {
            $allowedCategories = $this->resolveDepartmentCategories();
            $nameKeywords = [];
        }

        $query = Billing::query()
            ->where('status', 'Active')
            // Exclude IPD-generated billings from reporting searches
            ->where(function ($q) {
                $q->whereNull('case_number')
                    ->orWhere('case_number', 'not like', 'IPD-%');
            })
            ->whereHas('billItems', function ($q) use ($allowedCategories, $nameKeywords, $includeReported) {
                $q->whereIn('category', $allowedCategories)
                    ->whereNotNull('sample_collected_at');

                if (!empty($nameKeywords)) {
                    $q->where(function ($q2) use ($nameKeywords) {
                        foreach ($nameKeywords as $kw) {
                            $q2->orWhere('item_name', 'like', '%' . $kw . '%');
                        }
                    });
                }

                if (!$includeReported) {
                    $q->whereNull('reported_at');
                }
            });

        // Fetch billing and eagerly load only the filtered billItems so department detection is accurate
        $billing = (clone $query)
            ->with(['billItems' => function ($q) use ($allowedCategories, $nameKeywords, $includeReported) {
                $q->whereIn('category', $allowedCategories)
                    ->whereNotNull('sample_collected_at');

                if (!empty($nameKeywords)) {
                    $q->where(function ($q2) use ($nameKeywords) {
                        foreach ($nameKeywords as $kw) {
                            $q2->orWhere('item_name', 'like', '%' . $kw . '%');
                        }
                    });
                }

                if (!$includeReported) {
                    $q->whereNull('reported_at');
                }
            }])
            ->where('bill_number', $billNumber)
            ->first();

        if (!$billing) {
            $billing = (clone $query)
                ->with(['billItems' => function ($q) use ($allowedCategories, $nameKeywords, $includeReported) {
                    $q->whereIn('category', $allowedCategories)
                        ->whereNotNull('sample_collected_at');

                    if (!empty($nameKeywords)) {
                        $q->where(function ($q2) use ($nameKeywords) {
                            foreach ($nameKeywords as $kw) {
                                $q2->orWhere('item_name', 'like', '%' . $kw . '%');
                            }
                        });
                    }

                    if (!$includeReported) {
                        $q->whereNull('reported_at');
                    }
                }])
                ->where('bill_number', 'like', '%' . $billNumber . '%')
                ->first();
        }

        if (!$billing) {
            return back()->with('warning', 'No pending report found for this bill number.');
        }

        // Determine department based on the filtered billing items (fallback if not provided)
        $department = $requestedDept ?: null;
        if (!$department) {
            $names = $billing->billItems->pluck('item_name')->map(fn($s) => strtolower((string) $s))->all();
            $cats = $billing->billItems->pluck('category')->map(fn($s) => strtolower((string) $s))->all();
            $nameText = implode(' ', $names) . ' ' . implode(' ', $cats);

            $hasPathologyLikeItem = collect($billing->billItems)->some(function ($item) {
                $category = strtolower((string) ($item->category ?? ''));
                $name = strtolower((string) ($item->item_name ?? ''));
                return str_contains($category, 'pathology')
                    || str_contains($category, 'hematology')
                    || str_contains($category, 'haematology')
                    || str_contains($category, 'cbc')
                    || str_contains($name, 'cbc')
                    || str_contains($name, 'hematology')
                    || str_contains($name, 'haematology');
            });

            if ($hasPathologyLikeItem) {
                $department = 'pathology';
            } elseif (str_contains($nameText, 'ultrasound') || str_contains($nameText, 'ultrason') || str_contains($nameText, 'usg')) {
                $department = 'ultrasound';
            } elseif (str_contains($nameText, 'xray') || str_contains($nameText, 'x-ray') || str_contains($nameText, 'radiograph')) {
                $department = 'xray';
            }
        }

        return redirect()->route('backend.reporting.edit', ['billing' => $billing->id, 'department' => $department]);
    }

    public function edit(Billing $billing)
    {
        // Prevent reporting operations on IPD-generated billings
        if (str_starts_with((string) ($billing->case_number ?? ''), 'IPD-')) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'Reporting is not available for IPD invoices.');
        }

        // Allow overriding the department via query param (pathology|ultrasound|xray)
        $requestedDept = trim((string) request('department', ''));
        $includeReported = request()->boolean('include_reported');

        // If department not explicitly provided, try to auto-detect it from the billing's items
        if ($requestedDept === '') {
            try {
                $names = \App\Models\BillItem::query()
                    ->where('billing_id', $billing->id)
                    ->pluck('item_name')
                    ->map(fn($s) => strtolower((string) $s))->all();

                $cats = \App\Models\BillItem::query()
                    ->where('billing_id', $billing->id)
                    ->pluck('category')
                    ->map(fn($s) => strtolower((string) $s))->all();

                $nameText = implode(' ', $names) . ' ' . implode(' ', $cats);
                $hasPathologyLikeItem = collect(\App\Models\BillItem::query()->where('billing_id', $billing->id)->get())->some(function ($item) {
                    $category = strtolower((string) ($item->category ?? ''));
                    $name = strtolower((string) ($item->item_name ?? ''));
                    return str_contains($category, 'pathology')
                        || str_contains($category, 'hematology')
                        || str_contains($category, 'haematology')
                        || str_contains($category, 'cbc')
                        || str_contains($name, 'cbc')
                        || str_contains($name, 'hematology')
                        || str_contains($name, 'haematology');
                });

                if ($hasPathologyLikeItem) {
                    $requestedDept = 'pathology';
                } elseif (str_contains($nameText, 'ultrasound') || str_contains($nameText, 'ultrason') || str_contains($nameText, 'usg')) {
                    $requestedDept = 'ultrasound';
                } elseif (str_contains($nameText, 'xray') || str_contains($nameText, 'x-ray') || str_contains($nameText, 'radiograph')) {
                    $requestedDept = 'xray';
                }
            } catch (\Throwable $_) {
                // ignore detection failures and fall back to explicit categories
            }
        }

        if ($requestedDept === 'ultrasound') {
            // Ultrasound tests may be stored under Radiology category with USG names.
            $allowedCategories = ['Ultrasound', 'Ultrasonogram', 'Ultrasonography', 'Radiology'];
        } elseif ($requestedDept === 'xray') {
            $allowedCategories = ['Radiology'];
        } elseif ($requestedDept === 'pathology') {
            $allowedCategories = ['Pathology', 'Hematology', 'CBC', 'Haematology'];
        } else {
            $allowedCategories = $this->resolveDepartmentCategories();
        }

        // Eager-load patient and fetch bill items explicitly so department filtering is enforced reliably
        $billing->load('patient');

        // Build a helper that applies the department-specific name filter to a query builder.
        // This is reused for both the reported/pending check and the main data fetch so the
        // two queries are always consistent with each other.
        $applyDeptNameFilter = function (\Illuminate\Database\Eloquent\Builder $q) use ($requestedDept): void {
            if ($requestedDept === 'ultrasound') {
                $q->where(function ($q2) {
                    $q2->where('item_name', 'like', '%usg%')
                        ->orWhere('item_name', 'like', '%ultrasound%')
                        ->orWhere('item_name', 'like', '%ultrasonogram%')
                        ->orWhere('item_name', 'like', '%ultrasonography%')
                        ->orWhere('category', 'like', '%ultrasound%')
                        ->orWhere('category', 'like', '%ultrasonogram%')
                        ->orWhere('category', 'like', '%ultrasonography%');
                });
            } elseif ($requestedDept === 'xray') {
                // Include all Radiology items that are NOT ultrasound/USG so that tests
                // named "CXR", "PA View", "Chest AP" etc. are accessible without
                // requiring explicit "xray" or "radiograph" keywords in the name.
                $q->where(function ($q2) {
                    $q2->where('item_name', 'not like', '%usg%')
                        ->where('item_name', 'not like', '%ultrasound%')
                        ->where('item_name', 'not like', '%ultrasonogram%')
                        ->where('item_name', 'not like', '%ultrasonography%');
                });
            }
        };

        // Calculate reported/pending status scoped ONLY to the current department's
        // items so that items from other departments (e.g. already-reported USG while
        // pathology is still pending) do not incorrectly block the form.
        $deptScopedItemsForCheck = \App\Models\BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereNotNull('sample_collected_at')
            ->whereIn('category', $allowedCategories);
        $applyDeptNameFilter($deptScopedItemsForCheck);
        $deptScopedItems = $deptScopedItemsForCheck->get();

        $hasReportedItems = $deptScopedItems->contains(fn ($item) => !empty($item->reported_at));
        $hasPendingItems  = $deptScopedItems->contains(fn ($item) => empty($item->reported_at));
        $showOnlyReportedItems = $hasReportedItems && $hasPendingItems;

        $rawItemsQuery = \App\Models\BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereNotNull('sample_collected_at');

        if ($showOnlyReportedItems && $includeReported) {
            $rawItemsQuery->whereNotNull('reported_at');
        } elseif (!$includeReported) {
            $rawItemsQuery->whereNull('reported_at');
        }

        // apply category filter then department name filter
        $rawItemsQuery->whereIn('category', $allowedCategories);
        $applyDeptNameFilter($rawItemsQuery);

        $rawItems = $rawItemsQuery->with('collectedBy')->get();

        $pathologyItemIds = $rawItems
            ->where('category', 'Pathology')
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values();

        $normalRangeMap = $this->buildNormalRangeMap($pathologyItemIds->all());

        $items = $rawItems->map(function ($item) use ($normalRangeMap) {
            $item->report_file_url = $item->report_file
                ? route('backend.reporting.item.file', $item->id)
                : null;

            // Always try stored parameter range first (covers all categories, not just Pathology).
            $defaultRange = !empty($item->item_id) ? ($normalRangeMap[$item->item_id] ?? null) : null;

            // Fallback: suggest a range by test name only when no stored range exists.
            if (empty($defaultRange)) {
                $defaultRange = $this->suggestNormalRangeByTestName($item);
            }

            // Attach parameter definitions for pathology-like tests, including CBC/Hematology results.
            if ($this->isPathologyLikeCategory((string) ($item->category ?? '')) || $this->looksLikeCbcTest($item)) {
                $iname = trim(strtolower((string) ($item->item_name ?? '')));
                $test = null;
                $tname = '';
                if (!empty($item->item_id)) {
                    $test = Test::find($item->item_id);
                    $tname = trim(strtolower($test?->test_name ?? $test?->test_short_name ?? ''));
                    $storedFunction = trim((string) ($test?->report_function ?? ''));
                    $item->report_function = $storedFunction !== ''
                        ? $storedFunction
                        : $this->autoDetectTestFunction((string) ($item->item_name ?? ''), (string) ($item->category ?? ''));
                }

                $isUrineName = (str_contains($iname, 'urine') || preg_match('/\br\/?e\b/i', $iname) || preg_match('/\bm\/?e\b/i', $iname));
                $isUrineTest = ($tname !== '' && (str_contains($tname, 'urine') || preg_match('/\br\/?e\b/i', $tname) || preg_match('/\bm\/?e\b/i', $tname)));

                $pathologyLike = $this->isPathologyLikeCategory((string) ($item->category ?? '')) || $this->looksLikeCbcTest($item);
                if ($pathologyLike) {
                    $params = PathologyTestParameter::query()
                        ->where('pathology_test_id', $item->item_id)
                    ->where('pathology_test_id', $item->item_id)
                    ->with(['pathologyUnit:id,name', 'testParameter:id,name'])
                    ->orderBy('id')
                    ->get()
                    ->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'name' => trim((string) ($p->name ?: data_get($p, 'testParameter.name') ?: '')),
                            'reference_from' => trim((string) ($p->reference_from ?? '')),
                            'reference_to' => trim((string) ($p->reference_to ?? '')),
                            'unit' => trim((string) (data_get($p, 'pathologyUnit.name') ?? '')),
                        ];
                    })->values();

                // If no explicit pathology_test_parameters exist, try Test->test_parameters (legacy JSON)
                if ($params->isEmpty()) {
                    try {
                        $test = Test::find($item->item_id);
                        if ($test && !empty($test->test_parameters)) {
                            $ids = json_decode($test->test_parameters, true);
                            if (is_array($ids) && count($ids) > 0) {
                                $alt = \App\Models\PathologyParameter::query()
                                    ->whereIn('id', $ids)
                                    ->with('pathologyUnit')
                                    ->get()
                                    ->map(function ($p) {
                                        return [
                                            'id' => $p->id,
                                            'name' => trim((string) ($p->name ?: data_get($p, 'testParameter.name') ?: '')),
                                            'reference_from' => trim((string) ($p->referance_from ?? $p->reference_from ?? '')),
                                            'reference_to' => trim((string) ($p->referance_to ?? $p->reference_to ?? '')),
                                            'unit' => trim((string) ($p->pathologyUnit->name ?? '')),
                                        ];
                                    })->values();

                                if ($alt->isNotEmpty()) {
                                    $params = $alt;
                                }
                            }
                        }
                    } catch (\Throwable $_) {
                        // ignore fallback failures
                    }
                }

                // Auto-build the standard parameter format for known profile tests.
                // Tests outside these profiles keep the normal single result field.
                if ($params->isEmpty()) {
                    $template = $this->parameterTemplateForTest($iname . ' ' . $tname);
                    if (!empty($template)) {
                        $params = collect($template);
                    }
                }

                // CBC/Hematology tests should always use the standard CBC template rather than a generic
                // single-row placeholder that may exist in legacy pathology mappings.
                if ($this->looksLikeCbcTest($item)) {
                    $template = $this->parameterTemplateForTest('cbc');
                    if (!empty($template)) {
                        $nameSet = $params->map(fn ($p) => strtolower((string) ($p['name'] ?? '')))->filter()->values();
                        $hasOnlyGenericCbcRow = $params->count() <= 1 || $nameSet->every(fn ($value) => str_contains($value, 'cbc') || str_contains($value, 'complete blood count') || str_contains($value, 'hematology') || str_contains($value, 'haematology'));

                        if ($params->isEmpty() || $hasOnlyGenericCbcRow) {
                            $params = collect($template);
                        }
                    }
                }

                // If still empty, synthesize a common Urine R/E parameter set for urine-related tests.
                // CBC/Hematology items should also receive their default parameter table even when no saved
                // PathologyTestParameter rows exist for the specific test.
                if ($params->isEmpty()) {
                    try {
                        $test = Test::find($item->item_id);
                        $tname = trim(strtolower($test?->test_name ?? $test?->test_short_name ?? ''));

                        $iname = trim(strtolower((string) ($item->item_name ?? '')));
                        $isUrineName = (str_contains($iname, 'urine') || str_contains($iname, 'r/e') || str_contains($iname, 'm/e') || str_contains($iname, 'r e'));

                        if (($tname !== '' && str_contains($tname, 'urine')) || $isUrineName) {
                            $synth = [
                                // Physical
                                'Colour', 'Appearance', 'Sediment', 'Specific gravity',
                                // Chemical
                                'Reaction', 'Phosphate', 'Albumin', 'Sugar', 'Bile Salt', 'Bile Pigment', 'Ketone body',
                                // Microscopic
                                'Pus cell', 'Epithelial cell', 'RBC', 'RBC Cast', 'Bacteria', 'Hyaline Cast',
                                // Crystals (as individual parameters)
                                'Cal-oxalate', 'Triple phosphate', 'Uric Acid', 'Amorphous Phosphate'
                            ];

                            $gen = collect($synth)->map(function ($name, $i) {
                                return [
                                    'id' => 'gen:' . $name,
                                    'name' => $name,
                                    'reference_from' => '',
                                    'reference_to' => '',
                                    'unit' => '',
                                    'generated' => true,
                                ];
                            });

                            if ($gen->isNotEmpty()) {
                                $params = $gen;
                            }
                        }

                        if ($params->isEmpty() && $this->looksLikeCbcTest($item)) {
                            $params = collect($this->parameterTemplateForTest('cbc'));
                        }
                    } catch (\Throwable $_) {
                        // ignore
                    }
                }

                $params = $params->map(function (array $parameter) {
                    if (empty($parameter['id'])) {
                        $parameter['id'] = 'gen:' . trim((string) ($parameter['name'] ?? ''));
                    }

                    return $parameter;
                });

                if ($this->looksLikeCbcTest($item)) {
                    $normalizeCbcParameterName = fn ($name) => strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $name)));
                    $cbcParameterAliases = [
                        'haemoglobin' => 'hemoglobin',
                        'haemoglobinhb' => 'hemoglobin',
                        'hemoglobinhb' => 'hemoglobin',
                        'hb' => 'hemoglobin',
                        'hgb' => 'hemoglobin',
                        'esrwestergren' => 'esr',
                        'wbccount' => 'totalwbccount',
                        'whitebloodcellcount' => 'totalwbccount',
                        'neutrophil' => 'neutrophils',
                        'lymphocyte' => 'lymphocytes',
                        'monocyte' => 'monocytes',
                        'eosinophil' => 'eosinophils',
                        'basophil' => 'basophils',
                        'atypicalcell' => 'blastatypical',
                        'blastatypicalcell' => 'blastatypical',
                        'blastatypicalpercent' => 'blastatypical',
                        'blastyatypical' => 'blastatypical',
                        'blastyatypicalcell' => 'blastatypical',
                        'blastyatypicalpercent' => 'blastatypical',
                        'myelocytespercent' => 'myelocytes',
                        'totalcireosinophil' => 'totalcireosinophils',
                        'totalcirculatingeosinophils' => 'totalcireosinophils',
                        'rbccount' => 'totalrbccount',
                        'hematocrit' => 'hctpcv',
                        'plateletcount' => 'totalplateletcount',
                        'plcr' => 'plcr',
                        'bleedingtime' => 'bt',
                        'clottingtime' => 'ct',
                    ];
                    $canonicalizeCbcParameterName = function ($name) use ($normalizeCbcParameterName, $cbcParameterAliases) {
                        $normalized = $normalizeCbcParameterName($name);
                        return $cbcParameterAliases[$normalized] ?? $normalized;
                    };
                    $existingNames = $params
                        ->map(fn ($parameter) => $canonicalizeCbcParameterName($parameter['name'] ?? ''))
                        ->all();
                    foreach ($this->parameterTemplateForTest('cbc') as $parameter) {
                        $name = trim((string) ($parameter['name'] ?? ''));
                        $normalizedName = $canonicalizeCbcParameterName($name);
                        if ($name === '' || in_array($normalizedName, $existingNames, true)) {
                            continue;
                        }

                        $params->push(array_merge($parameter, [
                            'id' => 'gen:' . $name,
                            'generated' => true,
                        ]));
                        $existingNames[] = $normalizedName;
                    }

                    $existingParameterNames = $params
                        ->map(fn ($parameter) => strtolower(trim((string) ($parameter['name'] ?? ''))))
                        ->filter()
                        ->all();

                    BillItemParameterResult::query()
                        ->where('bill_item_id', $item->id)
                        ->whereNull('pathology_test_parameter_id')
                        ->whereNotNull('name')
                        ->get(['name', 'unit'])
                        ->each(function (BillItemParameterResult $result) use ($params, &$existingParameterNames) {
                            $name = trim((string) $result->name);
                            $normalizedName = strtolower($name);
                            if ($name === '' || in_array($normalizedName, $existingParameterNames, true)) {
                                return;
                            }

                            $params->push([
                                'id' => 'gen:' . $name,
                                'name' => $name,
                                'reference_from' => '',
                                'reference_to' => '',
                                'unit' => trim((string) $result->unit),
                                'generated' => true,
                            ]);
                            $existingParameterNames[] = $normalizedName;
                        });
                }

                // Preserve the original parameter sequence exactly as defined in the template.
                // CBC/Hematology reports must follow the serial layout from the reference sheet,
                // not the heuristic grouping used for urine/other pathology tests.
                if ($this->looksLikeCbcTest($item)) {
                    $cbcParameterMap = [
                        'Hemoglobin' => ['Hemoglobin'],
                        'ESR' => ['ESR'],
                        'Total Count' => ['Total WBC Count'],
                        'Differential Count' => [
                            'Neutrophils', 'Lymphocytes', 'Monocytes', 'Eosinophils', 'Basophils',
                            'Blast/Atypical %', 'Myelocytes %', 'Total Cir. Eosinophils',
                        ],
                        'RBC Panel' => ['Total RBC Count', 'HCT/PCV', 'MCV', 'MCH', 'MCHC', 'RDW-CV', 'RDW-SD'],
                        'Platelet Panel' => ['Platelet Count', 'MPV', 'PCT', 'PDW', 'P-LCR'],
                        'Clotting Time' => ['BT', 'CT'],
                        'Blood Film' => ['Blood Film'],
                    ];
                    $normalizeCbcParameterName = fn ($name) => strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $name)));
                    $cbcParameterAliases = [
                        'haemoglobin' => 'hemoglobin',
                        'haemoglobinhb' => 'hemoglobin',
                        'hemoglobinhb' => 'hemoglobin',
                        'hb' => 'hemoglobin',
                        'hgb' => 'hemoglobin',
                        'esrwestergren' => 'esr',
                        'wbccount' => 'totalwbccount',
                        'whitebloodcellcount' => 'totalwbccount',
                        'neutrophil' => 'neutrophils',
                        'lymphocyte' => 'lymphocytes',
                        'monocyte' => 'monocytes',
                        'eosinophil' => 'eosinophils',
                        'basophil' => 'basophils',
                        'atypicalcell' => 'blastatypical',
                        'blastatypicalcell' => 'blastatypical',
                        'blastatypicalpercent' => 'blastatypical',
                        'blastyatypical' => 'blastatypical',
                        'blastyatypicalcell' => 'blastatypical',
                        'blastyatypicalpercent' => 'blastatypical',
                        'myelocytespercent' => 'myelocytes',
                        'totalcireosinophil' => 'totalcireosinophils',
                        'totalcirculatingeosinophils' => 'totalcireosinophils',
                        'rbccount' => 'totalrbccount',
                        'hematocrit' => 'hctpcv',
                        'plateletcount' => 'totalplateletcount',
                        'bleedingtime' => 'bt',
                        'clottingtime' => 'ct',
                    ];

                    $orderedCbcGroups = [];
                    foreach ($cbcParameterMap as $title => $names) {
                        $normalizedNames = collect($names)->map(function ($name) use ($normalizeCbcParameterName, $cbcParameterAliases) {
                            $normalized = $normalizeCbcParameterName($name);
                            return $cbcParameterAliases[$normalized] ?? $normalized;
                        })->all();
                        $groupParams = [];
                        foreach ($normalizedNames as $normalizedName) {
                            $parameter = $params->first(function ($parameter) use ($normalizedName, $normalizeCbcParameterName, $cbcParameterAliases) {
                                $normalized = $normalizeCbcParameterName($parameter['name'] ?? '');
                                $normalized = $cbcParameterAliases[$normalized] ?? $normalized;
                                return $normalized === $normalizedName;
                            });
                            if ($parameter !== null) {
                                $groupParams[] = $parameter;
                            }
                        }

                        if (!empty($groupParams)) {
                            $orderedCbcGroups[] = [
                                'title' => $title,
                                'parameters' => $groupParams,
                            ];
                        }
                    }

                    $item->parameter_groups = $orderedCbcGroups ?: [[
                        'title' => 'Other',
                        'parameters' => $params->values()->all(),
                    ]];
                } else {
                    // Group parameters into common sections (Physical / Chemical / Microscopic)
                    // Check Microscopic keywords before Chemical so crystal subtypes map correctly
                    $groupRules = [
                        'Physical Examination' => ['colour', 'color', 'appearance', 'sediment', 'specific gravity', 'sg'],
                        'Microscopic Examination' => ['pus', 'pus cell', 'pus cells', 'epithelial', 'epithelial cell', 'epithelial cells', 'rbc', 'rbc cast', 'rbc casts', 'bacteria', 'hyaline', 'crystal', 'casts', 'oxalate', 'triple', 'uric', 'cal-oxalate', 'amorphous'],
                        'Chemical Examination' => ['reaction', 'phosphate', 'albumin', 'sugar', 'bile', 'bile salt', 'bile pigment', 'ketone', 'ph', 'bilirubin'],
                    ];

                    $grouped = [];
                    foreach (array_keys($groupRules) as $g) {
                        $grouped[$g] = [];
                    }
                    $grouped['Other'] = [];

                    foreach ($params as $p) {
                        $placed = false;
                        $lname = mb_strtolower($p['name'] ?? '');
                        foreach ($groupRules as $g => $keywords) {
                            foreach ($keywords as $kw) {
                                if (mb_stripos($lname, $kw) !== false) {
                                    $grouped[$g][] = $p;
                                    $placed = true;
                                    break 2;
                                }
                            }
                        }

                        if (!$placed) {
                            $grouped['Other'][] = $p;
                        }
                    }

                    $item->parameter_groups = collect($grouped)
                        ->filter(fn ($rows) => !empty($rows))
                        ->map(function ($rows, $title) {
                            return [
                                'title' => $title,
                                'parameters' => array_values($rows),
                            ];
                        })->values()->toArray();
                }

                // Keep legacy flat array for backward compatibility
                    $item->parameters = $params->toArray();

                    // load any previously saved parameter results for this bill item
                    $parameterIds = $params->pluck('id')->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->all();
                    $generatedParameterNames = $params
                        ->filter(fn ($parameter) => str_starts_with((string) ($parameter['id'] ?? ''), 'gen:'))
                        ->map(fn ($parameter) => trim((string) ($parameter['name'] ?? '')))
                        ->filter()
                        ->all();

                    $item->saved_parameter_values = BillItemParameterResult::query()
                        ->where('bill_item_id', $item->id)
                        ->get()
                        ->filter(function (BillItemParameterResult $result) use ($parameterIds, $generatedParameterNames) {
                            if ($result->pathology_test_parameter_id !== null) {
                                return in_array((int) $result->pathology_test_parameter_id, $parameterIds, true);
                            }

                            return in_array((string) $result->name, $generatedParameterNames, true);
                        })
                        ->mapWithKeys(function (BillItemParameterResult $result) {
                            $key = $result->pathology_test_parameter_id !== null
                                ? (string) $result->pathology_test_parameter_id
                                : 'gen:' . (string) $result->name;

                            return [$key => $result->value];
                        })
                        ->all();
                } else {
                    // not a urine/R&E test: ensure no parameter groups/parameters are exposed
                    $item->parameters = [];
                    $item->parameter_groups = [];
                    $item->saved_parameter_values = [];
                }
            }

            $item->default_report_range = $defaultRange;

            // Ensure every item has a report_function label (auto-detect if not already set).
            if (empty($item->report_function)) {
                $item->report_function = $this->autoDetectTestFunction(
                    (string) ($item->item_name ?? ''),
                    (string) ($item->category ?? '')
                );
            }

            // Resolve main_category_name from the test record for display in report titles.
            if (empty($item->main_category_name)) {
                $mcName = '';
                if (!empty($item->item_id)) {
                    $mcId = Test::query()->whereKey($item->item_id)->value('main_category_id');
                    if ($mcId) {
                        $mcName = trim((string) (MainCategory::query()->whereKey($mcId)->value('name') ?? ''));
                    }
                }
                $item->main_category_name = $mcName;
            }

            return $item;
        });

        // Enforce department-level filtering again on the final items collection
        if ($requestedDept === 'ultrasound') {
            $items = $items->filter(function ($it) {
                $name = strtolower((string) ($it->item_name ?? ''));
                $cat = strtolower((string) ($it->category ?? ''));
                $keywords = ['usg', 'ultrasound', 'ultrasonogram', 'ultrasonography'];
                $matchesKeyword = false;
                foreach ($keywords as $kw) {
                    if (str_contains($name, $kw) || str_contains($cat, $kw)) {
                        $matchesKeyword = true;
                        break;
                    }
                }
                return $matchesKeyword;
            })->values();
        } elseif ($requestedDept === 'xray') {
            // Show all Radiology items that are NOT ultrasound so that tests named
            // "CXR", "PA View", "Chest AP", etc. are included even without "xray" in the name.
            $items = $items->filter(function ($it) {
                $name = strtolower((string) ($it->item_name ?? ''));
                $usgKeywords = ['usg', 'ultrasound', 'ultrasonogram', 'ultrasonography'];
                foreach ($usgKeywords as $kw) {
                    if (str_contains($name, $kw)) {
                        return false;
                    }
                }
                return true;
            })->values();
        }

        if ($items->isEmpty()) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'No pending tests for reporting.');
        }

        return Inertia::render('Backend/Reporting/Form', [
            'pageTitle' => 'Report Entry',
            'billing' => $billing,
            'billItems' => $items,
            'department' => $requestedDept,
        ]);
    }

    public function viewFile(BillItem $billItem)
    {
        $allowedCategories = $this->resolveDepartmentCategories();

        if (!in_array($billItem->category, $allowedCategories, true)) {
            abort(403);
        }

        $relativePath = trim((string) $billItem->report_file);
        if ($relativePath === '') {
            abort(404);
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!Storage::disk('public')->exists($normalizedPath)) {
            abort(404);
        }

        $fullPath = storage_path('app/public/' . $normalizedPath);
        return response()->file($fullPath);
    }

    public function importStoredFileText(BillItem $billItem)
    {
        $allowedCategories = $this->resolveDepartmentCategories();

        if (!in_array($billItem->category, $allowedCategories, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid report item.',
            ], 403);
        }

        $relativePath = trim((string) $billItem->report_file);
        if ($relativePath === '') {
            return response()->json([
                'ok' => false,
                'message' => 'No file attached for this item.',
            ], 404);
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!Storage::disk('public')->exists($normalizedPath)) {
            return response()->json([
                'ok' => false,
                'message' => 'Attached file not found.',
            ], 404);
        }

        $fullPath = storage_path('app/public/' . $normalizedPath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        [$text, $meta] = $this->extractReportFileText($fullPath, $extension);
        $clean = $this->normalizeImportedText($text);

        if ($clean === '') {
            return response()->json([
                'ok' => false,
                'message' => $meta['message'] ?? 'Text could not be extracted from this file.',
                'source' => $meta['source'] ?? $extension,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'text' => $clean,
            'source' => $meta['source'] ?? $extension,
            'warning' => $meta['warning'] ?? null,
        ]);
    }

    private function extractReportFileText(string $fullPath, string $extension): array
    {
        if (in_array($extension, ['txt', 'md', 'csv', 'log'], true)) {
            return [
                (string) @file_get_contents($fullPath),
                ['source' => $extension],
            ];
        }

        if (in_array($extension, ['html', 'htm'], true)) {
            $html = (string) @file_get_contents($fullPath);
            return [
                html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ['source' => 'html'],
            ];
        }

        if ($extension === 'docx') {
            return $this->extractDocxText($fullPath);
        }

        if ($extension === 'doc') {
            return $this->extractDocTextWithConverters($fullPath);
        }

        if ($extension === 'pdf') {
            return $this->extractPdfTextWithConverter($fullPath);
        }

        return [
            '',
            ['source' => $extension, 'message' => 'Unsupported file type for text import.'],
        ];
    }

    private function extractDocxText(string $fullPath): array
    {
        if (!class_exists('ZipArchive')) {
            return ['', ['source' => 'docx', 'message' => 'ZIP extension is not available on the server.']];
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($fullPath);
        if ($opened !== true) {
            return ['', ['source' => 'docx', 'message' => 'DOCX file could not be opened.']];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!is_string($xml) || trim($xml) === '') {
            return ['', ['source' => 'docx', 'message' => 'DOCX content is empty.']];
        }

        $text = preg_replace('/<w:p[^>]*>/', "\n", $xml);
        $text = preg_replace('/<[^>]+>/', '', (string) $text);
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return [(string) $text, ['source' => 'docx']];
    }

    private function extractDocTextWithConverters(string $fullPath): array
    {
        $attempts = [
            ['antiword', 'antiword :file'],
            ['catdoc', 'catdoc :file'],
        ];

        foreach ($attempts as [$tool, $template]) {
            if (!$this->isShellToolAvailable($tool)) {
                continue;
            }

            $command = str_replace(':file', escapeshellarg($fullPath), $template);
            $output = $this->runShellCommand($command);
            if ($output !== '') {
                return [$output, ['source' => $tool]];
            }
        }

        if ($this->isShellToolAvailable('soffice')) {
            $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'hms_doc_import_' . Str::random(10);
            @mkdir($tmpDir, 0777, true);

            $command = 'soffice --headless --convert-to txt:Text --outdir '
                . escapeshellarg($tmpDir) . ' ' . escapeshellarg($fullPath);

            $this->runShellCommand($command);

            $target = $tmpDir . DIRECTORY_SEPARATOR . pathinfo($fullPath, PATHINFO_FILENAME) . '.txt';
            if (file_exists($target)) {
                $text = (string) @file_get_contents($target);
                @unlink($target);
                @rmdir($tmpDir);

                if (trim($text) !== '') {
                    return [$text, ['source' => 'soffice']];
                }
            }

            @rmdir($tmpDir);
        }

        return [
            '',
            [
                'source' => 'doc',
                'message' => 'Server DOC converter was not found. Install antiword/catdoc/LibreOffice or use DOCX.',
            ],
        ];
    }

    private function extractPdfTextWithConverter(string $fullPath): array
    {
        if (!$this->isShellToolAvailable('pdftotext')) {
            return [
                '',
                ['source' => 'pdf', 'message' => 'PDF text converter is not installed on the server.'],
            ];
        }

        $command = 'pdftotext -layout ' . escapeshellarg($fullPath) . ' -';
        $output = $this->runShellCommand($command);

        if ($output === '') {
            return ['', ['source' => 'pdf', 'message' => 'No readable text found in PDF.']];
        }

        return [$output, ['source' => 'pdftotext']];
    }

    private function isShellToolAvailable(string $tool): bool
    {
        $command = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
            ? 'where ' . escapeshellarg($tool)
            : 'command -v ' . escapeshellarg($tool);

        $output = $this->runShellCommand($command, true);
        return $output !== '';
    }

    private function runShellCommand(string $command, bool $suppressErrors = false): string
    {
        $redirect = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
            ? ($suppressErrors ? ' 2>NUL' : ' 2>&1')
            : ($suppressErrors ? ' 2>/dev/null' : ' 2>&1');

        $output = @shell_exec($command . $redirect);
        return trim((string) $output);
    }

    private function normalizeImportedText(string $text): string
    {
        $normalized = preg_replace('/\x{FEFF}/u', '', (string) $text);
        $normalized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', (string) $normalized);
        $normalized = preg_replace('/[^\S\r\n]{2,}/u', ' ', (string) $normalized);
        $normalized = preg_replace('/\n{3,}/u', "\n\n", (string) $normalized);

        return trim((string) $normalized);
    }

    private function splitTrailingUnit(string $value, ?string $preferredUnit = null): array
    {
        $raw = trim((string) $value);
        $fallbackUnit = trim((string) ($preferredUnit ?? ''));

        if ($raw === '') {
            return ['', $fallbackUnit];
        }

        $unit = $fallbackUnit;
        $clean = $raw;

        if ($raw !== '') {
            if (preg_match('/^(?<number>.*?)(?:\s+)?(?<unit>[\p{L}µμ%\/]+(?:\s*[\p{L}µμ%\/]+)*)\s*$/u', $raw, $m)) {
                $candidateUnit = trim((string) ($m['unit'] ?? ''));
                $candidateNumber = trim((string) ($m['number'] ?? ''));

                if ($candidateUnit !== '' && preg_match('/[0-9]/u', $candidateNumber) === 1) {
                    $clean = $candidateNumber;
                    $unit = $candidateUnit;
                }
            }
        }

        if ($unit === '' && $fallbackUnit !== '') {
            $unit = $fallbackUnit;
        }

        if ($unit !== '' && $clean !== '') {
            $clean = preg_replace('/\s*' . preg_quote($unit, '/') . '$/iu', '', $clean);
            $clean = trim((string) $clean);
        }

        return [$clean, $unit];
    }

    private function buildNormalRangeMap(array $testIds): array
    {
        if (empty($testIds)) {
            return [];
        }

        $parameters = PathologyTestParameter::query()
            ->whereIn('pathology_test_id', $testIds)
            ->with('pathologyUnit:id,name')
            ->orderBy('id')
            ->get();

        return $parameters
            ->groupBy('pathology_test_id')
            ->map(function ($rows) {
                $formatted = $rows->map(function ($row) {
                    $from = trim((string) ($row->reference_from ?? ''));
                    $to = trim((string) ($row->reference_to ?? ''));
                    $unit = trim((string) ($row->pathologyUnit->name ?? ''));

                    if ($from !== '' && $to !== '') {
                        $range = $from . ' - ' . $to;
                    } elseif ($from !== '') {
                        $range = $from;
                    } elseif ($to !== '') {
                        $range = $to;
                    } else {
                        $range = '';
                    }

                    $value = trim($range . ($unit !== '' ? ' ' . $unit : ''));
                    return $value !== '' ? $value : null;
                })->filter()->values();

                return $formatted->isNotEmpty() ? $formatted->implode(' | ') : null;
            })
            ->filter()
            ->toArray();
    }

    public function update(Request $request, Billing $billing)
    {
        // Disallow saving reports for IPD-generated billings
        if (str_starts_with((string) ($billing->case_number ?? ''), 'IPD-')) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'Reporting is not available for IPD invoices.');
        }

        $requestedDept = trim((string) $request->input('department', ''));
        if ($requestedDept === 'ultrasound') {
            $allowedCategories = ['Ultrasound', 'Ultrasonogram', 'Ultrasonography'];
        } elseif ($requestedDept === 'xray') {
            $allowedCategories = ['Radiology'];
        } elseif ($requestedDept === 'pathology') {
            $allowedCategories = ['Pathology'];
        } else {
            $allowedCategories = $this->resolveDepartmentCategories();
        }
        $validated = $request->validate([
            'report_notes' => ['array'],
            'report_notes.*' => ['nullable', 'string'],
            'report_files' => ['array'],
            'report_files.*' => $this->reportFileValidationRules(),
        ]);

        $items = BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereIn('category', $allowedCategories)
            ->whereNotNull('sample_collected_at')
            ->whereNull('reported_at');

        if ($requestedDept === 'xray') {
            $items->where(function ($q) {
                $q->where('item_name', 'like', '%xray%')
                    ->orWhere('item_name', 'like', '%x-ray%')
                    ->orWhere('item_name', 'like', '%radiograph%')
                    ->orWhere('item_name', 'like', '%x ray%');
            });
        }

        if ($requestedDept === 'ultrasound') {
            $items->where(function ($q) {
                $q->where('item_name', 'like', '%usg%')
                    ->orWhere('item_name', 'like', '%ultrasound%')
                    ->orWhere('item_name', 'like', '%ultrasonogram%')
                    ->orWhere('item_name', 'like', '%ultrasonography%');
            });
        }

        $items = $items->get();

        foreach ($items as $item) {
            $note = $validated['report_notes'][$item->id] ?? null;
            $file = $request->file("report_files.{$item->id}");
            $resultText = trim(str_replace('[[FULL_PAGE]]', '', strip_tags((string) $note)));
            if ($resultText === '') {
                continue;
            }

            if ($file) {
                $path = $file->store('reports', 'public');
                $item->report_file = $path;
            }

            $item->report_note = $note;
            $item->reported_at = now();
            $item->reported_by = auth('admin')->id();
            $item->save();
        }

        // Redirect back to the department-specific reporting list when possible
        if ($requestedDept === 'ultrasound') {
            return redirect()->route('backend.reporting.ultrasound')
                ->with([
                    'successMessage' => 'Report saved successfully.',
                    'success' => 'Report saved successfully.',
                ]);
        }

        if ($requestedDept === 'xray') {
            return redirect()->route('backend.reporting.xray')
                ->with([
                    'successMessage' => 'Report saved successfully.',
                    'success' => 'Report saved successfully.',
                ]);
        }

        if ($requestedDept === 'pathology') {
            return redirect()->route('backend.reporting.pathology')
                ->with([
                    'successMessage' => 'Report saved successfully.',
                    'success' => 'Report saved successfully.',
                ]);
        }

        return redirect()->route('backend.reporting.index')
            ->with([
                'successMessage' => 'Report saved successfully.',
                'success' => 'Report saved successfully.',
            ]);
    }

    public function updateItem(Request $request, BillItem $billItem)
    {
        // Disallow reporting actions for IPD-generated bill items
        if (str_starts_with((string) ($billItem->billing->case_number ?? ''), 'IPD-')) {
            return back()->with('warning', 'Reporting is not available for IPD invoices.');
        }

        $requestedDept = trim((string) $request->input('department', ''));
        if ($requestedDept === 'ultrasound') {
            $allowedCategories = ['Ultrasound', 'Ultrasonogram', 'Ultrasonography'];
        } elseif ($requestedDept === 'xray') {
            $allowedCategories = ['Radiology'];
        } elseif ($requestedDept === 'pathology') {
            $allowedCategories = ['Pathology'];
        } else {
            $allowedCategories = $this->resolveDepartmentCategories();
        }
        $request->validate([
            'report_note' => ['nullable', 'string'],
            'report_range' => ['nullable', 'string', 'max:255'],
            'report_file' => $this->reportFileValidationRules(),
            'parameter_values' => ['array'],
            'parameter_values.*' => ['nullable', 'string'],
        ]);

        $reportNote = trim((string) $request->input('report_note', ''));
        $reportNote = trim(str_replace('[[FULL_PAGE]]', '', strip_tags($reportNote)));
        $reportNote = trim((string) preg_replace('/\[\[CBC_HISTOGRAM:(?:SHOW|HIDE)\]\]/i', '', $reportNote));
        $hasParameterValues = collect($request->input('parameter_values', []))
            ->contains(fn ($value) => trim((string) $value) !== '');
        if (
            $reportNote === ''
            && !$hasParameterValues
        ) {
            $message = 'Enter and save a result before printing this report.';
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => ['report_note' => [$message]],
                ], 422);
            }

            return back()->withErrors(['report_note' => $message])->withInput();
        }

        // Validate department context more strictly: allow explicit ultrasound names
        $isAllowedByCategory = in_array($billItem->category, $allowedCategories, true);
        $nameLc = strtolower((string) ($billItem->item_name ?? ''));

        $isUltrasoundByName = str_contains($nameLc, 'usg') || str_contains($nameLc, 'ultrasound') || str_contains($nameLc, 'ultrasonogram') || str_contains($nameLc, 'ultrasonography');
        $isXrayByName = str_contains($nameLc, 'xray') || str_contains($nameLc, 'x-ray') || str_contains($nameLc, 'radiograph') || str_contains($nameLc, 'x ray');

        if ($requestedDept === 'ultrasound') {
            if (!($isAllowedByCategory || $isUltrasoundByName)) {
                return back()->with('warning', 'Invalid report item for Ultrasound.');
            }
        } elseif ($requestedDept === 'xray') {
            if (!($isAllowedByCategory && $isXrayByName)) {
                return back()->with('warning', 'Invalid report item for X-ray.');
            }
        } else {
            if (!$isAllowedByCategory) {
                return back()->with('warning', 'Invalid report item.');
            }
        }

        if (empty($billItem->sample_collected_at)) {
            return back()->with('warning', 'Sample not collected yet.');
        }

        $file = $request->file('report_file');

        $storedReportPath = $file ? $file->store('reports', 'public') : null;
        $parameterValues = $request->input('parameter_values', []);
        $reportRange = $request->input('report_range');
        $hasReportRange = $request->exists('report_range');

        DB::transaction(function () use ($billItem, $request, $storedReportPath, $parameterValues, $reportRange, $hasReportRange) {
            if ($storedReportPath) {
                $billItem->report_file = $storedReportPath;
            }

            $billItem->report_note = $request->input('report_note');
            $billItem->report_range = $reportRange;
            if (empty($billItem->reported_at)) {
                $billItem->reported_at = now();
                $billItem->reported_by = auth('admin')->id();
            }
            $billItem->save();

            if ($hasReportRange && $billItem->category === 'Pathology' && !empty($billItem->item_id)) {
                $this->syncPathologyItemNormalRanges((int) $billItem->item_id, (string) ($reportRange ?? ''));
            }

            if (is_array($parameterValues)) {
                $generatedUnits = BillItemParameterResult::query()
                    ->where('bill_item_id', $billItem->id)
                    ->whereNull('pathology_test_parameter_id')
                    ->whereNotNull('name')
                    ->pluck('unit', 'name');
                BillItemParameterResult::where('bill_item_id', $billItem->id)->delete();
                foreach ($parameterValues as $paramId => $val) {
                    $val = trim((string) ($val ?? ''));
                    if ($val === '') continue;

                    $genName = null;
                    $paramModel = null;

                    if (is_string($paramId) && str_starts_with($paramId, 'gen:')) {
                        $genName = trim(substr($paramId, 4));
                    } else {
                        $paramModel = PathologyTestParameter::with('pathologyUnit', 'testParameter')->find($paramId);
                    }

                    BillItemParameterResult::create([
                        'bill_item_id' => $billItem->id,
                        'pathology_test_parameter_id' => $paramModel ? $paramModel->id : null,
                        'name' => $paramModel ? trim((string) ($paramModel->name ?? data_get($paramModel, 'testParameter.name') ?? '')) : ($genName ?: null),
                        'value' => $val,
                        'unit' => $paramModel
                            ? (trim((string) data_get($paramModel, 'pathologyUnit.name') ?? '') ?: null)
                            : ($genName ? ($generatedUnits->get($genName) ?: null) : null),
                    ]);
                }
            }
        });
        // If this is an XHR/Inertia visit, return JSON so the client can
        // show a toast and update UI without performing a full redirect.
        $billingId = $billItem->billing_id ?? ($billItem->billing?->id ?? null);
        $remaining = 0;
        if ($billingId) {
            $remaining = BillItem::query()->where('billing_id', $billingId)->whereNull('reported_at')->count();
        }

        if ($request->header('X-Inertia') || $request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'successMessage' => 'Report saved successfully.',
                'removedItemId' => $billItem->id,
                'remainingCount' => $remaining,
            ]);
        }

        // Otherwise redirect back to the billing edit page for the same department so UI remains scoped.
        if ($billingId) {
            $routeParams = ['billing' => $billingId];
            if ($requestedDept !== '') $routeParams['department'] = $requestedDept;
            return redirect()->route('backend.reporting.edit', $routeParams)
                ->with([
                    'successMessage' => 'Report saved successfully.',
                    'success' => 'Report saved successfully.',
                ]);
        }

        return redirect()->route('backend.reporting.index')
            ->with([
                'successMessage' => 'Report saved successfully.',
                'success' => 'Report saved successfully.',
            ]);
    }

    private function syncPathologyItemNormalRanges(int $testId, string $reportRange): void
    {
        $test = Test::query()->find($testId);
        $parameters = PathologyTestParameter::query()
            ->where('pathology_test_id', $testId)
            ->with('pathologyUnit:id,name')
            ->orderBy('id')
            ->get();

        if ($parameters->isNotEmpty()) {
            $ranges = $this->splitReportRanges($reportRange, $parameters->count());
            if ($ranges === null) {
                return;
            }

            foreach ($parameters->values() as $index => $parameter) {
                [$from, $to] = $this->parseReportNormalRange(
                    $ranges[$index],
                    trim((string) ($parameter->pathologyUnit?->name ?? ''))
                );
                $parameter->reference_from = $from;
                $parameter->reference_to = $to;
                $parameter->save();
            }

            return;
        }

        if (!$test) {
            return;
        }

        $legacyParameters = $test->test_parameters;
        if (is_string($legacyParameters)) {
            $legacyParameters = json_decode($legacyParameters, true);
        }
        if (!is_array($legacyParameters) || $legacyParameters === []) {
            return;
        }

        $ranges = $this->splitReportRanges($reportRange, count($legacyParameters));
        if ($ranges === null) {
            return;
        }

        $legacyParameters = array_values($legacyParameters);
        foreach ($legacyParameters as $index => &$parameter) {
            if (!is_array($parameter)) {
                continue;
            }

            [$from, $to] = $this->parseReportNormalRange($ranges[$index], '');
            $fromKey = array_key_exists('reference_from', $parameter) ? 'reference_from' : 'referance_from';
            $toKey = array_key_exists('reference_to', $parameter) ? 'reference_to' : 'referance_to';
            $parameter[$fromKey] = $from;
            $parameter[$toKey] = $to;
        }
        unset($parameter);

        $test->test_parameters = json_encode($legacyParameters, JSON_UNESCAPED_UNICODE);
        $test->save();
    }

    private function splitReportRanges(string $reportRange, int $parameterCount): ?array
    {
        if ($parameterCount < 1) {
            return null;
        }

        if (trim($reportRange) === '') {
            return array_fill(0, $parameterCount, '');
        }

        $ranges = preg_split('/\s*\|\s*/u', trim($reportRange)) ?: [];
        if (count($ranges) === $parameterCount) {
            return $ranges;
        }

        return $parameterCount === 1 ? [$reportRange] : null;
    }

    private function parseReportNormalRange(string $range, string $unit): array
    {
        $range = trim($range);
        if ($range === '') {
            return [null, null];
        }

        if ($unit !== '') {
            $range = trim((string) preg_replace('/\s*' . preg_quote($unit, '/') . '\s*$/iu', '', $range));
        }

        $normalizedRange = trim((string) preg_replace('/[–—]/u', '-', $range));
        if (preg_match('/^([+-]?\d+(?:[.,]\d+)?)\s*(?:-|to)\s*([+-]?\d+(?:[.,]\d+)?)$/iu', $normalizedRange, $matches)) {
            return [trim($matches[1]), trim($matches[2])];
        }

        return [$normalizedRange !== '' ? $normalizedRange : null, null];
    }

    public function print(BillItem $billItem, bool $fromPatientPortal = false)
    {
        // Debug trace: record entry to help diagnose blank/500 print pages.
        try {
            Log::info('ReportingController::print called', ['bill_item_id' => $billItem->id ?? null, 'category' => $billItem->category ?? null]);
        } catch (\Throwable $e) {
            // ignore logging failures
        }

        // Disallow printing reports for IPD-generated bill items
        if (str_starts_with((string) ($billItem->billing->case_number ?? ''), 'IPD-')) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'Reporting/print not available for IPD invoices.');
        }

        $portalPatient = $fromPatientPortal ? auth('patient')->user() : null;
        $isPatientPortalPrint = $portalPatient
            && (int) ($billItem->billing?->patient_id ?? 0) === (int) $portalPatient->id
            && (float) ($billItem->billing?->due_amount ?? 0) <= 0
            && !Billing::query()
                ->where('patient_id', $portalPatient->id)
                ->where('due_amount', '>', 0)
                ->exists()
            && in_array((string) $billItem->category, ['Pathology', 'Radiology'], true)
            && !empty($billItem->reported_at)
            && (
                trim((string) ($billItem->report_note ?? '')) !== ''
                || trim((string) ($billItem->report_range ?? '')) !== ''
                || trim((string) ($billItem->report_file ?? '')) !== ''
            );

        $hasSavedParameterResults = BillItemParameterResult::query()
            ->where('bill_item_id', $billItem->id)
            ->exists();
        $hasSavedReportResult = trim(str_replace(
            '[[FULL_PAGE]]',
            '',
            strip_tags((string) ($billItem->report_note ?? ''))
        )) !== ''
            || $hasSavedParameterResults;

        if (!$isPatientPortalPrint && (empty($billItem->reported_at) || !$hasSavedReportResult)) {
            abort(404);
        }

        $allowedCategories = $this->resolveDepartmentCategories();

        // If the item category is not within the user's resolved department scope,
        // allow printing when the user explicitly has a matching department permission
        // (e.g., `ultrasound-reporting`, `xray-reporting`, `pathology-reporting`),
        // or when they have the global `reporting`/`report-delivery` permission.
        $user = auth('admin')->user();
        $categoryAllowedByScope = $isPatientPortalPrint
            || in_array($billItem->category, $allowedCategories, true);

        $explicitAllow = false;
        try {
            if ($user) {
                if ($user->can('reporting') || $user->can('report-delivery')) {
                    $explicitAllow = true;
                }

                // Pathology explicit permission
                if (!$explicitAllow && $user->can('pathology-reporting') && $billItem->category === 'Pathology') {
                    $explicitAllow = true;
                }

                // Ultrasound permission: allow if user has ultrasound-reporting and item looks like USG
                if (!$explicitAllow && $user->can('ultrasound-reporting')) {
                    $reportTitleTmp = $this->resolveReportTitle($billItem);
                    if ($this->isUltrasonogramBillItem($billItem, $reportTitleTmp)) {
                        $explicitAllow = true;
                    }
                }

                // X-ray permission: allow when user has xray-reporting and item name/title contains xray/radiograph
                if (!$explicitAllow && $user->can('xray-reporting')) {
                    $txt = strtolower(trim((string) ($billItem->item_name ?? '') . ' ' . ($billItem->category ?? '')));
                    if (str_contains($txt, 'xray') || str_contains($txt, 'x-ray') || str_contains($txt, 'radiograph') || str_contains($txt, 'radiography')) {
                        $explicitAllow = true;
                    }
                }
            }
        } catch (\Throwable $_) {
            // ignore permission check failures and fall back to scope check
        }

        if (! $categoryAllowedByScope && ! $explicitAllow) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'Invalid report item.');
        }

        // Disable debugbar for print rendering to avoid injected JS errors
        if (app()->bound('debugbar')) {
            try {
                app('debugbar')->disable();
            } catch (\Throwable $e) {
                // ignore if debugbar cannot be disabled
            }
        }

        $billItem->load('billing.patient', 'collectedBy', 'reportedBy.details.designation');

        $billing = $billItem->billing;
        $patient = $billing?->patient;
        if ($patient) {
            $patientName = trim((string) ($patient->name ?? trim((($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '')))));
            if ($patientName === '') {
                $patientName = 'N/A';
            }
        } else {
            $patientName = 'N/A';
        }

        $settings = WebSetting::query()
            ->where('status', 'Active')
            ->orderByDesc('id')
            ->first();
        if (!$settings) {
            $settings = WebSetting::query()->orderByDesc('id')->first();
        }

        $headerHtml = trim((string) ($settings?->report_header_html ?? ''));
        $footerHtml = trim((string) ($settings?->report_footer_html ?? ''));

        $attendanceOptions = [];
        $rawOptions = $settings?->attendance_device_options;

        if (is_array($rawOptions)) {
            $attendanceOptions = $rawOptions;
        } elseif (is_string($rawOptions) && trim($rawOptions) !== '') {
            $decodedOptions = json_decode($rawOptions, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decodedOptions)) {
                    $attendanceOptions = $decodedOptions;
                } elseif (is_string($decodedOptions) && trim($decodedOptions) !== '') {
                    $decodedTwice = json_decode($decodedOptions, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedTwice)) {
                        $attendanceOptions = $decodedTwice;
                    }
                }
            }
        }

        $signatureSettings = data_get($attendanceOptions, 'reporting.signature', []);
        if (!is_array($signatureSettings)) {
            $signatureSettings = [];
        }

        $identitySettings = data_get($attendanceOptions, 'reporting.identity', []);
        if (!is_array($identitySettings)) {
            $identitySettings = [];
        }

        $reportTitle = $this->resolveReportTitle($billItem);
        $reportingDepartment = $this->resolveReportingDepartment($billItem, $reportTitle);
        $departmentSettings = $reportingDepartment
            ? data_get($attendanceOptions, "reporting.departments.{$reportingDepartment}", [])
            : [];
        $departmentSettings = is_array($departmentSettings) ? $departmentSettings : [];

        $layoutSettings = data_get($attendanceOptions, 'reporting.layout', []);
        if (!is_array($layoutSettings)) {
            $layoutSettings = [];
        }
        $departmentLayoutSettings = data_get($departmentSettings, 'layout', []);
        if (is_array($departmentLayoutSettings)) {
            $layoutSettings = array_replace($layoutSettings, $departmentLayoutSettings);
        }
        $departmentSignatureSettings = data_get($departmentSettings, 'signature', []);
        if (is_array($departmentSignatureSettings)) {
            $signatureSettings = array_replace($signatureSettings, $departmentSignatureSettings);
        }

        $signatureMarginTop = max((int) ($signatureSettings['margin_top'] ?? 160), 0);
        $signatureMarginLeft = max((int) ($signatureSettings['margin_left'] ?? 96), 0);
        $cbcSignatureMarginTop = max(-500, min(500, (int) data_get($attendanceOptions, 'reporting.cbc.signature.margin_top', 14)));
        $pageMarginTop = max((int) ($layoutSettings['page_margin_top'] ?? 0), 0);
        $pageMarginBottom = max((int) ($layoutSettings['page_margin_bottom'] ?? 0), 0);
        $pageMarginLeft = max((int) ($layoutSettings['page_margin_left'] ?? 48), 0);
        $pageMarginRight = max((int) ($layoutSettings['page_margin_right'] ?? 48), 0);

        // header/footer heights (pixels). Defaults chosen to keep previous look:
        // default header ~115px (≈1.2in), footer default 70px.
        $reportHeaderHeight = max((int) ($layoutSettings['header_height'] ?? 115), 0);
        $reportFooterHeight = max((int) ($layoutSettings['footer_height'] ?? 70), 0);
        $reportFooterContentOffset = max(-50, min(50, (int) ($layoutSettings['footer_content_offset'] ?? 0)));
        $reportFontSizeAdjustment = max(0, min(10, (int) ($layoutSettings['font_size_adjustment'] ?? 0)));
        $reportHeaderHeight = max((int) ($layoutSettings['header_height'] ?? 115), 0); // px

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', 'billing')->first();
        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }
        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

        $fullPageInstitutionPadEnabled = array_key_exists('full_page_institution_pad', $departmentSettings)
            ? (bool) $departmentSettings['full_page_institution_pad']
            : (bool) data_get($attendanceOptions, 'reporting.full_page_institution_pad', false);
        $fullPageInstitutionPadImage = $fullPageInstitutionPadEnabled
            ? $this->resolvePublicStorageImageDataUri((string) (InvoiceDesign::reportFullPagePad()?->full_page_pad_path ?? ''))
            : '';
        $fullPageInstitutionPadContentOffset = max(0, min(100, (int) ($layoutSettings['full_page_pad_content_offset'] ?? 0)));

        // Final header/footer algorithm:
        // Header: invoice header image > report header html > empty
        // Footer: invoice footer image > invoice footer content > report footer html > empty
        $headerImageBase64 = $this->resolvePublicStorageImageDataUri((string) ($invoiceDesign?->header_photo_path ?? ''));
        $footerImageBase64 = $this->resolvePublicStorageImageDataUri((string) ($invoiceDesign?->footer_photo_path ?? ''));
        $footerContent = trim((string) ($invoiceDesign?->footer_content ?? ''));

        $hasHeader = $headerImageBase64 !== '' || $headerHtml !== '';
        $hasFooter = $footerImageBase64 !== '' || $footerContent !== '' || $footerHtml !== '';

        // respect admin option(s) to show/hide header & footer for reporting
        // Backwards-compatible: support either a single `show_header_footer` boolean
        // or separate `show_header` / `show_footer` booleans in the reporting settings.
        $settingShowHeaderFooter = data_get($attendanceOptions, 'reporting.show_header_footer', null);
        $settingShowHeader = data_get($attendanceOptions, 'reporting.show_header', null);
        $settingShowFooter = data_get($attendanceOptions, 'reporting.show_footer', null);

        $showHeader = $settingShowHeader !== null
            ? (bool) $settingShowHeader
            : ($settingShowHeaderFooter !== null ? (bool) $settingShowHeaderFooter : true);

        $showFooter = $settingShowFooter !== null
            ? (bool) $settingShowFooter
            : ($settingShowHeaderFooter !== null ? (bool) $settingShowHeaderFooter : true);

        if (array_key_exists('show_header', $departmentSettings)) {
            $showHeader = (bool) $departmentSettings['show_header'];
        }
        if (array_key_exists('show_footer', $departmentSettings)) {
            $showFooter = (bool) $departmentSettings['show_footer'];
        }

        // If header is disabled, clear header data so view won't render or reserve space.
        if (!$showHeader) {
            $headerImageBase64 = '';
            $headerHtml = '';
            $hasHeader = false;
        }

        // If footer is disabled, clear footer data so view won't render or reserve space.
        if (!$showFooter) {
            $footerImageBase64 = '';
            $footerHtml = '';
            $footerContent = '';
            $hasFooter = false;
        }

        // Provide a combined flag for older templates that expect a single boolean.
        $showHeaderFooter = ($showHeader && $showFooter);

        // apply page bottom margin if provided (already set above)
        $reportFooterHeight = max((int) ($layoutSettings['footer_height'] ?? 70), 0); // px

        $resolveSignatureImage = fn (?string $path): string => $this->makeSignatureBackgroundTransparent(
            $this->resolvePublicStorageImageDataUri($path)
        );

        $resolveDepartmentSignature = function (string $field) use ($departmentSettings, $settings, $resolveSignatureImage): string {
            $departmentImage = $resolveSignatureImage(
                trim((string) ($departmentSettings[$field] ?? ''))
            );
            if ($departmentImage !== '') {
                return $departmentImage;
            }

            return $resolveSignatureImage((string) ($settings?->{$field} ?? ''));
        };
        $technologistSignature = $resolveDepartmentSignature('technologist_signature');
        $sampleCollectedBySignature = $resolveDepartmentSignature('sample_collected_by_signature');
        $pathologistSignature = $resolveDepartmentSignature('pathologist_signature');
        $pathologistName = array_key_exists('pathologist_name', $departmentSettings)
            ? trim((string) $departmentSettings['pathologist_name'])
            : trim((string) ($settings?->pathologist_name ?? ''));
        $pathologistDesignation = array_key_exists('pathologist_designation', $departmentSettings)
            ? trim((string) $departmentSettings['pathologist_designation'])
            : trim((string) ($settings?->pathologist_designation ?? ''));

        $technologistNameSetting = array_key_exists('technologist_name', $departmentSettings)
            ? trim((string) $departmentSettings['technologist_name'])
            : trim((string) ($identitySettings['technologist_name'] ?? ''));
        $technologistDesignationSetting = array_key_exists('technologist_designation', $departmentSettings)
            ? trim((string) $departmentSettings['technologist_designation'])
            : trim((string) ($identitySettings['technologist_designation'] ?? ''));
        $sampleCollectedByNameSetting = array_key_exists('sample_collected_by_name', $departmentSettings)
            ? trim((string) $departmentSettings['sample_collected_by_name'])
            : trim((string) ($identitySettings['sample_collected_by_name'] ?? ''));
        $sampleCollectedByDesignationSetting = array_key_exists('sample_collected_by_designation', $departmentSettings)
            ? trim((string) $departmentSettings['sample_collected_by_designation'])
            : trim((string) ($identitySettings['sample_collected_by_designation'] ?? ''));
        $machineInfoLine = trim((string) data_get($attendanceOptions, 'reporting.machine_info_line', ''));
        if ($machineInfoLine === '') {
            $machineInfoLine = 'Estimations were carried out by Macfura F-580 Automatic Haematology Analyzer & Verified manually';
        }

        $categorySpecificMachineInfo = $this->resolveMachineInfoByCategory($billItem, $attendanceOptions, $machineInfoLine);
        $machineInfoLine = $categorySpecificMachineInfo['text'];
        $machineInfoLineEnabled = $categorySpecificMachineInfo['enabled'];
        if ($reportingDepartment && array_key_exists('machine_info_line', $departmentSettings)) {
            $departmentMachineInfoLine = trim((string) $departmentSettings['machine_info_line']);
            $machineInfoLine = $departmentMachineInfoLine;
            $machineInfoLineEnabled = $departmentMachineInfoLine !== '';
        }
        if ($reportingDepartment && array_key_exists('machine_info_line_enabled', $departmentSettings)) {
            $machineInfoLineEnabled = $machineInfoLineEnabled && (bool) $departmentSettings['machine_info_line_enabled'];
        }

        $contactNo = $billing?->patient_mobile ?? $patient?->phone ?? $patient?->mobile ?? 'N/A';
        $gender = $billing?->gender ?? $patient?->gender ?? 'N/A';

        $age = 'N/A';
        if ($patient?->dob) {
            $dob = new \DateTime($patient->dob);
            $now = new \DateTime();
            $ageYears = $now->diff($dob)->y;
            $age = $ageYears . ' Year';
        } elseif (!empty($patient?->age)) {
            $age = $patient->age . ' Y';
        }

        $reportDateTime = $billItem->reported_at
            ? $billItem->reported_at->format('d-M-Y h:i:s A')
            : now()->format('d-M-Y h:i:s A');

        $refdBy = $billing?->doctor_name ?? 'N/A';

        $requestedItemIds = collect(explode(',', (string) request()->query('item_ids', '')))
            ->filter(fn ($id) => ctype_digit(trim((string) $id)))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $isCbc = $this->looksLikeCbcTest($billItem);

        if (request()->boolean('single_item')) {
            $items = collect([$billItem]);
        } elseif ($requestedItemIds->isNotEmpty()) {
            $selectedItems = BillItem::query()
                ->where('billing_id', $billItem->billing_id)
                ->where('category', $billItem->category)
                ->whereIn('id', $requestedItemIds)
                ->whereNotNull('reported_at')
                ->orderBy('id')
                ->get();

            $itemsWithParameterResults = BillItemParameterResult::query()
                ->whereIn('bill_item_id', $selectedItems->pluck('id'))
                ->distinct()
                ->pluck('bill_item_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $items = $selectedItems->filter(function ($item) use ($itemsWithParameterResults) {
                $note = trim(str_replace(
                    '[[FULL_PAGE]]',
                    '',
                    strip_tags((string) ($item->report_note ?? ''))
                ));

                return $note !== ''
                    || in_array((int) $item->id, $itemsWithParameterResults, true);
            })->values();

            abort_unless($items->contains('id', $billItem->id), 404);
        } elseif ($isCbc) {
            $items = collect([$billItem]);
        } else {
            // Build base query for pathology items in the same billing.
            // Include items with or without reported_at so draft/unsaved items
            // can be printed together in one batch.
            $allItemsQuery = BillItem::query()
                ->where('billing_id', $billItem->billing_id)
                ->where('category', 'Pathology')
                ->whereNotNull('reported_at')
                ->where(function ($query) {
                    $query->whereNull('item_name')
                        ->orWhereRaw("LOWER(item_name) NOT LIKE '%cbc%'");
                });

            // Group by main_category_id first (broader grouping); fall back to
            // test_category_id so tests of the same department print together.
            if (!empty($billItem->item_id)) {
                $primaryTest = Test::query()->find($billItem->item_id);
                $primaryMainCategoryId = $primaryTest?->main_category_id ?? null;
                $primaryCategoryId = $primaryTest?->test_category_id ?? null;

                if (!empty($primaryMainCategoryId)) {
                    // Collect all test IDs that share the same main category
                    $relatedTestIds = Test::query()
                        ->where('main_category_id', $primaryMainCategoryId)
                        ->pluck('id')
                        ->all();

                    if (!empty($relatedTestIds)) {
                        $allItemsQuery->whereIn('item_id', $relatedTestIds);
                    }
                } elseif (!empty($primaryCategoryId)) {
                    // Fallback: group by test_category_id
                    $relatedTestIds = Test::query()
                        ->where('test_category_id', $primaryCategoryId)
                        ->pluck('id')
                        ->all();

                    if (!empty($relatedTestIds)) {
                        $allItemsQuery->whereIn('item_id', $relatedTestIds);
                    }
                }
            }

            $allItems = $allItemsQuery->orderBy('id')->get();
            $itemsWithParameterResults = BillItemParameterResult::query()
                ->whereIn('bill_item_id', $allItems->pluck('id'))
                ->distinct()
                ->pluck('bill_item_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $allItems = $allItems->filter(function ($item) use ($itemsWithParameterResults) {
                $note = trim(str_replace(
                    '[[FULL_PAGE]]',
                    '',
                    strip_tags((string) ($item->report_note ?? ''))
                ));

                return $note !== ''
                    || in_array((int) $item->id, $itemsWithParameterResults, true);
            })->values();

            $chunks = $allItems->chunk(4);
            $items = $chunks->first(function ($chunk) use ($billItem) {
                return $chunk->contains('id', $billItem->id);
            }) ?? collect([$billItem]);
        }

        $isUltrasonogramReport = $this->isUltrasonogramBillItem($billItem, $reportTitle);

        // If structured parameter results exist for items, prefer them for printing
        foreach ($items as $it) {
            $storedReportNote = (string) ($it->report_note ?? '');
            $histogramPreference = 'SHOW';
            if (preg_match('/\[\[CBC_HISTOGRAM:(SHOW|HIDE)\]\]/i', $storedReportNote, $histogramMatch) === 1) {
                $histogramPreference = strtoupper($histogramMatch[1]);
                $it->report_note = trim((string) preg_replace('/\s*\[\[CBC_HISTOGRAM:(?:SHOW|HIDE)\]\]\s*/i', "\n", $storedReportNote));
            }

            $histogramPayload = $it->getAttribute('cbc_histogram_data')
                ?? $it->getAttribute('histogram_data')
                ?? [];
            if (is_string($histogramPayload)) {
                $decodedHistogramPayload = json_decode($histogramPayload, true);
                $histogramPayload = json_last_error() === JSON_ERROR_NONE && is_array($decodedHistogramPayload)
                    ? $decodedHistogramPayload
                    : [];
            }
            $histogramData = [];
            if (is_array($histogramPayload)) {
                foreach (['WBC' => 'wbc', 'RBC' => 'rbc', 'PLT' => 'plt'] as $label => $key) {
                    $values = $histogramPayload[$label] ?? $histogramPayload[$key] ?? null;
                    if (is_array($values) && count($values) > 1) {
                        $numericValues = array_values(array_filter($values, fn ($value) => is_numeric($value) && is_finite((float) $value)));
                        if (count($numericValues) > 1) {
                            $histogramData[$label] = array_map('floatval', $numericValues);
                        }
                    }
                }
            }
            foreach (['WBC' => 'wbc', 'RBC' => 'rbc', 'PLT' => 'plt'] as $label => $key) {
                if (isset($histogramData[$label])) {
                    continue;
                }
                $values = $it->getAttribute($key . '_histogram_data') ?? $it->getAttribute($key . '_histogram');
                if (is_string($values)) {
                    $decodedValues = json_decode($values, true);
                    $values = json_last_error() === JSON_ERROR_NONE ? $decodedValues : null;
                }
                if (is_array($values) && count($values) > 1) {
                    $numericValues = array_values(array_filter($values, fn ($value) => is_numeric($value) && is_finite((float) $value)));
                    if (count($numericValues) > 1) {
                        $histogramData[$label] = array_map('floatval', $numericValues);
                    }
                }
            }
            $it->cbc_histogram_data = $histogramData;
            $it->show_cbc_histogram = $histogramPreference === 'SHOW' && $histogramData !== [];

            $reportFunction = '';
            $mainCategoryName = '';
            $defaultParamRange = '';
            $defaultParamUnit = '';

            if (!empty($it->item_id)) {
                $testRecord = Test::query()->whereKey($it->item_id)->first(['report_function', 'main_category_id']);
                $reportFunction = trim((string) ($testRecord?->report_function ?? ''));

                // Resolve main category name
                if ($testRecord && $testRecord->main_category_id) {
                    $mainCategoryName = trim((string) (MainCategory::query()->whereKey($testRecord->main_category_id)->value('name') ?? ''));
                }

                // Fetch stored parameter range (used when no BillItemParameterResult exists yet)
                $firstParam = PathologyTestParameter::query()
                    ->where('pathology_test_id', $it->item_id)
                    ->with('pathologyUnit:id,name')
                    ->orderBy('id')
                    ->first();
                if ($firstParam) {
                    $pFrom = trim((string) ($firstParam->reference_from ?? ''));
                    $pTo   = trim((string) ($firstParam->reference_to ?? ''));
                    $defaultParamUnit = trim((string) ($firstParam->pathologyUnit->name ?? ''));
                    if ($pFrom !== '' && $pTo !== '') {
                        $defaultParamRange = $pFrom . ' - ' . $pTo;
                    } elseif ($pFrom !== '') {
                        $defaultParamRange = $pFrom;
                    } elseif ($pTo !== '') {
                        $defaultParamRange = $pTo;
                    }
                    if ($defaultParamRange !== '' && $defaultParamUnit !== '') {
                        $defaultParamRange .= ' ' . $defaultParamUnit;
                    } elseif ($defaultParamUnit !== '') {
                        $defaultParamRange = $defaultParamUnit;
                    }
                }
            }

            if ($reportFunction === '') {
                $reportFunction = $this->autoDetectTestFunction((string) ($it->item_name ?? ''), (string) ($it->category ?? ''));
            }
            $it->report_function = $reportFunction;
            $it->main_category_name = $mainCategoryName;
            // Use stored bill-item range if present, else fall back to parameter-derived range.
            $it->resolved_print_range = trim((string) ($it->report_range ?? '')) ?: $defaultParamRange;
            $it->resolved_print_unit  = $defaultParamUnit;

            $cbcParameterDefinitions = collect();
            $cbcTemplateDefinitions = collect();
            if ($this->looksLikeCbcTest($it)) {
                $cbcTemplateDefinitions = collect($this->parameterTemplateForTest('cbc'))
                    ->keyBy(fn ($parameter) => mb_strtolower(trim((string) $parameter['name'])));
                $cbcParameterDefinitions = PathologyTestParameter::query()
                    ->where('pathology_test_id', $it->item_id)
                    ->with('pathologyUnit:id,name', 'testParameter:id,name')
                    ->get()
                    ->keyBy(fn ($parameter) => mb_strtolower(trim((string) ($parameter->name ?: $parameter->testParameter?->name))));
            }

            $results = BillItemParameterResult::query()->where('bill_item_id', $it->id)->orderBy('id')->get();
            if ($results->isNotEmpty()) {
                $plainLines = [];
                $printRows = [];

                foreach ($results as $r) {
                    $param = null;
                    if (!empty($r->pathology_test_parameter_id)) {
                        $param = PathologyTestParameter::query()->find($r->pathology_test_parameter_id);
                    }

                    $name = trim((string) ($param?->name ?: data_get($param, 'testParameter.name') ?: $r->name ?: data_get($r, 'pathologyTestParameter.name') ?: ''));

                    // Older results may contain the unit in the name column.
                    // Use the bill item's test name so printed reports remain readable.
                    if ($name !== '' && preg_match('/^(mg(\/dl)?|mmol(\/l)?|g\/dl|[uμ]?mol\/l|[uμ]?iu(\/ml|\/l)?|miu(\/ml|\/l)?|iu|%|unit)$/iu', $name)) {
                        $name = trim((string) ($it->item_name ?? ''));
                    }

                    // If name is still empty, try to infer it from any stored result_html
                    // This helps when older records stored a single HTML string like "Uric acid: 4.5"
                    // and the structured `name` field is not populated.
                    if ($name === '') {
                        $rHtmlRaw = trim((string) ($r->result_html ?? ''));
                        if ($rHtmlRaw !== '') {
                            $posRaw = strpos($rHtmlRaw, ':');
                            if ($posRaw !== false) {
                                $maybeName = trim(strip_tags(substr($rHtmlRaw, 0, $posRaw)));
                                if ($maybeName !== '') {
                                    $name = $maybeName;
                                }
                            }
                        }
                    }
                    $unit = trim((string) ($r->unit ?? ''));
                    $val = trim((string) ($r->value ?? ''));
                    $cbcDefinition = $cbcParameterDefinitions->get(mb_strtolower($name));
                    $cbcTemplateDefinition = $cbcTemplateDefinitions->get(mb_strtolower($name));

                    // Build normal range string from parameter definition when available
                    $from = trim((string) ($param?->reference_from ?? ''))
                        ?: trim((string) data_get($cbcDefinition, 'reference_from', ''))
                        ?: trim((string) data_get($cbcTemplateDefinition, 'reference_from', ''));
                    $to = trim((string) ($param?->reference_to ?? ''))
                        ?: trim((string) data_get($cbcDefinition, 'reference_to', ''))
                        ?: trim((string) data_get($cbcTemplateDefinition, 'reference_to', ''));
                    $paramUnit = trim((string) data_get($param, 'pathologyUnit.name', ''))
                        ?: trim((string) data_get($cbcDefinition, 'pathologyUnit.name', ''))
                        ?: trim((string) data_get($cbcTemplateDefinition, 'unit', ''));
                    $rangeUnit = '';
                    $rangeSource = trim($from . ' ' . $to);
                    if ($rangeSource !== '' && preg_match('/([\p{L}µμ]+(?:\/[\p{L}]+)?)\s*$/u', $rangeSource, $unitMatch)) {
                        $rangeUnit = trim($unitMatch[1]);
                    }
                    $displayUnit = $paramUnit !== '' ? $paramUnit : ($unit !== '' ? $unit : $rangeUnit);
                    [$cleanValue, $displayUnit] = $this->splitTrailingUnit($val, $displayUnit);
                    $displayValue = $cleanValue !== '' ? $cleanValue : trim((string) $val);

                    if ($from !== '' && $to !== '') {
                        $rangeStr = $from . ' - ' . $to;
                    } elseif ($from !== '') {
                        $rangeStr = $from;
                    } elseif ($to !== '') {
                        $rangeStr = $to;
                    } else {
                        $rangeStr = '';
                    }

                    if ($paramUnit !== '') {
                        $rangeStr = $rangeStr !== '' ? $rangeStr . ' ' . $paramUnit : $paramUnit;
                    } elseif ($unit !== '') {
                        $rangeStr = $rangeStr !== '' ? $rangeStr . ' ' . $unit : $unit;
                    }

                    [$rangeValue, $rangeUnit] = $this->splitTrailingUnit($rangeStr, $displayUnit);
                    if ($rangeValue !== '' && $rangeUnit !== '') {
                        $rangeStr = trim($rangeValue . ' ' . $rangeUnit);
                    }

                    // numeric comparison to detect out-of-range
                    $valNum = $this->parseNumeric($val);
                    $fromNum = $this->parseNumeric($from);
                    $toNum = $this->parseNumeric($to);

                    // If controller did not get explicit from/to numbers, try to
                    // extract numeric bounds from the assembled range string
                    // (covers cases like item->report_range = "0.6-1.2 mg/dl" or "0.6 - 1.2").
                    $rangeCandidate = trim((string) ($rangeStr ?? ''));
                    if ($rangeCandidate === '' && isset($it) && isset($it->report_range)) {
                        $rangeCandidate = trim((string) $it->report_range);
                    }
                    if ($rangeCandidate !== '') {
                        $r = preg_replace('/[–—]/u', '-', $rangeCandidate);
                        // comparator like '< 150' or '<150' or '>= 0.6'
                        if ($fromNum === null || $toNum === null) {
                            if (preg_match('/([<>]=?)\s*([+-]?[0-9]+(?:[\.,][0-9]+)?)/u', $r, $mc)) {
                                $op = $mc[1];
                                $limit = floatval(str_replace(',', '.', $mc[2]));
                                if ($op === '<' || $op === '<=') {
                                    $toNum = $limit;
                                } elseif ($op === '>' || $op === '>=') {
                                    $fromNum = $limit;
                                }
                            } elseif (preg_match_all('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $r, $mn) && count($mn[0]) >= 2) {
                                $fromNum = floatval(str_replace(',', '.', $mn[0][0]));
                                $toNum = floatval(str_replace(',', '.', $mn[0][1]));
                            }
                        }
                    }

                    // If a single bound string actually contains a min-max (eg "0.6 - 1.2" in reference_from),
                    // try to extract both numbers from that string so we treat them as low/high.
                    if ($fromNum !== null && $toNum === null) {
                        if (preg_match_all('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $from, $m) && count($m[0]) >= 2) {
                            $fromNum = floatval(str_replace(',', '.', $m[0][0]));
                            $toNum = floatval(str_replace(',', '.', $m[0][1]));
                        }
                    }
                    if ($toNum !== null && $fromNum === null) {
                        if (preg_match_all('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $to, $m2) && count($m2[0]) >= 2) {
                            $fromNum = floatval(str_replace(',', '.', $m2[0][0]));
                            $toNum = floatval(str_replace(',', '.', $m2[0][1]));
                        }
                    }

                    // Only mark as out-of-range when a numeric value exists and
                    // it falls strictly outside the provided bounds. Handle
                    // situations where only one bound is provided and where
                    // bounds may be reversed (ensure min/max semantics).
                    $isOut = false;
                    if ($valNum !== null) {
                        if ($fromNum !== null && $toNum !== null) {
                            $low = min($fromNum, $toNum);
                            $high = max($fromNum, $toNum);
                            if ($valNum < $low || $valNum > $high) {
                                $isOut = true;
                            }
                        } elseif ($fromNum !== null && $toNum === null) {
                            // only lower bound provided -> value less than lower is out
                            if ($valNum < $fromNum) {
                                $isOut = true;
                            }
                        } elseif ($toNum !== null && $fromNum === null) {
                            // only upper bound provided -> value greater than upper is out
                            if ($valNum > $toNum) {
                                $isOut = true;
                            }
                        }
                    }

                    $escapedName = e($name);
                    $escapedDisplayValue = e($displayValue);

                    $resultHtmlLine = $escapedName === ''
                        ? ($isOut ? '<strong>' . $escapedDisplayValue . '</strong>' : $escapedDisplayValue)
                        : ($escapedName . ': ' . ($isOut ? '<strong>' . $escapedDisplayValue . '</strong>' : $escapedDisplayValue));

                    $plainLines[] = ($name === '' ? $displayValue : ($name . ': ' . $displayValue));
                    $printRows[] = [
                        'result_html' => $resultHtmlLine,
                        'param' => $name,
                        'value' => $displayValue,
                        'unit' => $displayUnit,
                        'normal_range' => $rangeStr,
                        'is_out' => $isOut,
                        'value_num' => $valNum,
                        'main_category_name' => $it->main_category_name ?? '',
                        'report_function' => $it->report_function ?? '',
                        'item_name' => $it->item_name ?? '',
                    ];

                        // Temporary debug log for all parameters (will help diagnose mismatches)
                        try {
                            Log::info('ReportingController::print param debug', [
                                'bill_item_id' => $billItem->id ?? null,
                                'item_id' => $it->item_id ?? null,
                                'param_name' => $name,
                                'raw_value' => $val,
                                'display_value' => $displayValue,
                                'from_raw' => $from,
                                'to_raw' => $to,
                                'from_num' => $fromNum,
                                'to_num' => $toNum,
                                'value_num' => $valNum,
                                'is_out' => $isOut,
                            ]);
                        } catch (\Throwable $_) {
                            // ignore logging failures
                        }
                }

                $it->report_note = implode("\n", $plainLines);
                $it->printed_parameter_rows = $printRows;
            } else {
                // Fallback: try to parse free-text report_note into parameter rows
                $plain = trim((string) ($it->report_note ?? ''));
                $printRows2 = [];
                if ($plain !== '') {
                    $lines = preg_split('/\r?\n/', $plain);
                    foreach ($lines as $line) {
                        $line = trim((string) $line);
                        if ($line === '') continue;

                        $name = '';
                        $val = '';
                        $unit = '';

                        // Try common patterns: "Name: 0.8 mg/dl" or "Name - 0.8 mg/dl"
                        if (preg_match('/^(.*?)[\:\-\t]\s*([+-]?[0-9]+(?:[\.,][0-9]+)?)(.*)$/u', $line, $m)) {
                            $name = trim($m[1]);
                            $val = trim($m[2]);
                            $unit = trim($m[3]);
                        } else {
                            // Try to locate first number token as value
                            if (preg_match('/([+-]?[0-9]+(?:[\.,][0-9]+)?)/u', $line, $m2)) {
                                $val = trim($m2[1]);
                                // name is everything except the matched number
                                $name = trim(preg_replace('/' . preg_quote($m2[0], '/') . '/u', '', $line, 1));
                                // try to extract trailing unit text
                                $unit = trim(preg_replace('/^.*' . preg_quote($m2[0], '/') . '/u', '', $line));
                            } else {
                                // no numeric value found: treat whole line as a value string
                                $name = '';
                                $val = $line;
                            }
                        }

                        $displayUnit = trim((string) $unit);
                        $cleanValue = trim((string) $val);
                        if ($displayUnit !== '' && $cleanValue !== '') {
                            $cleanValue = preg_replace('/\s*' . preg_quote($displayUnit, '/') . '$/iu', '', $cleanValue);
                            $cleanValue = trim((string) $cleanValue);
                        }
                        $displayValue = $cleanValue !== '' ? $cleanValue : trim((string) $val);

                        // Build a candidate range: use resolved_print_range (parameter-derived) first.
                        $rangeStr = trim((string) ($it->resolved_print_range ?? $it->report_range ?? ''));
                        if ($rangeStr === '') {
                            try {
                                $rangeStr = (string) ($this->suggestNormalRangeByTestName($it) ?? '');
                            } catch (\Throwable $_) {
                                $rangeStr = '';
                            }
                        }
                        // Use parameter-derived unit if line-parsed unit is empty
                        if ($displayUnit === '') {
                            $displayUnit = trim((string) ($it->resolved_print_unit ?? ''));
                        }

                        $valNum = $this->parseNumeric($val);
                        $fromNum = null;
                        $toNum = null;

                        // Try to extract bounds from rangeStr similar to structured path
                        $rc = trim((string) $rangeStr);
                        if ($rc !== '') {
                            $r = preg_replace('/[–—]/u', '-', $rc);
                            if (preg_match('/([<>]=?)\s*([+-]?[0-9]+(?:[\.,][0-9]+)?)/u', $r, $mc)) {
                                $op = $mc[1];
                                $limit = floatval(str_replace(',', '.', $mc[2]));
                                if ($op === '<' || $op === '<=') {
                                    $toNum = $limit;
                                } elseif ($op === '>' || $op === '>=') {
                                    $fromNum = $limit;
                                }
                            } elseif (preg_match_all('/[+-]?[0-9]+(?:[\.,][0-9]+)?/u', $r, $mn) && count($mn[0]) >= 2) {
                                $fromNum = floatval(str_replace(',', '.', $mn[0][0]));
                                $toNum = floatval(str_replace(',', '.', $mn[0][1]));
                            }
                        }

                        $isOut = false;
                        if ($valNum !== null) {
                            if ($fromNum !== null && $toNum !== null) {
                                $low = min($fromNum, $toNum);
                                $high = max($fromNum, $toNum);
                                if ($valNum < $low || $valNum > $high) $isOut = true;
                            } elseif ($fromNum !== null && $toNum === null) {
                                if ($valNum < $fromNum) $isOut = true;
                            } elseif ($toNum !== null && $fromNum === null) {
                                if ($valNum > $toNum) $isOut = true;
                            }
                        } else {
                            // when no numeric value but there is a textual range, mark outside conservatively
                            if ($rangeStr !== '' && !preg_match('/[0-9]/', $rangeStr)) {
                                if (mb_strtolower($displayValue) !== mb_strtolower($rangeStr)) $isOut = true;
                            }
                        }

                        // If parsed name looks like a unit (eg 'mg/dl'), try to infer real parameter name
                        if ($name !== '') {
                            $nameLc = mb_strtolower($name);
                            if (preg_match('/^(mg(\/dl)?|mmol(\/l)?|g\/dl|[uμ]?mol\/l|[uμ]?iu(\/ml|\/l)?|miu(\/ml|\/l)?|iu|unit|%)$/iu', $nameLc) || (preg_match('/^[\p{L}\/\s%]+$/u', $nameLc) && mb_strlen(trim($nameLc)) <= 8)) {
                                // try to find a known parameter token in the full line
                                $tokens = ['creatinine','creat','s\.creatinine','s creatinine','urea','triglyceride','triglycerides','triglycerid','cholesterol','hdl','ldl','glucose','rbs','hba1c','uric acid','uricacid','uric'];
                                $found = '';
                                foreach ($tokens as $t) {
                                    try {
                                        if (preg_match('/' . $t . '/iu', $line) === 1) { $found = $t; break; }
                                    } catch (\Throwable $_) { }
                                }
                                if ($found !== '') {
                                    $name = ucwords(str_replace(['.', '_'], ' ', preg_replace('/[^a-z0-9\.\s]/i', '', $found)));
                                } else {
                                    // fallback to item name if available
                                    $name = trim((string) ($it->item_name ?? 'Result'));
                                }
                            }
                        }

                        $printRows2[] = [
                            'result_html' => ($name === '' ? ($isOut ? '<strong>' . e($displayValue) . '</strong>' : e($displayValue)) : e($name) . ': ' . ($isOut ? '<strong>' . e($displayValue) . '</strong>' : e($displayValue))),
                            'param' => $name,
                            'value' => $displayValue,
                            'unit' => $displayUnit,
                            'normal_range' => $rangeStr,
                            'is_out' => $isOut,
                            'value_num' => $valNum,
                            'main_category_name' => $it->main_category_name ?? '',
                            'report_function' => $it->report_function ?? '',
                            'item_name' => $it->item_name ?? '',
                        ];
                    }
                }

                $it->report_note = $plain;
                $it->printed_parameter_rows = $printRows2;
                // Log fallback parse results to help debugging (temporary)
                try {
                    foreach ($it->printed_parameter_rows as $dbg) {
                        Log::info('ReportingController::print param debug (fallback)', array_merge(['bill_item_id' => $billItem->id ?? null, 'item_id' => $it->item_id ?? null], $dbg));
                    }
                } catch (\Throwable $_) {
                }
            }
        }

        try {
            Log::info('ReportingController::print prepared view', ['primary_id' => $billItem->id ?? null, 'items_count' => isset($items) ? (is_countable($items) ? count($items) : $items->count()) : 0]);
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            Log::info('ReportingController::print footer debug', [
                'footer_image_base64_empty' => ($footerImageBase64 ?? '') === '',
                'footer_content_empty' => ($footerContent ?? '') === '',
                'footer_html_empty' => ($footerHtml ?? '') === '',
                'hasFooter' => $hasFooter ?? false,
                'showHeaderFooter' => $showHeaderFooter ?? true,
                'invoice_footer_photo_path' => $invoiceDesign?->footer_photo_path ?? null,
                'invoice_footer_content_preview' => substr((string) ($invoiceDesign?->footer_content ?? ''), 0, 200),
                'settings_report_footer_preview' => substr((string) ($settings?->report_footer_html ?? ''), 0, 200),
                'footer_image_preview' => substr((string) ($footerImageBase64 ?? ''), 0, 120),
                'footer_image_length' => is_string($footerImageBase64) ? strlen($footerImageBase64) : null,
                'header_image_preview' => substr((string) ($headerImageBase64 ?? ''), 0, 120),
                'header_image_length' => is_string($headerImageBase64) ? strlen($headerImageBase64) : null,
            ]);
        } catch (\Throwable $_) {
            // ignore logging failures
        }

        try {
            return view('backend.reporting.print', [
            'items' => $items,
            'primaryItem' => $billItem,
            'printedDeliveryUrls' => $items->map(
                fn ($item) => route('backend.report-delivery.deliver', $item->id)
            )->values(),
            'trackPrintedDelivery' => request()->boolean('mark_delivered')
                && !$isPatientPortalPrint
                && auth('admin')->check(),
            'reportTitle' => $reportTitle,
            'isUltrasonogramReport' => $isUltrasonogramReport,
            'billing' => $billing,
            'patientName' => $patientName,
            'headerHtml' => $headerHtml,
            'footerHtml' => $footerHtml,
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $footerContent,
            'footer_content_position' => in_array(strtolower((string) ($invoiceDesign?->footer_content_position ?? '')), ['above', 'below']) ? strtolower((string) $invoiceDesign?->footer_content_position) : 'above',
            'invoiceDesign' => $invoiceDesign,
            'fullPageInstitutionPadImage' => $fullPageInstitutionPadImage,
            'fullPageInstitutionPadContentOffset' => $fullPageInstitutionPadContentOffset,
            'hasHeader' => $hasHeader,
            'hasFooter' => $hasFooter,
            'reportDateTime' => $reportDateTime,
            'age' => $age,
            'contact_no' => $contactNo,
            'gender' => $gender,
            'refd_by' => $refdBy,
            'signatureMarginTop' => $signatureMarginTop,
            'signatureMarginLeft' => $signatureMarginLeft,
            'cbcSignatureMarginTop' => $cbcSignatureMarginTop,
            'pageMarginTop' => $pageMarginTop,
            'pageMarginBottom' => $pageMarginBottom,
            'pageMarginLeft' => $pageMarginLeft,
            'pageMarginRight' => $pageMarginRight,
            'reportHeaderHeight' => $reportHeaderHeight,
            'reportFooterHeight' => $reportFooterHeight,
            'reportFooterContentOffset' => $reportFooterContentOffset,
            'reportFontSizeAdjustment' => $reportFontSizeAdjustment,
            'technologistSignature' => $technologistSignature,
            'sampleCollectedBySignature' => $sampleCollectedBySignature,
            'pathologistSignature' => $pathologistSignature,
            'pathologistName' => $pathologistName !== '' ? $pathologistName : 'N/A',
            'pathologistDesignation' => $pathologistDesignation !== '' ? $pathologistDesignation : 'Pathologist',
            'technologistNameSetting' => $technologistNameSetting,
            'technologistDesignationSetting' => $technologistDesignationSetting,
            'sampleCollectedByNameSetting' => $sampleCollectedByNameSetting,
            'sampleCollectedByDesignationSetting' => $sampleCollectedByDesignationSetting,
            'machineInfoLine' => $machineInfoLine,
            'machineInfoLineEnabled' => $machineInfoLineEnabled,
            // raw values for blade logic (empty string when not set)
            'pathologistNameRaw' => $pathologistName,
            'pathologistDesignationRaw' => $pathologistDesignation,
            // legacy template expects `$websetting` in some branches; provide it
            'websetting' => $settings,
            ]);
        } catch (\Throwable $e) {
            try {
                Log::error('ReportingController::print view render failed', [
                    'message' => $e->getMessage(),
                    'bill_item_id' => $billItem->id ?? null,
                    'billing_id' => $billing->id ?? null,
                    'trace' => $e->getTraceAsString(),
                ]);
            } catch (\Throwable $_) {
                // ignore logging failures
            }

            throw $e;
        }
    }

    public function downloadReport(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'module' => ['nullable', 'string', 'in:reporting,pathology,radiology'],
        ]);

        $requestedId = (int) $validated['id'];
        $module = strtolower((string) ($validated['module'] ?? 'reporting'));

        // Formula support: id can be either bill_items.id or billings.id.
        $billItem = BillItem::query()->find($requestedId);

        if (!$billItem) {
            $billItem = BillItem::query()
                ->where('billing_id', $requestedId)
                ->when($module === 'pathology', fn ($query) => $query->where('category', 'Pathology'))
                ->when($module === 'radiology', fn ($query) => $query->where('category', 'Radiology'))
                ->whereNotNull('reported_at')
                ->orderBy('id')
                ->first();
        }

        if (!$billItem) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'No report found for this id.');
        }

        // Prevent downloading reports for IPD-generated billings
        if (str_starts_with((string) ($billItem->billing->case_number ?? ''), 'IPD-')) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'Reporting is not available for IPD invoices.');
        }

        return redirect()->route('backend.reporting.print', $billItem->id);
    }

    private function resolveReportTitle(BillItem $billItem): string
    {
        $categoryName = '';

        if ($billItem->category === 'Pathology' && !empty($billItem->item_id)) {
            $test = Test::query()
                ->with(['pathologyCategory', 'mainCategory'])
                ->find($billItem->item_id);

            $categoryName = trim((string) ($test?->mainCategory?->name ?? $test?->pathologyCategory?->name ?? ''));
        } elseif ($billItem->category === 'Radiology' && !empty($billItem->item_id)) {
            // `bill_items.item_id` may reference either a RadiologyTest record
            // or directly a Test record. Try both patterns to reliably resolve
            // the main category name.
            $categoryName = '';

            // Try as RadiologyTest id -> RadiologyTest->test->pathologyCategory
            try {
                $radiologyTest = RadiologyTest::query()
                    ->with(['test.pathologyCategory', 'test.mainCategory'])
                    ->find($billItem->item_id);
                $categoryName = trim((string) ($radiologyTest?->test?->mainCategory?->name ?? $radiologyTest?->test?->pathologyCategory?->name ?? ''));
            } catch (\Throwable $_) {
                // ignore
            }

            // If not found, try interpreting item_id as Test id
            if ($categoryName === '') {
                try {
                    $t = Test::query()->with(['pathologyCategory', 'mainCategory'])->find($billItem->item_id);
                    $categoryName = trim((string) ($t?->mainCategory?->name ?? $t?->pathologyCategory?->name ?? ''));
                } catch (\Throwable $_) {
                    // ignore
                }
            }
        }

        // If still empty, try additional heuristics from the itemable relation
        if ($categoryName === '' && $billItem->relationLoaded('itemable') || $billItem->itemable) {
            try {
                $itemable = $billItem->itemable;
                if ($itemable) {
                    // common patterns: test_id or test_category_id
                    if (isset($itemable->test_id) && !empty($itemable->test_id)) {
                        $t = Test::with(['pathologyCategory', 'mainCategory'])->find($itemable->test_id);
                        $categoryName = trim((string) ($t?->mainCategory?->name ?? $t?->pathologyCategory?->name ?? ''));
                    }

                    if ($categoryName === '' && isset($itemable->test_category_id) && !empty($itemable->test_category_id)) {
                        $tc = \App\Models\TestCategory::find($itemable->test_category_id);
                        $categoryName = trim((string) ($tc?->name ?? ''));
                    }

                    // some itemables expose a `test` relation
                    if ($categoryName === '' && method_exists($itemable, 'test')) {
                        try {
                            $t2 = $itemable->test()->with(['pathologyCategory', 'mainCategory'])->first();
                            $categoryName = trim((string) ($t2?->mainCategory?->name ?? $t2?->pathologyCategory?->name ?? ''));
                        } catch (\Throwable $_) {
                            // ignore
                        }
                    }
                }
            } catch (\Throwable $_) {
                // ignore
            }
        }

        if ($categoryName === '') {
            $fallback = trim((string) $billItem->category);
            if ($fallback === '') {
                return 'Test Report';
            }
            return $fallback . ' Report';
        }

        return $categoryName . ' Report';
    }

    private function isUltrasonogramBillItem(BillItem $billItem, ?string $reportTitle = null): bool
    {
        $keywords = ['ultrasonogram', 'ultrasonography', 'usg'];

        $matchesKeywords = function (?string $value) use ($keywords): bool {
            $text = strtolower(trim((string) $value));
            if ($text === '') {
                return false;
            }

            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return true;
                }
            }

            return false;
        };

        if (
            $matchesKeywords((string) $billItem->category)
            || $matchesKeywords((string) $billItem->item_name)
            || $matchesKeywords((string) $reportTitle)
        ) {
            return true;
        }

        if ($billItem->category !== 'Radiology' || empty($billItem->item_id)) {
            return false;
        }

        $radiologyTest = RadiologyTest::query()
            ->with(['test.pathologyCategory', 'test.mainCategory'])
            ->find($billItem->item_id);

        if (!$radiologyTest) {
            return false;
        }

        $lookupTexts = [
            data_get($radiologyTest, 'test.test_name'),
            data_get($radiologyTest, 'test.test_short_name'),
            data_get($radiologyTest, 'test.mainCategory.name'),
            data_get($radiologyTest, 'test.pathologyCategory.name'),
        ];

        foreach ($lookupTexts as $text) {
            if ($matchesKeywords($text)) {
                return true;
            }
        }

        return false;
    }

    private function parameterTemplateForTest(string $testText): array
    {
        $text = strtolower(trim($testText));

        $differentialTemplate = [
            ['name' => 'Differential Count', 'reference_from' => '', 'reference_to' => '', 'unit' => ''],
            ['name' => 'Neutrophils', 'reference_from' => '40', 'reference_to' => '75', 'unit' => '%'],
            ['name' => 'Lymphocytes', 'reference_from' => '20', 'reference_to' => '45', 'unit' => '%'],
            ['name' => 'Monocytes', 'reference_from' => '2', 'reference_to' => '10', 'unit' => '%'],
            ['name' => 'Eosinophils', 'reference_from' => '1', 'reference_to' => '6', 'unit' => '%'],
            ['name' => 'Basophils', 'reference_from' => '0', 'reference_to' => '1', 'unit' => '%'],
            ['name' => 'Total Cir. Eosinophils', 'reference_from' => '0', 'reference_to' => '500', 'unit' => '/µl'],
        ];

        $hctTemplate = [
            ['name' => 'HCT/PCV', 'reference_from' => '36', 'reference_to' => '46', 'unit' => '%'],
        ];

        $plateletTemplate = [
            ['name' => 'Platelet Count', 'reference_from' => '150,000', 'reference_to' => '450,000', 'unit' => '/µl'],
            ['name' => 'MPV', 'reference_from' => '7.5', 'reference_to' => '11.5', 'unit' => 'fl'],
            ['name' => 'PDW', 'reference_from' => '10', 'reference_to' => '18', 'unit' => 'fl'],
            ['name' => 'P-LCR', 'reference_from' => '15', 'reference_to' => '35', 'unit' => '%'],
            ['name' => 'PCT', 'reference_from' => '0.10', 'reference_to' => '0.50', 'unit' => '%'],
        ];

        $cbcTemplate = [
            ['name' => 'Hemoglobin', 'reference_from' => '12', 'reference_to' => '17', 'unit' => 'g/dl'],
            ['name' => 'ESR', 'reference_from' => '0', 'reference_to' => '20', 'unit' => 'mm/hr'],
            ['name' => 'Total WBC Count', 'reference_from' => '4,000', 'reference_to' => '11,000', 'unit' => '/µl'],
            ['name' => 'Differential Count', 'reference_from' => '', 'reference_to' => '', 'unit' => ''],
            ['name' => 'Neutrophils', 'reference_from' => '40', 'reference_to' => '75', 'unit' => '%'],
            ['name' => 'Lymphocytes', 'reference_from' => '20', 'reference_to' => '45', 'unit' => '%'],
            ['name' => 'Monocytes', 'reference_from' => '2', 'reference_to' => '10', 'unit' => '%'],
            ['name' => 'Eosinophils', 'reference_from' => '1', 'reference_to' => '6', 'unit' => '%'],
            ['name' => 'Basophils', 'reference_from' => '0', 'reference_to' => '1', 'unit' => '%'],
            ['name' => 'Blast/Atypical %', 'reference_from' => '', 'reference_to' => '', 'unit' => '%'],
            ['name' => 'Myelocytes %', 'reference_from' => '', 'reference_to' => '', 'unit' => '%'],
            ['name' => 'Total Cir. Eosinophils', 'reference_from' => '0', 'reference_to' => '500', 'unit' => '/µl'],
            ['name' => 'BT', 'reference_from' => '2', 'reference_to' => '7', 'unit' => 'min'],
            ['name' => 'CT', 'reference_from' => '4', 'reference_to' => '11', 'unit' => 'min'],
            ['name' => 'Blood Film', 'reference_from' => '', 'reference_to' => '', 'unit' => ''],
            ['name' => 'Total RBC Count', 'reference_from' => '4.2', 'reference_to' => '5.4', 'unit' => 'million/µl'],
            ['name' => 'HCT/PCV', 'reference_from' => '36', 'reference_to' => '46', 'unit' => '%'],
            ['name' => 'MCV', 'reference_from' => '80', 'reference_to' => '100', 'unit' => 'fl'],
            ['name' => 'MCH', 'reference_from' => '27', 'reference_to' => '32', 'unit' => 'pg'],
            ['name' => 'MCHC', 'reference_from' => '32', 'reference_to' => '36', 'unit' => 'g/dl'],
            ['name' => 'Platelet Count', 'reference_from' => '150,000', 'reference_to' => '450,000', 'unit' => '/µl'],
            ['name' => 'RDW-CV', 'reference_from' => '11.5', 'reference_to' => '14.5', 'unit' => '%'],
            ['name' => 'RDW-SD', 'reference_from' => '35', 'reference_to' => '56', 'unit' => 'fl'],
            ['name' => 'MPV', 'reference_from' => '7.5', 'reference_to' => '11.5', 'unit' => 'fl'],
            ['name' => 'PDW', 'reference_from' => '10', 'reference_to' => '18', 'unit' => 'fl'],
            ['name' => 'P-LCR', 'reference_from' => '15', 'reference_to' => '35', 'unit' => '%'],
            ['name' => 'PCT', 'reference_from' => '0.10', 'reference_to' => '0.50', 'unit' => '%'],
        ];

        // helper: wrap parameter rows for returning
        $wrap = function (array $rows): array {
            return array_map(function (array $p) {
                return array_merge(['reference_from' => '', 'reference_to' => '', 'unit' => ''], $p, [
                    'id'        => 'gen:' . ($p['name'] ?? uniqid()),
                    'generated' => true,
                ]);
            }, $rows);
        };

        // ── Widal Test ────────────────────────────────────────────────────
        if (str_contains($text, 'widal')) {
            return $wrap([
                ['name' => 'S. Typhi O',      'reference_to' => '1:20', 'unit' => 'Titre'],
                ['name' => 'S. Typhi H',      'reference_to' => '1:20', 'unit' => 'Titre'],
                ['name' => 'S. Paratyphi AH', 'reference_to' => '1:20', 'unit' => 'Titre'],
                ['name' => 'S. Paratyphi BH', 'reference_to' => '1:20', 'unit' => 'Titre'],
            ]);
        }

        // ── Well-Felix / Weil-Felix ───────────────────────────────────────
        if (preg_match('/weil.?felix|well.?felix/', $text)) {
            return $wrap([
                ['name' => 'OX K',  'reference_to' => 'Up to 1:80'],
                ['name' => 'OX 2',  'reference_to' => 'Up to 1:80'],
                ['name' => 'OX 19', 'reference_to' => 'Up to 1:80'],
            ]);
        }

        // ── LFT / Liver Function Test ─────────────────────────────────────
        if (preg_match('/\blft\b|liver function/', $text)) {
            return $wrap([
                ['name' => 'SGPT (ALT)',         'reference_from' => '7',   'reference_to' => '56',  'unit' => 'U/L'],
                ['name' => 'SGOT (AST)',         'reference_from' => '10',  'reference_to' => '40',  'unit' => 'U/L'],
                ['name' => 'ALP',                'reference_from' => '44',  'reference_to' => '147', 'unit' => 'U/L'],
                ['name' => 'GGT',                'reference_from' => '8',   'reference_to' => '61',  'unit' => 'U/L'],
                ['name' => 'Total Bilirubin',    'reference_from' => '0.2', 'reference_to' => '1.2', 'unit' => 'mg/dL'],
                ['name' => 'Direct Bilirubin',   'reference_from' => '0',   'reference_to' => '0.3', 'unit' => 'mg/dL'],
                ['name' => 'Indirect Bilirubin', 'reference_from' => '0.1', 'reference_to' => '0.8', 'unit' => 'mg/dL'],
                ['name' => 'Total Protein',      'reference_from' => '6.0', 'reference_to' => '8.3', 'unit' => 'g/dL'],
                ['name' => 'Albumin',            'reference_from' => '3.5', 'reference_to' => '5.0', 'unit' => 'g/dL'],
                ['name' => 'Globulin',           'reference_from' => '2.3', 'reference_to' => '3.5', 'unit' => 'g/dL'],
            ]);
        }

        // ── RFT / KFT / Renal/Kidney Function ───────────────────────────
        if (preg_match('/\b(rft|kft)\b|renal function|kidney function/', $text)) {
            return $wrap([
                ['name' => 'Serum Creatinine', 'reference_from' => '0.6', 'reference_to' => '1.2', 'unit' => 'mg/dL'],
                ['name' => 'Blood Urea',       'reference_from' => '15',  'reference_to' => '40',  'unit' => 'mg/dL'],
                ['name' => 'Uric Acid',        'reference_from' => '3.5', 'reference_to' => '7.2', 'unit' => 'mg/dL'],
                ['name' => 'eGFR',             'reference_from' => '60',  'reference_to' => '',    'unit' => 'mL/min/1.73m²'],
            ]);
        }

        // ── Lipid Profile ─────────────────────────────────────────────────
        if (str_contains($text, 'lipid profile') || str_contains($text, 'lipid panel')) {
            return $wrap([
                ['name' => 'Total Cholesterol', 'reference_from' => '0',  'reference_to' => '200', 'unit' => 'mg/dL'],
                ['name' => 'Triglycerides',     'reference_from' => '0',  'reference_to' => '150', 'unit' => 'mg/dL'],
                ['name' => 'HDL Cholesterol',   'reference_from' => '40', 'reference_to' => '',    'unit' => 'mg/dL'],
                ['name' => 'LDL Cholesterol',   'reference_from' => '0',  'reference_to' => '100', 'unit' => 'mg/dL'],
                ['name' => 'VLDL Cholesterol',  'reference_from' => '0',  'reference_to' => '30',  'unit' => 'mg/dL'],
            ]);
        }

        // ── TFT / Thyroid Function Test ───────────────────────────────────
        if (preg_match('/\btft\b|thyroid function test/', $text)) {
            return $wrap([
                ['name' => 'TSH', 'reference_from' => '0.27', 'reference_to' => '4.20', 'unit' => 'µIU/mL'],
                ['name' => 'T3',  'reference_from' => '0.8',  'reference_to' => '2.0',  'unit' => 'ng/mL'],
                ['name' => 'T4',  'reference_from' => '5.0',  'reference_to' => '12.0', 'unit' => 'µg/dL'],
                ['name' => 'FT3', 'reference_from' => '2.3',  'reference_to' => '4.2',  'unit' => 'pg/mL'],
                ['name' => 'FT4', 'reference_from' => '0.93', 'reference_to' => '1.70', 'unit' => 'ng/dL'],
            ]);
        }

        // ── Coagulation Profile ───────────────────────────────────────────
        if (preg_match('/coagulation profile|pt\/aptt|bleeding.?clotting/', $text)) {
            return $wrap([
                ['name' => 'Prothrombin Time (PT)', 'reference_from' => '11', 'reference_to' => '14', 'unit' => 'seconds'],
                ['name' => 'APTT',                  'reference_from' => '25', 'reference_to' => '35', 'unit' => 'seconds'],
                ['name' => 'INR',                   'reference_from' => '0.8','reference_to' => '1.2','unit' => ''],
                ['name' => 'Bleeding Time',         'reference_from' => '2',  'reference_to' => '4',  'unit' => 'minutes'],
                ['name' => 'Clotting Time',         'reference_from' => '4',  'reference_to' => '10', 'unit' => 'minutes'],
            ]);
        }

        // ── Electrolytes ──────────────────────────────────────────────────
        if (str_contains($text, 'electrolyte')) {
            return $wrap([
                ['name' => 'Sodium (Na+)',       'reference_from' => '135', 'reference_to' => '145', 'unit' => 'mmol/L'],
                ['name' => 'Potassium (K+)',     'reference_from' => '3.5', 'reference_to' => '5.2', 'unit' => 'mmol/L'],
                ['name' => 'Chloride (Cl-)',     'reference_from' => '96',  'reference_to' => '108', 'unit' => 'mmol/L'],
                ['name' => 'Bi-Carbonate (HCO3-)','reference_from'=> '22',  'reference_to' => '28',  'unit' => 'mmol/L'],
                ['name' => 'Calcium',            'reference_from' => '8.5', 'reference_to' => '10.5','unit' => 'mg/dL'],
                ['name' => 'Phosphorus',         'reference_from' => '2.5', 'reference_to' => '4.5', 'unit' => 'mg/dL'],
                ['name' => 'Magnesium',          'reference_from' => '1.7', 'reference_to' => '2.2', 'unit' => 'mEq/L'],
            ]);
        }

        // ── Iron Studies ──────────────────────────────────────────────────
        if (preg_match('/iron stud|iron profile|tibc/', $text)) {
            return $wrap([
                ['name' => 'Serum Iron',            'reference_from' => '60',  'reference_to' => '170', 'unit' => 'µg/dL'],
                ['name' => 'TIBC',                  'reference_from' => '250', 'reference_to' => '370', 'unit' => 'µg/dL'],
                ['name' => 'Transferrin Saturation','reference_from' => '20',  'reference_to' => '50',  'unit' => '%'],
                ['name' => 'Serum Ferritin',        'reference_from' => '12',  'reference_to' => '300', 'unit' => 'ng/mL'],
            ]);
        }

        // ── Cardiac Enzymes / Markers ─────────────────────────────────────
        if (preg_match('/cardiac enzyme|cardiac marker|cardiac profile/', $text)) {
            return $wrap([
                ['name' => 'Troponin I', 'reference_from' => '0',  'reference_to' => '0.04','unit' => 'ng/mL'],
                ['name' => 'CK-MB',     'reference_from' => '0',  'reference_to' => '25',  'unit' => 'U/L'],
                ['name' => 'CPK',       'reference_from' => '22', 'reference_to' => '198', 'unit' => 'U/L'],
                ['name' => 'LDH',       'reference_from' => '135','reference_to' => '225', 'unit' => 'U/L'],
            ]);
        }

        // ── Hormone Profile / Fertility ───────────────────────────────────
        if (preg_match('/hormone profile|fertility profile|reproductive/', $text)) {
            return $wrap([
                ['name' => 'FSH',         'reference_from' => '1.5', 'reference_to' => '12.4', 'unit' => 'mIU/mL'],
                ['name' => 'LH',          'reference_from' => '1.7', 'reference_to' => '15.0', 'unit' => 'mIU/mL'],
                ['name' => 'Prolactin',   'reference_from' => '4.0', 'reference_to' => '23.0', 'unit' => 'ng/mL'],
                ['name' => 'Estradiol',   'reference_from' => '30',  'reference_to' => '400',  'unit' => 'pg/mL'],
                ['name' => 'Progesterone','reference_from' => '0.3', 'reference_to' => '1.2',  'unit' => 'ng/mL'],
                ['name' => 'Testosterone','reference_from' => '300', 'reference_to' => '1000', 'unit' => 'ng/dL'],
            ]);
        }

        // ── Semen Analysis ────────────────────────────────────────────────
        if (preg_match('/semen|sperm|seminogram/', $text)) {
            return $wrap([
                ['name' => 'Volume',               'reference_from' => '1.5','reference_to' => '5.0', 'unit' => 'mL'],
                ['name' => 'pH',                   'reference_from' => '7.2','reference_to' => '8.0', 'unit' => ''],
                ['name' => 'Sperm Count',          'reference_from' => '15', 'reference_to' => '',    'unit' => 'million/mL'],
                ['name' => 'Total Motility',       'reference_from' => '40', 'reference_to' => '',    'unit' => '%'],
                ['name' => 'Progressive Motility', 'reference_from' => '32', 'reference_to' => '',    'unit' => '%'],
                ['name' => 'Normal Morphology',    'reference_from' => '4',  'reference_to' => '',    'unit' => '%'],
                ['name' => 'Liquefaction Time',    'reference_from' => '15', 'reference_to' => '30',  'unit' => 'minutes'],
            ]);
        }

        $templates = [
            'lipid' => [
                ['name' => 'S. Cholesterol (Total)', 'reference_to' => '<200', 'unit' => 'mg/dl'],
                ['name' => 'S. Cholesterol (HDL)', 'reference_to' => '<40', 'unit' => 'mg/dl'],
                ['name' => 'S. Cholesterol (LDL)', 'reference_to' => '<150', 'unit' => 'mg/dl'],
                ['name' => 'Triglycerides', 'reference_to' => '190', 'unit' => 'mg/dl'],
            ],
            'cholesterol' => [
                ['name' => 'S. Cholesterol (Total)', 'reference_to' => '<200', 'unit' => 'mg/dl'],
            ],
            'electrolyte' => [
                ['name' => 'Sodium (Na+)', 'reference_from' => '135', 'reference_to' => '145', 'unit' => 'mmol/l'],
                ['name' => 'Potassium (K+)', 'reference_from' => '3.5', 'reference_to' => '5.2', 'unit' => 'mmol/l'],
                ['name' => 'Chloride (Cl-)', 'reference_from' => '96', 'reference_to' => '108', 'unit' => 'mmol/l'],
                ['name' => 'Bi-Carbonate (HCO3-)', 'reference_from' => '22', 'reference_to' => '28', 'unit' => 'mmol/l'],
            ],
            'differential count' => $differentialTemplate,
            'hematocrit' => $hctTemplate,
            'hct/pcv' => $hctTemplate,
            'pcv' => $hctTemplate,
            'platelet count' => $plateletTemplate,
            'platelet' => $plateletTemplate,
            'complete blood count' => $cbcTemplate,
            'cbc' => $cbcTemplate,
            'hematology' => $cbcTemplate,
            'haematology' => $cbcTemplate,
            'tsh' => [
                ['name' => 'TSH', 'reference_from' => '0.27', 'reference_to' => '4.20', 'unit' => 'µIU/ml'],
            ],
        ];

        foreach ($templates as $keyword => $parameters) {
            if (!str_contains($text, $keyword)) {
                continue;
            }

            return array_map(function (array $parameter) {
                return array_merge($parameter, [
                    'id' => 'gen:' . $parameter['name'],
                    'generated' => true,
                ]);
            }, $parameters);
        }

        return [];
    }

    /**
     * Auto-detect the medical function/category label for a test based on its name.
     * Returns a human-readable label like "Liver Function Test", "Thyroid Function", etc.
     * Used as a fallback when the test's report_function field is empty.
     */
    private function autoDetectTestFunction(string $name, string $category = ''): string
    {
        $n = strtolower(trim($name));
        $c = strtolower(trim($category));
        $ctx = $n . ' ' . $c;

        // ── Liver Function Test ───────────────────────────────────────────
        if (preg_match('/\b(sgpt|sgot|alt\b|ast\b|alp\b|alkaline phosphatase|ggt|ggtp|gamma.?gt|bilirubin|bili\b|albumin|globulin|total protein|serum protein|prothrombin|liver function|lft\b)\b/i', $ctx)) {
            return 'Liver Function Test';
        }

        // ── Kidney / Renal Function Test ──────────────────────────────────
        if (preg_match('/\b(creatinine|s\.creatinine|urea\b|bun\b|blood urea|uric acid|egfr|cystatin|renal function|kidney function|kft\b|rft\b)\b/i', $ctx)) {
            return 'Kidney Function Test';
        }

        // ── Blood Sugar / Diabetes ────────────────────────────────────────
        if (preg_match('/\b(glucose|fbs\b|rbs\b|ppbs\b|hba1c|hb ?a1c|glycated|blood sugar|fasting sugar|oral glucose|ogtt|gtt\b|diabetes)\b/i', $ctx)) {
            return 'Blood Sugar / Diabetes';
        }

        // ── Lipid Profile ─────────────────────────────────────────────────
        if (preg_match('/\b(cholesterol|triglyceride|hdl\b|ldl\b|vldl\b|lipid profile|lipid panel|non.?hdl)\b/i', $ctx)) {
            return 'Lipid Profile';
        }

        // ── Complete Blood Count ──────────────────────────────────────────
        if (preg_match('/\b(cbc\b|complete blood count|hemoglobin|haemoglobin|hgb\b|hb\b|wbc\b|rbc\b|platelet|thrombocyte|hematocrit|haematocrit|pcv\b|mcv\b|mch\b|mchc\b|reticulocyte|differential count|blood count|esr\b)\b/i', $ctx)) {
            return 'Complete Blood Count';
        }

        // ── Thyroid Function ──────────────────────────────────────────────
        if (preg_match('/\b(tsh\b|t3\b|t4\b|ft3\b|ft4\b|free t3|free t4|anti.?tpo|thyroglobulin|thyroid stimulating|thyroid function|tft\b|thyroid)\b/i', $ctx)) {
            return 'Thyroid Function Test';
        }

        // ── Cardiac Markers ───────────────────────────────────────────────
        if (preg_match('/\b(troponin|ck.?mb|cpk\b|ldh\b|bnp\b|nt.?probnp|myoglobin|cardiac enzyme|cardiac marker|pro.?bnp)\b/i', $ctx)) {
            return 'Cardiac Marker';
        }

        // ── Coagulation ───────────────────────────────────────────────────
        if (preg_match('/\b(prothrombin time|pt\b|aptt\b|ptt\b|inr\b|fibrinogen|d.?dimer|bleeding time|clotting time|coagulation|factor viii|factor ix)\b/i', $ctx)) {
            return 'Coagulation Test';
        }

        // ── Tumor Markers ─────────────────────────────────────────────────
        if (preg_match('/\b(psa\b|afp\b|cea\b|ca.?125|ca.?19.?9|ca.?15.?3|beta.?hcg|b.?hcg|tumor marker|tumour marker|ca 125|ca 19|ca 15)\b/i', $ctx)) {
            return 'Tumor Marker';
        }

        // ── Hormones / Reproductive ───────────────────────────────────────
        if (preg_match('/\b(cortisol|insulin\b|fsh\b|lh\b|estrogen|estradiol|testosterone|prolactin|dhea|progesterone|amh\b|anti.?mullerian|androgen|hormone|hcg\b|beta hcg)\b/i', $ctx)) {
            return 'Hormone Test';
        }

        // ── Inflammation / Acute Phase ────────────────────────────────────
        if (preg_match('/\b(c.?reactive protein|crp\b|procalcitonin|interleukin|il.?6|esr\b|erythrocyte sedimentation|ferritin|serum ferritin)\b/i', $ctx)) {
            return 'Inflammation Marker';
        }

        // ── Iron Studies ──────────────────────────────────────────────────
        if (preg_match('/\b(serum iron|tibc\b|transferrin|iron study|iron deficiency|iron profile)\b/i', $ctx)) {
            return 'Iron Studies';
        }

        // ── Vitamins / Minerals ───────────────────────────────────────────
        if (preg_match('/\b(vitamin d|25.?oh|25.?hydroxy|vitamin b12|vit b12|cobalamin|folate|folic acid|vitamin b9|zinc\b|magnesium|calcium\b|phosphorus|parathyroid|pth\b)\b/i', $ctx)) {
            return 'Vitamin / Mineral Test';
        }

        // ── Electrolytes ──────────────────────────────────────────────────
        if (preg_match('/\b(sodium\b|potassium\b|chloride\b|bicarbonate\b|electrolyte|na\+?\/k\+?|serum na|serum k\b)\b/i', $ctx)) {
            return 'Electrolyte Test';
        }

        // ── Weil-Felix / Well-Felix (Rickettsial) ────────────────────────
        if (preg_match('/weil.?felix|well.?felix/i', $ctx)) {
            return 'Serological Test';
        }

        // ── Widal (Enteric Fever) ─────────────────────────────────────────
        if (preg_match('/\bwidal\b/i', $ctx)) {
            return 'Serological Test';
        }

        // ── Serology / Infection ──────────────────────────────────────────
        if (preg_match('/\b(vdrl\b|tpha\b|hiv\b|hbsag\b|hepatitis b|hepatitis c|hcv\b|hbs\b|dengue|malaria|widal\b|aslo\b|aso\b|ra factor|rpr\b|torch\b|cmv\b|ebv\b|toxoplasma|syphilis|serology|antibody titer)\b/i', $ctx)) {
            return 'Serology / Infection Test';
        }

        // ── Urine Analysis ────────────────────────────────────────────────
        if (preg_match('/\b(urine r\/e|urine m\/e|urinalysis|urine analysis|urine culture|urine protein|microalbumin|urine routine|urine exam)\b/i', $ctx)
            || (str_contains($n, 'urine') && preg_match('/\b(r\/?e\b|m\/?e\b|routine|exam|analysis|culture)\b/i', $n))) {
            return 'Urine Analysis';
        }

        // ── Stool Analysis ────────────────────────────────────────────────
        if (preg_match('/\b(stool r\/e|stool m\/e|stool culture|stool obt|stool occult|stool exam|stool routine|stool analysis|faecal|fecal)\b/i', $ctx)
            || (str_contains($n, 'stool') && preg_match('/\b(r\/?e\b|m\/?e\b|routine|exam|culture|occult)\b/i', $n))) {
            return 'Stool Analysis';
        }

        // ── Microbiology / Culture & Sensitivity ─────────────────────────
        if (preg_match('/\b(culture|sensitivity|c\/s\b|gram stain|afb\b|mrsa\b|blood culture|sputum culture|wound culture|pus culture|throat culture)\b/i', $ctx)) {
            return 'Microbiology';
        }

        // ── Immunology / Autoimmune ───────────────────────────────────────
        if (preg_match('/\b(ana\b|anti.?dsdna|anca\b|anti.?ccp|anti.?ccp|rheumatoid factor|complement c3|complement c4|immunoglobulin|igg|igm|iga|autoimmune)\b/i', $ctx)) {
            return 'Immunology / Autoimmune';
        }

        // ── Pancreatic Function ───────────────────────────────────────────
        if (preg_match('/\b(amylase|lipase|pancreatic)\b/i', $ctx)) {
            return 'Pancreatic Function';
        }

        // ── Drug Monitoring ───────────────────────────────────────────────
        if (preg_match('/\b(digoxin|phenytoin|lithium|valproate|vancomycin|drug level|therapeutic drug|tdm\b)\b/i', $ctx)) {
            return 'Drug Level Monitoring';
        }

        // ── Semen Analysis ────────────────────────────────────────────────
        if (preg_match('/\b(semen|sperm|seminogram|spermiogram)\b/i', $ctx)) {
            return 'Semen Analysis';
        }

        // ── Ultrasonography ───────────────────────────────────────────────
        if (preg_match('/\b(usg\b|ultrasonogram|ultrasonography|ultrasound)\b/i', $ctx)) {
            return 'Ultrasonography';
        }

        // ── X-Ray ─────────────────────────────────────────────────────────
        if (preg_match('/\b(x.?ray|xray\b|radiograph|cxr\b)\b/i', $ctx)) {
            return 'Radiology / X-Ray';
        }

        // ── ECG ───────────────────────────────────────────────────────────
        if (preg_match('/\b(ecg\b|ekg\b|electrocardiogram|electrocardiograph)\b/i', $ctx)) {
            return 'ECG';
        }

        // ── Haematology (generic) ─────────────────────────────────────────
        if (str_contains($c, 'hematology') || str_contains($c, 'haematology')) {
            return 'Haematology';
        }

        // ── Biochemistry (generic) ────────────────────────────────────────
        if (str_contains($c, 'biochem')) {
            return 'Biochemistry';
        }

        // ── Microbiology (generic) ────────────────────────────────────────
        if (str_contains($c, 'micro') || str_contains($c, 'biology')) {
            return 'Microbiology';
        }

        return '';
    }

    private function suggestNormalRangeByTestName(BillItem $item): ?string
    {
        $name = strtolower(trim((string) $item->item_name));
        $category = strtolower(trim((string) $item->category));
        $context = trim($name . ' ' . $category);

        if ($context === '') {
            return null;
        }

        // Curated quick suggestions for common tests.
        // Uses regex with word boundaries (\b) to prevent partial matches
        // (e.g. "ft4" must NOT match the "t4" rule, "ft3" must NOT match "t3").
        $rules = [
            // ── Thyroid ──────────────────────────────────────────────────
            // Free T3/T4 must come BEFORE plain T3/T4 to avoid substring collision.
            ['pattern' => '/\bfree\s*t3\b|\bft3\b/i',  'range' => '2.3 - 4.2 pg/mL'],
            ['pattern' => '/\bfree\s*t4\b|\bft4\b/i',  'range' => '0.93 - 1.70 ng/dL'],
            ['pattern' => '/\btsh\b/i',                 'range' => '0.4 - 4.0 mIU/L'],
            // Plain T3/T4 only when NOT preceded by 'f' (i.e. not FT3/FT4)
            ['pattern' => '/(?<![a-z])t3\b/i',          'range' => '0.8 - 2.0 ng/mL'],
            ['pattern' => '/(?<![a-z])t4\b/i',          'range' => '5.0 - 12.0 ug/dL'],
            // ── Haematology ───────────────────────────────────────────────
            ['pattern' => '/\bhemoglobin\b|\bhaemoglobin\b|\bhgb\b/i', 'range' => 'Male: 13 - 17 g/dL | Female: 12 - 15 g/dL'],
            ['pattern' => '/\bwbc\b|\bwhite blood cell\b/i',           'range' => '4,000 - 11,000 /uL'],
            ['pattern' => '/\brbc\b|\bred blood cell\b/i',             'range' => 'Male: 4.5 - 5.9 M/uL | Female: 4.1 - 5.1 M/uL'],
            ['pattern' => '/\bplatelet\b/i',                           'range' => '150,000 - 450,000 /uL'],
            ['pattern' => '/\besr\b/i',                                'range' => 'Male: 0 - 15 mm/hr | Female: 0 - 20 mm/hr'],
            // ── Blood Sugar ───────────────────────────────────────────────
            ['pattern' => '/\bfbs\b|\bfasting blood sugar\b/i',        'range' => '70 - 99 mg/dL'],
            ['pattern' => '/\brbs\b|\brandom blood sugar\b/i',         'range' => '70 - 140 mg/dL'],
            ['pattern' => '/\bppbs\b/i',                               'range' => '< 140 mg/dL'],
            ['pattern' => '/\bglucose\b/i',                            'range' => '70 - 99 mg/dL'],
            ['pattern' => '/\bhba1c\b|\bhb\s*a1c\b/i',                'range' => '4.0 - 5.6 %'],
            // ── Kidney ────────────────────────────────────────────────────
            ['pattern' => '/\bs\.?\s*creatinine\b|\bserum creatinine\b|\bcreatinine\b/i', 'range' => '0.6 - 1.2 mg/dL'],
            ['pattern' => '/\burea\b|\bbun\b/i',                       'range' => '15 - 40 mg/dL'],
            ['pattern' => '/\buric acid\b/i',                          'range' => 'Male: 3.5 - 7.2 mg/dL | Female: 2.6 - 6.0 mg/dL'],
            // ── Liver ─────────────────────────────────────────────────────
            ['pattern' => '/\balt\b|\bsgpt\b/i',                       'range' => '7 - 56 U/L'],
            ['pattern' => '/\bast\b|\bsgot\b/i',                       'range' => '10 - 40 U/L'],
            ['pattern' => '/\balp\b|\balkaline phosphatase\b/i',       'range' => '44 - 147 U/L'],
            ['pattern' => '/\bggt\b|\bggtp\b/i',                       'range' => 'Male: 8 - 61 U/L | Female: 5 - 36 U/L'],
            ['pattern' => '/\bbilirubin\b/i',                          'range' => '0.2 - 1.2 mg/dL'],
            ['pattern' => '/\balbumin\b/i',                            'range' => '3.5 - 5.0 g/dL'],
            // ── Lipid ─────────────────────────────────────────────────────
            ['pattern' => '/\bldl\b/i',                                'range' => '< 100 mg/dL'],
            ['pattern' => '/\bhdl\b/i',                                'range' => 'Male: > 40 mg/dL | Female: > 50 mg/dL'],
            ['pattern' => '/\bvldl\b/i',                               'range' => '< 30 mg/dL'],
            ['pattern' => '/\btriglyceride/i',                         'range' => '< 150 mg/dL'],
            ['pattern' => '/\bcholesterol\b/i',                        'range' => '< 200 mg/dL'],
            // ── Misc ──────────────────────────────────────────────────────
            ['pattern' => '/\becg\b|\bekg\b/i',                        'range' => 'Heart Rate: 60 - 100 bpm'],
            ['pattern' => '/\busg\b|\bultrasonogram|\bultrasonography/i', 'range' => 'Impression based on clinical and sonographic findings'],
        ];

        foreach ($rules as $rule) {
            if (preg_match($rule['pattern'], $context)) {
                return $rule['range'];
            }
        }

        return null;
    }

    private function extractTrailingUnit(string $input): string
    {
        $text = trim((string) $input);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/[–—]/u', '-', $text);
        if (preg_match('/(?:^|[\s\-])([A-Za-zµμ%\/]+(?:\/[A-Za-zµμ%\/]+)?)\s*$/u', $text, $matches)) {
            return trim((string) $matches[1]);
        }

        if (preg_match('/([A-Za-zµμ%\/]+(?:\/[A-Za-zµμ%\/]+)?)\s*$/u', $text, $matches)) {
            return trim((string) $matches[1]);
        }

        return '';
    }

    private function resolveReportingDepartment(BillItem $billItem, ?string $reportTitle = null): ?string
    {
        $category = strtolower(trim((string) $billItem->category));
        if (str_contains($category, 'patholog')) {
            return 'pathology';
        }

        if ($this->isUltrasonogramBillItem($billItem, $reportTitle)) {
            return 'ultrasound';
        }

        if (
            str_contains($category, 'radiolog')
            || str_contains($category, 'xray')
            || str_contains($category, 'x-ray')
            || str_contains($category, 'x ray')
        ) {
            return 'xray';
        }

        return null;
    }

    private function resolveMachineInfoByCategory(BillItem $billItem, array $attendanceOptions, string $fallbackText): array
    {
        $configured = data_get($attendanceOptions, 'reporting.machine_info_by_category', []);
        $configured = is_array($configured) ? $configured : [];

        $categoryCandidates = [];

        if (!empty($billItem->category)) {
            $categoryCandidates[] = (string) $billItem->category;
        }

        if (!empty($billItem->item_id)) {
            try {
                $test = Test::query()->with(['mainCategory', 'pathologyCategory'])->find($billItem->item_id);
                if ($test) {
                    $categoryCandidates[] = (string) ($test->mainCategory?->name ?? '');
                    $categoryCandidates[] = (string) ($test->pathologyCategory?->name ?? '');
                }
            } catch (\Throwable $_) {
                // ignore
            }
        }

        $categoryCandidates[] = $this->resolveReportTitle($billItem);

        $categoryNames = [];
        foreach ($categoryCandidates as $candidate) {
            $trimmed = trim((string) $candidate);
            if ($trimmed === '') {
                continue;
            }
            $categoryNames[] = $trimmed;
        }

        $categoryNames = array_values(array_unique($categoryNames));

        foreach ($categoryNames as $categoryName) {
            $normalizedKey = $this->normalizeMachineInfoCategoryKey($categoryName);
            if ($normalizedKey === '') {
                continue;
            }

            if (isset($configured[$categoryName])) {
                $item = $configured[$categoryName];
                if (is_array($item)) {
                    $text = trim((string) ($item['text'] ?? ''));
                    $enabled = $item['enabled'] ?? true;
                    if (is_string($enabled)) {
                        $enabled = in_array(strtolower($enabled), ['1', 'true', 'yes', 'on'], true);
                    }
                    return ['text' => $text !== '' ? $text : $fallbackText, 'enabled' => (bool) $enabled];
                }
            }

            foreach ($configured as $configuredKey => $item) {
                if (!is_array($item)) {
                    continue;
                }

                if ($this->normalizeMachineInfoCategoryKey((string) $configuredKey) === $normalizedKey) {
                    $text = trim((string) ($item['text'] ?? ''));
                    $enabled = $item['enabled'] ?? true;
                    if (is_string($enabled)) {
                        $enabled = in_array(strtolower($enabled), ['1', 'true', 'yes', 'on'], true);
                    }
                    return ['text' => $text !== '' ? $text : $fallbackText, 'enabled' => (bool) $enabled];
                }
            }
        }

        $globalEnabled = data_get($attendanceOptions, 'reporting.machine_info_line_enabled', true);
        $globalEnabled = filter_var($globalEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($globalEnabled === null) {
            $globalEnabled = true;
        }

        return ['text' => $fallbackText, 'enabled' => (bool) $globalEnabled];
    }

    private function normalizeMachineInfoCategoryKey(string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized);
        $normalized = trim((string) $normalized);

        if ($normalized === '') {
            return '';
        }

        if (str_contains($normalized, 'hemat') || str_contains($normalized, 'haemat')) {
            return 'hematology';
        }

        if (str_contains($normalized, 'biochem')) {
            return 'biochemistry';
        }

        if (str_contains($normalized, 'hormone')) {
            return 'hormone analysis';
        }

        if (str_contains($normalized, 'cbc')) {
            return 'cbc';
        }

        if (str_contains($normalized, 'pathology')) {
            return 'pathology';
        }

        if (str_contains($normalized, 'micro')) {
            return 'microbiology';
        }

        if (str_contains($normalized, 'urine')) {
            return 'urine';
        }

        return $normalized;
    }

    private function isPathologyLikeCategory(string $category): bool
    {
        $normalized = strtolower(trim($category));
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'pathology')
            || str_contains($normalized, 'micro')
            || str_contains($normalized, 'biology')
            || str_contains($normalized, 'hematology')
            || str_contains($normalized, 'haematology')
            || str_contains($normalized, 'cbc');
    }

    private function looksLikeCbcTest($item): bool
    {
        $combined = strtolower(trim((string) (($item->item_name ?? '') . ' ' . ($item->category ?? '') . ' ' . ($item->report_function ?? ''))));
        return str_contains($combined, 'cbc')
            || str_contains($combined, 'complete blood count')
            || str_contains($combined, 'hematology')
            || str_contains($combined, 'haematology')
            || str_contains($combined, 'hemogram')
            || str_contains($combined, 'haemogram')
            || str_contains($combined, 'blood count');
    }

    private function resolveDepartmentCategories(): array
    {
        $departmentName = strtolower(trim((string) data_get(auth('admin')->user(), 'details.department.name', '')));
        $designationName = strtolower(trim((string) data_get(auth('admin')->user(), 'details.designation.name', '')));
        $scopeText = trim($departmentName . ' ' . $designationName);

        if (str_contains($scopeText, 'pathology') || str_contains($scopeText, 'patholog')) {
            return $this->categoryNamesMatching(['pathology', 'micro', 'biology', 'hematology', 'haematology', 'cbc'], ['Pathology', 'Hematology', 'CBC']);
        }

        if (str_contains($scopeText, 'radiology') || str_contains($scopeText, 'radiolog')) {
            return $this->categoryNamesMatching(['radiology', 'radiolog', 'xray', 'x-ray'], ['Radiology']);
        }

        if (
            str_contains($scopeText, 'ultrasonogram')
            || str_contains($scopeText, 'ultrasonography')
            || str_contains($scopeText, 'usg')
        ) {
            return ['Ultrasonogram', 'Ultrasonography'];
        }

        if (str_contains($scopeText, 'ecg') || str_contains($scopeText, 'e.c.g')) {
            return $this->categoryNamesMatching(['ecg', 'echo'], ['ECG']);
        }

        return $this->categoryNamesMatching(
            ['pathology', 'micro', 'biology', 'radiology', 'radiolog', 'ultrasound', 'ultrasonogram', 'ultrasonography', 'ecg', 'echo'],
            ['Pathology', 'Radiology', 'Ultrasonogram', 'Ultrasonography', 'ECG']
        );
    }

    private function categoryNamesMatching(array $keywords, array $fallback): array
    {
        $names = TestCategory::query()
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

        return array_values(array_unique(array_merge($fallback, $names), SORT_STRING));
    }

    private function parseNumeric(?string $s): ?float
    {
        if ($s === null) return null;
        $s = trim((string) $s);
        if ($s === '') return null;
        // remove commas for thousands
        $s = str_replace(',', '', $s);
        // find the first numeric occurrence (integer or float)
        if (preg_match('/-?\d+(?:\.\d+)?/', $s, $m)) {
            return (float) $m[0];
        }
        return null;
    }

    private function resolvePublicStorageImageDataUri(?string $path): string
    {
        $rawPath = trim((string) $path);
        if ($rawPath === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $rawPath);
        $normalized = ltrim($normalized, '/');

        $candidates = array_values(array_unique(array_filter([
            $normalized,
            preg_replace('#^storage/#i', '', $normalized),
            preg_replace('#^public/#i', '', $normalized),
            preg_replace('#^public/storage/#i', '', $normalized),
        ])));

        $resolvedPath = null;
        foreach ($candidates as $candidate) {
            $candidate = ltrim($candidate, '/');
            if (Storage::disk('public')->exists($candidate)) {
                $resolvedPath = $candidate;
                break;
            }
        }

        if ($resolvedPath === null) {
            return '';
        }

        $extension = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($resolvedPath));
    }

    private function makeSignatureBackgroundTransparent(string $dataUri): string
    {
        if (!extension_loaded('gd') || preg_match('#^data:image/(?:png|jpe?g|gif|webp);base64,#i', $dataUri) !== 1) {
            return $dataUri;
        }

        $encodedImage = substr($dataUri, strpos($dataUri, ',') + 1);
        $imageBytes = base64_decode($encodedImage, true);
        $source = $imageBytes === false ? false : @imagecreatefromstring($imageBytes);
        if (!$source instanceof \GdImage) {
            return $dataUri;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $transparentImage = imagecreatetruecolor($width, $height);
        imagealphablending($transparentImage, false);
        imagesavealpha($transparentImage, true);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($source, $x, $y);
                $sourceAlpha = 1 - (($pixel >> 24) & 0x7F) / 127;
                $red = ($pixel >> 16) & 0xFF;
                $green = ($pixel >> 8) & 0xFF;
                $blue = $pixel & 0xFF;
                $minimumChannel = min($red, $green, $blue);
                $maximumChannel = max($red, $green, $blue);
                $colorSpread = $maximumChannel - $minimumChannel;

                if ($sourceAlpha <= 0 || ($minimumChannel >= 230 && $colorSpread < 20)) {
                    $color = imagecolorallocatealpha($transparentImage, 255, 255, 255, 127);
                } elseif ($minimumChannel > 205 && $colorSpread < 20) {
                    $inkOpacity = (255 - $minimumChannel) / 50;
                    $alpha = (int) round(127 * (1 - $sourceAlpha * $inkOpacity));
                    $red = (int) round(max(0, min(255, ($red - (1 - $inkOpacity) * 255) / $inkOpacity)));
                    $green = (int) round(max(0, min(255, ($green - (1 - $inkOpacity) * 255) / $inkOpacity)));
                    $blue = (int) round(max(0, min(255, ($blue - (1 - $inkOpacity) * 255) / $inkOpacity)));
                    $color = imagecolorallocatealpha($transparentImage, $red, $green, $blue, $alpha);
                } else {
                    $alpha = (int) round(127 * (1 - $sourceAlpha));
                    $color = imagecolorallocatealpha($transparentImage, $red, $green, $blue, $alpha);
                }

                imagesetpixel($transparentImage, $x, $y, $color);
            }
        }

        ob_start();
        imagepng($transparentImage);
        $transparentImageBytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($transparentImage);

        if (!is_string($transparentImageBytes) || $transparentImageBytes === '') {
            return $dataUri;
        }

        return 'data:image/png;base64,' . base64_encode($transparentImageBytes);
    }
}
