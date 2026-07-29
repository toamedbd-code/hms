<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BillItem;
use App\Models\Billing;
use App\Models\InvoiceDesign;
use App\Models\PathologyTestParameter;
use App\Models\BillItemParameterResult;
use App\Models\RadiologyTest;
use App\Models\Test;
use App\Models\WebSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
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
        $this->middleware('permission:reporting|pathology-reporting|ultrasound-reporting|xray-reporting');
    }

    /**
     * Pathology-specific reporting list (only Pathology items).
     */
    public function pathologyIndex(Request $request)
    {
        $allowedCategories = ['Pathology'];
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
        $allowedCategories = ['Ultrasound', 'Ultrasonogram', 'Ultrasonography', 'Radiology'];
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
        $allowedCategories = ['Radiology'];
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
                        $q2->where('item_name', 'like', '%xray%')
                            ->orWhere('item_name', 'like', '%x-ray%')
                            ->orWhere('item_name', 'like', '%radiograph%')
                            ->orWhere('item_name', 'like', '%x ray%');
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
                            $q2->where('item_name', 'like', '%xray%')
                                ->orWhere('item_name', 'like', '%x-ray%')
                                ->orWhere('item_name', 'like', '%radiograph%')
                                ->orWhere('item_name', 'like', '%x ray%');
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
            $allowedCategories = ['Pathology'];
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
            if (str_contains($nameText, 'ultrasound') || str_contains($nameText, 'ultrason') || str_contains($nameText, 'usg')) {
                $department = 'ultrasound';
            } elseif (str_contains($nameText, 'xray') || str_contains($nameText, 'x-ray') || str_contains($nameText, 'radiograph')) {
                $department = 'xray';
            } elseif (in_array('pathology', $cats, true)) {
                $department = 'pathology';
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
                if (str_contains($nameText, 'ultrasound') || str_contains($nameText, 'ultrason') || str_contains($nameText, 'usg')) {
                    $requestedDept = 'ultrasound';
                } elseif (str_contains($nameText, 'xray') || str_contains($nameText, 'x-ray') || str_contains($nameText, 'radiograph')) {
                    $requestedDept = 'xray';
                } elseif (in_array('pathology', $cats, true)) {
                    $requestedDept = 'pathology';
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
            $allowedCategories = ['Pathology'];
        } else {
            $allowedCategories = $this->resolveDepartmentCategories();
        }

        // Eager-load patient and fetch bill items explicitly so department filtering is enforced reliably
        $billing->load('patient');

            $rawItemsQuery = \App\Models\BillItem::query()
                ->where('billing_id', $billing->id)
                ->whereNotNull('sample_collected_at');

            if (!$includeReported) {
                $rawItemsQuery->whereNull('reported_at');
            }

            // apply category filter
            $rawItemsQuery->whereIn('category', $allowedCategories);

            // apply department-specific name filters for radiology-derived departments
            if ($requestedDept === 'ultrasound') {
                $rawItemsQuery->where(function ($q2) {
                    $q2->where('item_name', 'like', '%usg%')
                        ->orWhere('item_name', 'like', '%ultrasound%')
                        ->orWhere('item_name', 'like', '%ultrasonogram%')
                        ->orWhere('item_name', 'like', '%ultrasonography%');
                });
            } elseif ($requestedDept === 'xray') {
                $rawItemsQuery->where(function ($q2) {
                    $q2->where('item_name', 'like', '%xray%')
                        ->orWhere('item_name', 'like', '%x-ray%')
                        ->orWhere('item_name', 'like', '%radiograph%')
                        ->orWhere('item_name', 'like', '%x ray%');
                });
            }

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

            $defaultRange = $item->category === 'Pathology'
                ? ($normalRangeMap[$item->item_id] ?? null)
                : null;

            // Fallback AI-style range suggestion by test name/category when a predefined range is absent.
            if (empty($defaultRange)) {
                $defaultRange = $this->suggestNormalRangeByTestName($item);
            }

            // Attach parameter definitions only for Urine R/E / M/E pathology tests.
            if ($item->category === 'Pathology') {
                $iname = trim(strtolower((string) ($item->item_name ?? '')));
                $test = null;
                $tname = '';
                if (!empty($item->item_id)) {
                    $test = Test::find($item->item_id);
                    $tname = trim(strtolower($test?->test_name ?? $test?->test_short_name ?? ''));
                }

                $isUrineName = (str_contains($iname, 'urine') || preg_match('/\br\/?e\b/i', $iname) || preg_match('/\bm\/?e\b/i', $iname));
                $isUrineTest = ($tname !== '' && (str_contains($tname, 'urine') || preg_match('/\br\/?e\b/i', $tname) || preg_match('/\bm\/?e\b/i', $tname)));

                if ($isUrineName || $isUrineTest) {
                    $params = PathologyTestParameter::query()
                        ->where('pathology_test_id', $item->item_id)
                    ->where('pathology_test_id', $item->item_id)
                    ->with(['pathologyUnit:id,name', 'testParameter:id,name'])
                    ->orderBy('id')
                    ->get()
                    ->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'name' => trim((string) ($p->name ?? data_get($p, 'testParameter.name') ?? '')),
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
                                            'name' => trim((string) ($p->name ?? '')),
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

                // If still empty, synthesize a common Urine R/E parameter set for urine-related tests
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
                                    'id' => 'gen:' . preg_replace('/[^A-Za-z0-9_\-]/', '_', strtolower($name)),
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
                    } catch (\Throwable $_) {
                        // ignore
                    }
                }

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

                $genCounter = 0;
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

                // Convert to indexed groups and attach
                $item->parameter_groups = collect($grouped)
                    ->map(function ($rows, $title) {
                        return [
                            'title' => $title,
                            'parameters' => array_values($rows),
                        ];
                    })->values()->toArray();

                // Keep legacy flat array for backward compatibility
                    $item->parameters = $params->toArray();

                    // load any previously saved parameter results for this bill item
                    $existing = BillItemParameterResult::query()
                        ->where('bill_item_id', $item->id)
                        ->whereIn('pathology_test_parameter_id', array_filter($params->pluck('id')->all(), fn($v) => $v !== null))
                        ->pluck('value', 'pathology_test_parameter_id')
                        ->toArray();

                    $item->saved_parameter_values = $existing;
                } else {
                    // not a urine/R&E test: ensure no parameter groups/parameters are exposed
                    $item->parameters = [];
                    $item->parameter_groups = [];
                    $item->saved_parameter_values = [];
                }
            }

            $item->default_report_range = $defaultRange;

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
            $items = $items->filter(function ($it) {
                $name = strtolower((string) ($it->item_name ?? ''));
                $cat = strtolower((string) ($it->category ?? ''));
                $keywords = ['xray', 'x-ray', 'radiograph', 'radiography', 'x ray'];
                $matchesKeyword = false;
                foreach ($keywords as $kw) {
                    if (str_contains($name, $kw) || str_contains($cat, $kw)) {
                        $matchesKeyword = true;
                        break;
                    }
                }
                return $matchesKeyword;
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

        if ($file) {
            $path = $file->store('reports', 'public');
            $billItem->report_file = $path;
        }

        $billItem->report_note = $request->input('report_note');
        $billItem->report_range = $request->input('report_range');
        if (empty($billItem->reported_at)) {
            $billItem->reported_at = now();
            $billItem->reported_by = auth('admin')->id();
        }
        $billItem->save();

        // If saving from pathology or xray department, mark other matching
        // bill items in the same billing as reported as well so that the
        // department view shows all tests as ready.
        try {
            if ($requestedDept === 'pathology') {
                \App\Models\BillItem::query()
                    ->where('billing_id', $billItem->billing_id)
                    ->where('category', 'Pathology')
                    ->whereNull('reported_at')
                    ->update([
                        'reported_at' => now(),
                        'reported_by' => auth('admin')->id(),
                    ]);
            } elseif ($requestedDept === 'xray') {
                // Mark other radiology items that look like xray/radiograph
                \App\Models\BillItem::query()
                    ->where('billing_id', $billItem->billing_id)
                    ->where('category', 'Radiology')
                    ->whereNull('reported_at')
                    ->where(function ($q) {
                        $q->where('item_name', 'like', '%xray%')
                          ->orWhere('item_name', 'like', '%x-ray%')
                          ->orWhere('item_name', 'like', '%radiograph%')
                          ->orWhere('item_name', 'like', '%radiography%')
                          ->orWhere('item_name', 'like', '%x ray%');
                    })
                    ->update([
                        'reported_at' => now(),
                        'reported_by' => auth('admin')->id(),
                    ]);
            }
        } catch (\Throwable $_) {
            // ignore marking failures; main item already saved
        }

        // Persist structured parameter results (if provided)
        $parameterValues = $request->input('parameter_values', []);
        if (is_array($parameterValues)) {
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
                    'unit' => $paramModel ? trim((string) data_get($paramModel, 'pathologyUnit.name') ?? '') : null,
                ]);
            }
        }
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

    public function print(BillItem $billItem)
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

        $allowedCategories = $this->resolveDepartmentCategories();

        // If the item category is not within the user's resolved department scope,
        // allow printing when the user explicitly has a matching department permission
        // (e.g., `ultrasound-reporting`, `xray-reporting`, `pathology-reporting`),
        // or when they have the global `reporting`/`report-delivery` permission.
        $user = auth('admin')->user();
        $categoryAllowedByScope = in_array($billItem->category, $allowedCategories, true);

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

        if (empty($billItem->reported_at)) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'Report is not ready for print.');
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

        $layoutSettings = data_get($attendanceOptions, 'reporting.layout', []);
        if (!is_array($layoutSettings)) {
            $layoutSettings = [];
        }

        $signatureMarginTop = max((int) ($signatureSettings['margin_top'] ?? 160), 0);
        $signatureMarginLeft = max((int) ($signatureSettings['margin_left'] ?? 96), 0);
        $pageMarginTop = max((int) ($layoutSettings['page_margin_top'] ?? 0), 0);
        $pageMarginBottom = max((int) ($layoutSettings['page_margin_bottom'] ?? 0), 0);

        // header/footer heights (pixels). Defaults chosen to keep previous look:
        // default header ~115px (≈1.2in), footer default 70px.
        $reportHeaderHeight = max((int) ($layoutSettings['header_height'] ?? 115), 0);
        $reportFooterHeight = max((int) ($layoutSettings['footer_height'] ?? 70), 0);
        $reportHeaderHeight = max((int) ($layoutSettings['header_height'] ?? 115), 0); // px

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', 'billing')->first();
        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }
        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

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

        $resolveSignatureImage = function (?string $path): string {
            $rawPath = trim((string) $path);
            if ($rawPath === '') {
                return '';
            }

            $storagePath = storage_path('app/public/' . ltrim($rawPath, '/'));
            if (!file_exists($storagePath)) {
                return '';
            }

            $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => 'image/png',
            };

            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($storagePath));
        };

        $technologistSignature = $resolveSignatureImage((string) ($settings?->technologist_signature ?? ''));
        $sampleCollectedBySignature = $resolveSignatureImage((string) ($settings?->sample_collected_by_signature ?? ''));
        $pathologistSignature = $resolveSignatureImage((string) ($settings?->pathologist_signature ?? ''));
        $pathologistName = trim((string) ($settings?->pathologist_name ?? ''));
        $pathologistDesignation = trim((string) ($settings?->pathologist_designation ?? ''));

        $technologistNameSetting = trim((string) ($identitySettings['technologist_name'] ?? ''));
        $technologistDesignationSetting = trim((string) ($identitySettings['technologist_designation'] ?? ''));
        $sampleCollectedByNameSetting = trim((string) ($identitySettings['sample_collected_by_name'] ?? ''));
        $sampleCollectedByDesignationSetting = trim((string) ($identitySettings['sample_collected_by_designation'] ?? ''));

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

        $singleItemCategories = ['Radiology', 'Ultrasonogram', 'Ultrasonography', 'ECG'];
        $isSingleItemCategory = in_array((string) $billItem->category, $singleItemCategories, true);
        $isCbc = str_contains(strtolower((string) $billItem->item_name), 'cbc');

        if ($isSingleItemCategory || $isCbc) {
            $items = collect([$billItem]);
        } else {
            // Build base query for pathology items in the same billing
            $allItemsQuery = BillItem::query()
                ->where('billing_id', $billItem->billing_id)
                ->where('category', 'Pathology')
                ->whereNotNull('reported_at')
                ->where(function ($query) {
                    $query->whereNull('item_name')
                        ->orWhereRaw("LOWER(item_name) NOT LIKE '%cbc%'");
                });

            // If the primary bill item references a test, try to limit the group
            // to the same pathology test category to avoid mixing Biochemistry/Electrolyte sets.
            if (!empty($billItem->item_id)) {
                $primaryTest = Test::query()->find($billItem->item_id);
                $primaryCategoryId = $primaryTest?->test_category_id ?? null;
                if (!empty($primaryCategoryId)) {
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

            $chunks = $allItems->chunk(4);
            $items = $chunks->first(function ($chunk) use ($billItem) {
                return $chunk->contains('id', $billItem->id);
            }) ?? collect([$billItem]);
        }

        $reportTitle = $this->resolveReportTitle($billItem);
        $isUltrasonogramReport = $this->isUltrasonogramBillItem($billItem, $reportTitle);

        // If structured parameter results exist for items, prefer them for printing
        foreach ($items as $it) {
            $results = BillItemParameterResult::query()->where('bill_item_id', $it->id)->orderBy('id')->get();
            if ($results->isNotEmpty()) {
                $plainLines = [];
                $printRows = [];

                foreach ($results as $r) {
                    $param = null;
                    if (!empty($r->pathology_test_parameter_id)) {
                        $param = PathologyTestParameter::query()->find($r->pathology_test_parameter_id);
                    }

                    $name = trim((string) ($r->name ?? '')) ?: trim((string) data_get($r, 'pathologyTestParameter.name') ?? '');

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

                    $displayValue = $val . ($unit !== '' ? ' ' . $unit : '');

                    // Build normal range string from parameter definition when available
                    $from = $param ? trim((string) ($param->reference_from ?? '')) : '';
                    $to = $param ? trim((string) ($param->reference_to ?? '')) : '';
                    $paramUnit = $param ? trim((string) data_get($param, 'pathologyUnit.name') ?? '') : '';

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
                        'normal_range' => $rangeStr,
                        'is_out' => $isOut,
                        'value_num' => $valNum,
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

                        $displayValue = $val . ($unit !== '' ? ' ' . $unit : '');

                        // Build a candidate range from item-level report_range or suggested defaults
                        $rangeStr = trim((string) ($it->report_range ?? ''));
                        if ($rangeStr === '') {
                            try {
                                $rangeStr = (string) ($this->suggestNormalRangeByTestName($it) ?? '');
                            } catch (\Throwable $_) {
                                $rangeStr = '';
                            }
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
                            if (preg_match('/^(mg|mg\/dl|mmol\/l|mmol|mmol\/L|mmol\/l|g\/dl|μmol\/l|umol\/l|mmol\/L|mmol\/l|mmol\/L|mmol\/L|mmol\/?l|mg\/?dl)$/i', $nameLc) || preg_match('/^[\p{L}\/\s%]+$/u', $nameLc) && strlen(trim($nameLc)) <= 5) {
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
                            'normal_range' => $rangeStr,
                            'is_out' => $isOut,
                            'value_num' => $valNum,
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
            'hasHeader' => $hasHeader,
            'hasFooter' => $hasFooter,
            'reportDateTime' => $reportDateTime,
            'age' => $age,
            'contact_no' => $contactNo,
            'gender' => $gender,
            'refd_by' => $refdBy,
            'signatureMarginTop' => $signatureMarginTop,
            'signatureMarginLeft' => $signatureMarginLeft,
            'pageMarginTop' => $pageMarginTop,
            'reportHeaderHeight' => $reportHeaderHeight,
            'reportFooterHeight' => $reportFooterHeight,
            'technologistSignature' => $technologistSignature,
            'sampleCollectedBySignature' => $sampleCollectedBySignature,
            'pathologistSignature' => $pathologistSignature,
            'pathologistName' => $pathologistName !== '' ? $pathologistName : 'N/A',
            'pathologistDesignation' => $pathologistDesignation !== '' ? $pathologistDesignation : 'Pathologist',
            'technologistNameSetting' => $technologistNameSetting,
            'technologistDesignationSetting' => $technologistDesignationSetting,
            'sampleCollectedByNameSetting' => $sampleCollectedByNameSetting,
            'sampleCollectedByDesignationSetting' => $sampleCollectedByDesignationSetting,
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
                ->with('pathologyCategory')
                ->find($billItem->item_id);

            $categoryName = trim((string) ($test?->pathologyCategory?->name ?? ''));
        } elseif ($billItem->category === 'Radiology' && !empty($billItem->item_id)) {
            // `bill_items.item_id` may reference either a RadiologyTest record
            // or directly a Test record. Try both patterns to reliably resolve
            // the main category name.
            $categoryName = '';

            // Try as RadiologyTest id -> RadiologyTest->test->pathologyCategory
            try {
                $radiologyTest = RadiologyTest::query()
                    ->with('test.pathologyCategory')
                    ->find($billItem->item_id);
                $categoryName = trim((string) ($radiologyTest?->test?->pathologyCategory?->name ?? ''));
            } catch (\Throwable $_) {
                // ignore
            }

            // If not found, try interpreting item_id as Test id
            if ($categoryName === '') {
                try {
                    $t = Test::query()->with('pathologyCategory')->find($billItem->item_id);
                    $categoryName = trim((string) ($t?->pathologyCategory?->name ?? ''));
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
                        $t = Test::with('pathologyCategory')->find($itemable->test_id);
                        $categoryName = trim((string) ($t?->pathologyCategory?->name ?? ''));
                    }

                    if ($categoryName === '' && isset($itemable->test_category_id) && !empty($itemable->test_category_id)) {
                        $tc = \App\Models\TestCategory::find($itemable->test_category_id);
                        $categoryName = trim((string) ($tc?->name ?? ''));
                    }

                    // some itemables expose a `test` relation
                    if ($categoryName === '' && method_exists($itemable, 'test')) {
                        try {
                            $t2 = $itemable->test()->with('pathologyCategory')->first();
                            $categoryName = trim((string) ($t2?->pathologyCategory?->name ?? ''));
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
            ->with('test.pathologyCategory')
            ->find($billItem->item_id);

        if (!$radiologyTest) {
            return false;
        }

        $lookupTexts = [
            data_get($radiologyTest, 'test.test_name'),
            data_get($radiologyTest, 'test.test_short_name'),
            data_get($radiologyTest, 'test.pathologyCategory.name'),
        ];

        foreach ($lookupTexts as $text) {
            if ($matchesKeywords($text)) {
                return true;
            }
        }

        return false;
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
        $rules = [
            ['keywords' => ['hemoglobin', 'hb'], 'range' => 'Male: 13 - 17 g/dL | Female: 12 - 15 g/dL'],
            ['keywords' => ['wbc', 'white blood cell'], 'range' => '4,000 - 11,000 /uL'],
            ['keywords' => ['rbc', 'red blood cell'], 'range' => 'Male: 4.5 - 5.9 M/uL | Female: 4.1 - 5.1 M/uL'],
            ['keywords' => ['platelet'], 'range' => '150,000 - 450,000 /uL'],
            ['keywords' => ['esr'], 'range' => 'Male: 0 - 15 mm/hr | Female: 0 - 20 mm/hr'],
            ['keywords' => ['glucose', 'fbs', 'fasting blood sugar'], 'range' => '70 - 99 mg/dL'],
            ['keywords' => ['rbs', 'random blood sugar'], 'range' => '70 - 140 mg/dL'],
            ['keywords' => ['hba1c'], 'range' => '4.0 - 5.6 %'],
            ['keywords' => ['creatinine'], 'range' => '0.6 - 1.2 mg/dL'],
            ['keywords' => ['urea'], 'range' => '15 - 40 mg/dL'],
            ['keywords' => ['alt', 'sgpt'], 'range' => '7 - 56 U/L'],
            ['keywords' => ['ast', 'sgot'], 'range' => '10 - 40 U/L'],
            ['keywords' => ['bilirubin'], 'range' => '0.2 - 1.2 mg/dL'],
            ['keywords' => ['cholesterol'], 'range' => '< 200 mg/dL'],
            ['keywords' => ['triglyceride', 'triglycerides'], 'range' => '< 150 mg/dL'],
            ['keywords' => ['ldl'], 'range' => '< 100 mg/dL'],
            ['keywords' => ['hdl'], 'range' => 'Male: > 40 mg/dL | Female: > 50 mg/dL'],
            ['keywords' => ['tsh'], 'range' => '0.4 - 4.0 mIU/L'],
            ['keywords' => ['t3'], 'range' => '0.8 - 2.0 ng/mL'],
            ['keywords' => ['t4'], 'range' => '5.0 - 12.0 ug/dL'],
            ['keywords' => ['ecg'], 'range' => 'Heart Rate: 60 - 100 bpm'],
            ['keywords' => ['ultrasonogram', 'ultrasonography', 'usg'], 'range' => 'Impression based on clinical and sonographic findings'],
        ];

        foreach ($rules as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if (str_contains($context, $keyword)) {
                    return $rule['range'];
                }
            }
        }

        return null;
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
            $fullPath = storage_path('app/public/' . ltrim($candidate, '/'));
            if (file_exists($fullPath)) {
                $resolvedPath = $fullPath;
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

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($resolvedPath));
    }
}
