<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillingRequest;
use App\Models\Admin;
use App\Models\Billing;
use App\Models\BillingDoctor;
use App\Models\BillItem;
use App\Models\CashCounterSession;
use App\Models\Expense;
use App\Models\MedicineInventory;
use App\Models\Pathology;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Radiology;
use App\Models\Referral;
use App\Models\Test;
use App\Models\OpdPrescription;
use App\Models\IpdPrescription;
use App\Models\OpdPatient;
use App\Models\IpdPatient;
use App\Models\Charge;
use App\Models\InvoiceDesign;
use App\Services\AdminService;
use Illuminate\Support\Facades\DB;
use App\Services\BillingService;
use App\Services\IpdDischargeBillingService;

use App\Services\MedicineInventoryService;
use App\Services\PatientService;
use App\Services\ReferralPersonService;
use App\Services\LedgerService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;
use App\Models\PharmacyBill;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BillingController extends Controller
{
    use SystemTrait;

    protected $billingService, $medicineInventoryService, $adminService, $patientService, $referrerService;
    protected $ledgerService;

    public function __construct(BillingService $billingService, MedicineInventoryService $medicineInventoryService, AdminService $adminService, PatientService $patientService, ReferralPersonService $referrerService, LedgerService $ledgerService)
    {
        $this->billingService = $billingService;
        $this->medicineInventoryService = $medicineInventoryService;
        $this->adminService = $adminService;
        $this->patientService = $patientService;
        $this->referrerService = $referrerService;
        $this->ledgerService = $ledgerService;

        $this->middleware('auth:admin');

        // Add permission middleware
        $this->middleware('permission:billing', ['only' => ['index']]);
        $this->middleware('permission:billing|billing-create', ['only' => ['create', 'billing', 'billingPage', 'store', 'searchPrescription', 'searchPrescriptionSuggestions']]);
        $this->middleware('permission:billing-delete', ['only' => ['destroy']]);
        $this->middleware('permission:billing-edit', ['only' => ['edit', 'update']]);
    }



    public function index(Request $request)
    {
        $perPage = (int) ($request->get('numOfData') ?? 10);
        $isIpdBilling = $request->filled('ipd');

        return Inertia::render('Backend/Billing/Index', [
            'pageTitle' => fn () => $isIpdBilling ? 'IPD Billing List' : 'Billing List',
            'tableHeaders' => fn() => $this->getTableHeaders($isIpdBilling),
            'dataFields' => fn() => $this->dataFields($isIpdBilling),
            'datas' => fn () => $this->getDatas($perPage),
            'filters' => fn () => ['numOfData' => $perPage, 'user' => request()->user ?? '', 'ipd' => $request->ipd ?? ''],
            'isIpdBilling' => fn () => $isIpdBilling,
            'userStats' => fn() => $this->getUserStats(request()),
        ]);
    }

    private function getDatas(int $perPage)
    {
        $query = Billing::query()->with(['patient', 'creator']);

        if (request()->filled('ipd')) {
            $query->where('case_number', 'like', 'IPD-%');
        } else {
            $query->where('case_number', 'not like', 'IPD-%');
        }

        // Newest bills should appear first. `id desc` is more reliable for
        // sequential billing order than `created_at` when edits preserve old timestamps.
        $query->orderBy('id', 'desc');

        // Apply simple search from request (bill_number, case_number, patient name via relation, patient_mobile)
        if (request()->filled('search')) {
            $s = trim(request()->search);
            // match billing fields directly and also patient name via relation
            $query->where(function ($q) use ($s) {
                $q->where('bill_number', 'like', "%{$s}%")
                  ->orWhere('case_number', 'like', "%{$s}%")
                  ->orWhere('patient_mobile', 'like', "%{$s}%");
            });

            // search related patient name
            $query->orWhereHas('patient', function ($p) use ($s) {
                $p->where('name', 'like', "%{$s}%");
            });
        }

        if (request()->filled('user')) {
            // match admin by first/last name or full name (Admin model has computed `name`)
            $userName = trim(request()->user);
            $adminIdsQuery = \App\Models\Admin::query()
                ->where('first_name', 'like', "%{$userName}%")
                ->orWhere('last_name', 'like', "%{$userName}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$userName}%"]);

            $adminIds = $adminIdsQuery->pluck('id')->toArray();
            if (count($adminIds)) {
                $query->whereIn('created_by', $adminIds);
            }
        }

        $datas = $query->paginate(request()->numOfData ?? $perPage)->withQueryString();

        $ipdPatients = collect();
        $billingIds = $datas->pluck('id')->filter()->values()->all();
        if (!empty($billingIds)) {
            $ipdPatients = IpdPatient::query()
                ->whereIn('billing_id', $billingIds)
                ->get()
                ->keyBy('billing_id');
        }

        $formated = $datas->map(function ($item, $index) use ($datas, $ipdPatients) {
            $row = new \stdClass();
            $row->index = ($datas->currentPage() - 1) * $datas->perPage() + $index + 1;
            $row->bill_number = $item->bill_number;
            $row->patient_id = $item->patient_name ?? ($item->patient?->name ?? 'N/A');
            $row->total = number_format($item->total ?? 0, 2);
            $row->paid_amt = number_format($item->paid_amt ?? 0, 2);
            // raw numeric due amount (for modal logic) and formatted display
            $row->due_amount = isset($item->due_amount) ? (float) $item->due_amount : 0.0;
            $row->due_amount_display = number_format($row->due_amount ?? 0, 2);

            // identification used by frontend actions/modals
            $row->row_type = 'billing';
            $row->row_id = $item->id;
            if (!empty($item->delivery_date)) {
                try {
                    $row->delivery_date = ($item->delivery_date instanceof \DateTime)
                        ? $item->delivery_date->format('d-m-Y h:i A')
                        : Carbon::parse($item->delivery_date)->format('d-m-Y h:i A');
                } catch (\Throwable $e) {
                    $row->delivery_date = (string) $item->delivery_date;
                }
            } else {
                $row->delivery_date = '';
            }
            $row->created_by = $item->creator?->name ?? ($item->created_by ? (string)$item->created_by : 'N/A');
            $row->payment_status = $item->payment_status ?? '';
            if (!empty($item->created_at)) {
                try {
                    $row->created_at = ($item->created_at instanceof \DateTime)
                        ? $item->created_at->format('d-m-Y h:i A')
                        : Carbon::parse($item->created_at)->format('d-m-Y h:i A');
                } catch (\Throwable $e) {
                    $row->created_at = (string) $item->created_at;
                }
            } else {
                $row->created_at = '';
            }

            $row->ipd_invoice_number = '';
            $row->ipd_patient_id = null;
            if (str_starts_with((string) ($item->case_number ?? ''), 'IPD-')) {
                $ipdpatient = $ipdPatients->get($item->id);
                if ($ipdpatient) {
                    $row->ipd_patient_id = $ipdpatient->id;
                    $ipdInvoiceNumber = function_exists('prefixed_serial')
                        ? prefixed_serial('ipd_no_prefix', 'IPDN', $ipdpatient->id, 4)
                        : ('IPD' . str_pad((string) $ipdpatient->id, 4, '0', STR_PAD_LEFT));

                    $row->ipd_invoice_number = '<a href="' . route('backend.print.ipd.invoice', ['id' => $ipdpatient->id, 'billing_id' => $item->id, 'auto_print' => 1]) . '" target="_blank" rel="noopener">' . e($ipdInvoiceNumber) . '</a>';
                }
            }

            $row->hasLink = true;
            $row->links = [];

            // Due / New Collect button when there's a due and user has permission
            if (
                ($item->payment_status ?? '') !== 'Paid' &&
                (float) ($item->due_amount ?? 0) > 0 &&
                \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->check('billing-due-collect')
            ) {
                $row->links[] = [
                    'linkClass' => 'bg-purple-600 text-white semi-bold',
                    'link' => url('/due-collect/' . $item->id),
                    'linkLabel' => getLinkLabel('Due Collect', null, null),
                    'action_name' => 'due-collect',
                    'action_id' => 'billing|' . $item->id,
                ];
            }

            // basic action buttons (use URLs to avoid missing named routes)
            // Use public download endpoint with query param as a robust fallback
            // (avoids named-route or route-prefix issues and works when route cache
            // may be stale). This endpoint maps to InvoiceController::downloadInvoice.
            if (!empty($row->ipd_patient_id)) {
                // For IPD bills, make the Invoice button open the IPD Final Bill print view
                // so it behaves like the "Open in New Tab" print action.
                try {
                    $printUrl = route('backend.print.ipd.final-bill', ['id' => $row->ipd_patient_id, 'auto_print' => 1, 'fast_open' => 1]);
                } catch (\Throwable $e) {
                    // fallback to a robust URL using the public endpoint
                    $printUrl = url('/print/ipd/final-bill?id=' . urlencode((string) $row->ipd_patient_id) . '&auto_print=1&fast_open=1');
                }

                $row->links[] = [
                    'linkClass' => 'bg-green-600 text-white',
                    'link' => $printUrl,
                    'linkLabel' => getLinkLabel('Invoice', null, null),
                    'target' => '_blank',
                ];
            } else {
                $row->links[] = [
                    'linkClass' => 'bg-green-600 text-white',
                    'link' => url('/download-invoice?id=' . $item->id . '&module=billing&fast_open=1'),
                    'linkLabel' => getLinkLabel('Invoice', null, null),
                    'target' => '_blank',
                ];
            }

            if (\Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->check('billing-edit')) {
                if (!empty($row->ipd_patient_id)) {
                    // For IPD-linked bills, the Edit action should take the user
                    // to the IPD patient show page so they can view/edit the
                    // admission details. Open in the same tab for smoother flow.
                    try {
                        $editUrl = route('backend.ipdpatient.show', $row->ipd_patient_id);
                    } catch (\Throwable $e) {
                        $editUrl = url('/ipdpatient/' . $row->ipd_patient_id);
                    }
                } else {
                    $editUrl = url('/billing/' . $item->id . '/edit');
                }

                $row->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black',
                    'link' => $editUrl,
                    'linkLabel' => getLinkLabel('Edit', null, null),
                ];
            }

            if (\Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->check('billing-delete')) {
                $row->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white',
                    'link' => url('/billing/' . $item->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ];
            }

            return $row;
        });

        return regeneratePagination($formated, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    private function getUserStats(Request $request)
    {
        if (! $request->filled('user')) {
            return [];
        }

        $userName = trim($request->user);
        $isIpd = $request->filled('ipd');
        $adminIds = \App\Models\Admin::query()
            ->where('first_name', 'like', "%{$userName}%")
            ->orWhere('last_name', 'like', "%{$userName}%")
            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$userName}%"])
            ->pluck('id')
            ->toArray();
        if (!count($adminIds)) return [];

        $query = Billing::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->whereIn('created_by', $adminIds)
            ->when($isIpd, function ($q) {
                $q->where('case_number', 'like', 'IPD-%');
            }, function ($q) {
                $q->where('case_number', 'not like', 'IPD-%');
            })
            ->selectRaw(
                "DATE(created_at) as date, COUNT(*) as count, SUM(total) as total_amount, SUM(COALESCE(paid_amt, 0) - COALESCE(return_amt, 0)) as total_net_income, SUM(COALESCE(due_amount, 0)) as due_pending_income, SUM(CASE WHEN discount_type = 'percentage' THEN COALESCE(total, 0) * COALESCE(discount, 0) / 100 ELSE COALESCE(discount, 0) END + COALESCE(extra_flat_discount, 0)) as total_discount"
            )
            ->groupBy('date');

        try {
            $today = Carbon::today()->toDateString();
            // Put today's date on top, then order remaining by date descending
            $query->orderByRaw("(DATE(created_at) = ?) DESC", [$today])
                  ->orderBy('date', 'desc');
        } catch (\Throwable $e) {
            // Fallback to default ordering if DB doesn't support functions
            $query->orderBy('date', 'desc');
        }

        $stats = $query->get()
            ->map(function ($r) {
                return [
                    'date' => $r->date,
                    'count' => (int) $r->count,
                    'total_amount' => isset($r->total_amount) ? (float) $r->total_amount : 0.0,
                    'total_discount' => isset($r->total_discount) ? (float) $r->total_discount : 0.0,
                    'due_pending_income' => isset($r->due_pending_income) ? (float) $r->due_pending_income : 0.0,
                    'total_net_income' => isset($r->total_net_income) ? (float) $r->total_net_income : 0.0,
                ];
            })->toArray();

        return $stats;
    }

    private function parseBillingSearchDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // Ignore invalid date formats and continue trying.
            }
        }

        return null;
    }

    private function dataFields(bool $includeIpdInvoice = false)
    {
        $fields = [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'bill_number', 'class' => 'text-center'],
        ];

        if ($includeIpdInvoice) {
            $fields[] = ['fieldName' => 'ipd_invoice_number', 'class' => 'text-center whitespace-nowrap'];
        }

        $fields = array_merge($fields, [
            ['fieldName' => 'patient_id', 'class' => 'text-center'],
            ['fieldName' => 'total', 'class' => 'text-center'],
            ['fieldName' => 'paid_amt', 'class' => 'text-center'],
            ['fieldName' => 'due_amount_display', 'class' => 'text-center'],
            ['fieldName' => 'delivery_date', 'class' => 'text-center whitespace-nowrap'],
            ['fieldName' => 'created_by', 'class' => 'text-center'],
            ['fieldName' => 'payment_status', 'class' => 'text-center'],
            ['fieldName' => 'created_at', 'class' => 'text-center whitespace-nowrap'],
        ]);

        return $fields;
    }
    private function getTableHeaders(bool $includeIpdInvoice = false)
    {
        $headers = [
            'Sl/No',
            'Bill Number',
        ];

        if ($includeIpdInvoice) {
            $headers[] = 'IPD Invoice';
        }

        return array_merge($headers, [
            'Patient',
            'Total',
            'Paid Amount',
            'Due Amount',
            'Delivery Date',
            'Created By',
            'Payment Status',
            'Created Date',
            'Action',
        ]);
    }

        public function searchShow(Request $request)
    {
        $request->validate([
            'case_id' => 'required|string',
            // When true, we are allowed to create an IPD auto bill if no billing exists yet.
            // We keep it false for debounced (auto) searching.
            'auto_create' => 'nullable|boolean',
        ]);

        $caseId = trim((string) $request->case_id);
        $autoCreate = (bool) $request->boolean('auto_create');

        $mapBilling = function (Billing $billing) {
            return [
                'id' => $billing->id,
                'case_number' => $billing->case_number,
                'patient_name' => $billing->patient?->name ?? 'N/A',
                'patient_mobile' => $billing->patient?->phone ?? 'N/A',
                'created_at' => $billing->created_at?->format('d M Y h:i A') ?? '',
                'status' => $billing->status,
                'payment_status' => $billing->payment_status,
            ];
        };

        $results = Billing::query()
            ->where(function ($q) use ($caseId) {
                $q->where('case_number', 'like', '%' . $caseId . '%')
                  ->orWhere('bill_number', 'like', '%' . $caseId . '%')
                  ->orWhere('invoice_number', 'like', '%' . $caseId . '%');
            })
            ->with(['patient', 'doctor'])
            ->limit(10)
            ->get()
            ->map($mapBilling);

        // If nothing found and user explicitly asked => try IPD auto billing.
        if ($results->isEmpty() && $autoCreate) {
            $ipdId = $this->extractIpdIdFromSearch($caseId);

            if ($ipdId) {
                $ipdpatient = IpdPatient::query()->find($ipdId);

                if ($ipdpatient) {
                    /** @var IpdDischargeBillingService $service */
                    $service = app(IpdDischargeBillingService::class);

                    // Works both for Discharged & Active patients.
                    // For Active patients, it uses "now" as dischargeAt to build a provisional bill.
                    $billing = $service->createOrGetForDischarge($ipdpatient, auth('admin')->id());

                    if (empty($ipdpatient->billing_id)) {
                        $ipdpatient->billing_id = $billing->id;
                        $ipdpatient->save();
                    }

                    $billing->loadMissing(['patient', 'doctor']);

                    $results = collect([$mapBilling($billing)]);
                }
            }
        }

        return response()->json($results);
    }

    private function extractIpdIdFromSearch(string $caseId): ?int
    {
        $caseId = trim($caseId);

        // Accept: "123", "IPD-000123", "ipd 123"
        if (preg_match('/^ipd\s*[-_]?\s*0*(\d+)$/i', $caseId, $m)) {
            return (int) $m[1];
        }

        if (ctype_digit($caseId)) {
            return (int) $caseId;
        }

        return null;
    }



    public function create()
    {
        return Inertia::render(
            'Backend/Billing/Form',
            [
                'pageTitle' => fn() => 'Billing Create',
                'breadcrumbs' => fn() => [
                    ['link' => null, 'title' => 'Billing Manage'],
                    ['link' => route('backend.billing.create'), 'title' => 'Billing Create'],
                ],
            ]
        );
    }

    public function billing()
    {
        return Inertia::render(
            'Backend/Billing/Billing',
            [
                'pageTitle' => fn() => 'Billing Create',
                // 'breadcrumbs' => fn() => [
                //     ['link' => null, 'title' => 'Billing Manage'],
                //     ['link' => route('backend.billing.create'), 'title' => 'Billing Create'],
                // ],
            ]
        );
    }

    public function billingPage()
    {
        $lastPathology = Pathology::latest()->first();
        $lastBillNumber = $lastPathology ? $lastPathology->bill_no : null;

        // Get all active tests (include IPD tests so Item Charge IPD items are available in Billing)
        // Include Disposable tests so items like V. Tube are available in Billing
        // Include OPD and Appointment tests so appointment items appear in Billing
        $pathologyAndRadiologyTests = Test::whereIn('category_type', ['Pathology', 'Radiology', 'ECG', 'Ultrasound', 'IPD', 'Disposable', 'OPD', 'Appointment'])
            ->where('status', 'Active')
            ->select('id', 'category_type', 'test_name', 'test_short_name', 'report_days', 'room_no', 'tax', 'standard_charge', 'amount', 'referral_percentage')
            ->orderBy('test_name')
            ->get()
            ->map(function ($test) {
                return [
                    'id' => $test->id,
                    'category_type' => $test->category_type,
                    'test_name' => $test->test_name,
                    'test_short_name' => $test->test_short_name,
                    'report_days' => $test->report_days,
                    'room_no' => $test->room_no ?? null,
                    'tax' => $test->tax,
                    'standard_charge' => $test->standard_charge,
                    'amount' => $test->amount,
                    'referral_percentage' => $test->referral_percentage ?? 0,
                ];
            });

        $medicineInventories = $this->medicineInventoryService->activeList();
        $doctors = $this->adminService->activeDoctors();
        $patients = $this->patientService->activeList();
        $referrers = $this->referrerService->activeList();
        // Prepare hospital charges with a normalized `module` value (JSON string or empty)
        // Fetch non-deleted charges (include Active/any status) to ensure items are available.
        // Treat unspecified env value as enabled; accept boolean-like strings
        $hospitalChargesEnv = env('HOSPITAL_CHARGES_ENABLED', null);
        $hospitalChargesEnabled = true;
        if ($hospitalChargesEnv !== null && $hospitalChargesEnv !== '') {
            $hospitalChargesEnabled = filter_var($hospitalChargesEnv, FILTER_VALIDATE_BOOLEAN);
        }

        if ($hospitalChargesEnabled) {
            $rawCharges = Charge::with('chargeType')->whereNull('deleted_at')->get();
        } else {
            $rawCharges = collect([]);
        }

        // Prepare hospital charges for frontend suggestions. Previously this
        // feature was disabled (empty array), but include it so items like
        // Disposable charges created via Item Edit are available in Billing.
        $hospitalCharges = [];
        foreach ($rawCharges as $ch) {
            $modules = [];
            if (!empty($ch->module)) {
                $modules = is_array($ch->module) ? $ch->module : preg_split('/\s*,\s*/', trim($ch->module));
            }

            if (empty($modules) && !empty($ch->chargeType?->modules)) {
                $ct = $ch->chargeType->modules;
                $decoded = null;
                try {
                    $decoded = json_decode($ct, true);
                } catch (\Throwable $e) {
                    $decoded = null;
                }

                if (is_array($decoded) && count($decoded) > 0) {
                    $modules = $decoded;
                } else {
                    $modules = preg_split('/\s*,\s*/', trim((string)$ct));
                }
            }

            $modules = array_values(array_filter(array_map(function ($m) {
                return trim((string) $m);
            }, (array) $modules)));

            $hospitalCharges[] = [
                'id' => $ch->id,
                'name' => $ch->name,
                'module' => count($modules) ? json_encode($modules) : '',
                'amount' => $ch->standard_charge ?? 0,
            ];
        }
        $authInfo = $this->adminService->getAuthInfo();
        // dd($pathologyAndRadiologyTests);

        return Inertia::render(
            'Backend/Billing/BillingPage',
            [
                'pageTitle' => fn() => 'Billing Page',
                'billnumber' => fn() => $lastBillNumber,
                'pathologyAndRadiologyTests' => fn() => $pathologyAndRadiologyTests,
                'hospitalCharges' => fn() => $hospitalCharges,
                'medicineInventories' => fn() => $medicineInventories,
                'doctors' => fn() => $doctors,
                'patients' => fn() => $patients,
                'referrers' => fn() => $referrers,
                'authInfo' => fn() => $authInfo,
            ]
        );
    }

    /**
     * Return normalized hospital charges as JSON for frontend fetch.
     */
    public function hospitalChargesList()
    {
        // Fetch non-deleted charges for API as well unless feature disabled.
        $hospitalChargesEnv = env('HOSPITAL_CHARGES_ENABLED', null);
        $hospitalChargesEnabled = true;
        if ($hospitalChargesEnv !== null && $hospitalChargesEnv !== '') {
            $hospitalChargesEnabled = filter_var($hospitalChargesEnv, FILTER_VALIDATE_BOOLEAN);
        }

        if ($hospitalChargesEnabled) {
            $rawCharges = Charge::with('chargeType')->whereNull('deleted_at')->get();
        } else {
            $rawCharges = collect([]);
        }
        $hospitalCharges = [];
        foreach ($rawCharges as $ch) {
            $modules = [];
            if (!empty($ch->module)) {
                $modules = is_array($ch->module) ? $ch->module : preg_split('/\s*,\s*/', trim($ch->module));
            }

            if (empty($modules) && !empty($ch->chargeType?->modules)) {
                $ct = $ch->chargeType->modules;
                $decoded = null;
                try {
                    $decoded = json_decode($ct, true);
                } catch (\Throwable $e) {
                    $decoded = null;
                }

                if (is_array($decoded) && count($decoded) > 0) {
                    $modules = $decoded;
                } else {
                    $modules = preg_split('/\s*,\s*/', trim((string)$ct));
                }
            }

            $modules = array_values(array_filter(array_map(function ($m) {
                return trim((string) $m);
            }, (array) $modules)));

            $hospitalCharges[] = [
                'id' => $ch->id,
                'name' => $ch->name,
                'module' => count($modules) ? json_encode($modules) : '',
                'amount' => $ch->standard_charge ?? 0,
            ];
        }

        return response()->json($hospitalCharges);
    }

    private function normalizeReferralCommissionCategory(?string $category): string
    {
        $cat = strtolower(trim((string) ($category ?? '')));
        if ($cat === '') {
            return '';
        }

        if (in_array($cat, ['pathology', 'pathological'], true)) {
            return 'pathology';
        }

        if (in_array($cat, ['medicine', 'pharmacy', 'drug'], true)) {
            return 'medicine';
        }

        if (in_array($cat, ['ecg', 'ekg'], true)) {
            return 'ecg';
        }

        if (in_array($cat, ['ultrasound', 'ultrasonogram', 'ultrasonography', 'usg', 'sono'], true)) {
            return 'ultrasound';
        }

        if (in_array($cat, ['radiology', 'xray', 'x-ray', 'ct', 'mri'], true)) {
            return 'radiology';
        }

        if (in_array($cat, ['opd', 'appointment'], true)) {
            return $cat === 'appointment' ? 'appointment' : 'opd';
        }

        if ($cat === 'ipd') {
            return 'ipd';
        }

        return $cat;
    }

    private function resolveReferrerCommissionRate($referrer, ?string $category, ?int $itemId = null): float
    {
        $normalizedCategory = $this->normalizeReferralCommissionCategory($category);
        $commissionRate = 0.0;

        switch ($normalizedCategory) {
            case 'pathology':
                $commissionRate = (float) ($referrer->pathology_commission ?? 0);
                break;
            case 'radiology':
                $commissionRate = (float) ($referrer->radiology_commission ?? 0);
                break;
            case 'ecg':
                $commissionRate = (float) ($referrer->ecg_commission ?? 0);
                break;
            case 'ultrasound':
                $commissionRate = (float) ($referrer->ultrasound_commission ?? $referrer->radiology_commission ?? 0);
                break;
            case 'medicine':
                $commissionRate = (float) ($referrer->pharmacy_commission ?? 0);
                break;
            case 'opd':
            case 'appointment':
                $commissionRate = (float) ($referrer->opd_commission ?? 0);
                break;
            case 'ipd':
                $commissionRate = (float) ($referrer->ipd_commission ?? 0);
                break;
        }

        if ($itemId) {
            $itemReferralRate = null;
            try {
                if (in_array($normalizedCategory, ['pathology', 'radiology', 'ecg', 'ultrasound'], true)) {
                    $master = Test::find($itemId);
                    if ($master && isset($master->referral_percentage) && (float) $master->referral_percentage > 0) {
                        $itemReferralRate = (float) $master->referral_percentage;
                    }
                } elseif ($normalizedCategory === 'medicine') {
                    $medicine = MedicineInventory::find($itemId);
                    if ($medicine && isset($medicine->referral_percentage) && (float) $medicine->referral_percentage > 0) {
                        $itemReferralRate = (float) $medicine->referral_percentage;
                    }
                }
            } catch (\Throwable $e) {
                $itemReferralRate = null;
            }

            if ($itemReferralRate !== null) {
                $commissionRate = $itemReferralRate;
            }
        }

        return (float) $commissionRate;
    }

    public function store(BillingRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $expectsJsonResponse = $request->wantsJson()
                || $request->ajax()
                || $request->expectsJson()
                || $request->hasHeader('X-Inertia')
                || $request->header('X-Requested-With') === 'XMLHttpRequest';

            $adminUser = auth('admin')->user();
            $adminId = (int) ($adminUser?->id ?? 0);
            $adminName = trim((string) ($adminUser?->name ?? ''));
            if (!$this->hasOpenCounterSessionForAdmin($adminId, $adminName)) {
                $message = 'No active counter session found for your login. Please Counter Start first.';
                if ($expectsJsonResponse) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'errors' => ['counter' => $message],
                    ], 422);
                }

                return redirect()->back()->with('errorMessage', $message);
            }

            // Debug incoming billing datetime for troubleshooting backdate issues
            try {
                Log::debug('billing.store.received', [
                    'billing_date' => $data['billing_date'] ?? null,
                    'billing_time' => $data['billing_time'] ?? null,
                ]);
            } catch (\Throwable $e) {
                // ignore logging failure
            }

            // Debug: log incoming items payload to help identify category/type issues
            try {
                Log::debug('billing.store.items', ['items' => $data['items'] ?? null]);
            } catch (\Throwable $e) {
                // ignore logging failure
            }

            $doctorInfo = $this->handleDoctor($data['doctor_name'] ?? null);

            // Use unified patient handler
            $patientResult = $this->handlePatientData($data);
            $patientId = $patientResult['patient_id'];
            $data = $patientResult['processed_data'];
            $data = array_merge($data, $this->normalizeBillingPaymentData($data));

            // Normalize item categories: map ECG/Ultrasound/USG variants to Radiology.
            // Also inspect item name because some frontend/test data may have
            // radiology keywords in the name while category is incorrectly set.
            if (!empty($data['items']) && is_array($data['items'])) {
                $data['items'] = array_map(function ($it) {
                    $cat = strtolower(trim((string) ($it['category'] ?? '')));
                    $name = strtolower(trim((string) ($it['name'] ?? '')));

                    $radiologyKeywords = ['ecg', 'ecg of', 'ultrasound', 'ultrasonogram', 'usg'];

                    foreach ($radiologyKeywords as $kw) {
                        if (str_contains($cat, $kw) || str_contains($name, $kw)) {
                            $it['category'] = 'Radiology';
                            break;
                        }
                    }

                    return $it;
                }, $data['items']);
            }

            // Log normalized items to help debugging enum/insert issues
            try {
                Log::debug('billing.store.items.normalized', ['items' => $data['items'] ?? null]);
            } catch (\Throwable $e) {
                // ignore logging failure
            }

            // Allow multiple same-day bills for the same patient.
            // Hospitals may need separate bills for additional tests/services on the same day.

            $totalBillAmountBeforeDiscount = collect($data['items'])->sum('total_amount');
            $totalDiscountAmount = 0;
            if (($data['discount_type'] ?? 'percentage') === 'percentage') {
                $totalDiscountAmount = $totalBillAmountBeforeDiscount * (($data['discount'] ?? 0) / 100);
            } else {
                $totalDiscountAmount = $data['discount'] ?? 0;
            }

            $vatPercentage = 0.0;
            $vatAmount = 0.0;
            try {
                $ws = \App\Models\WebSetting::query()
                    ->where('status', 'Active')
                    ->orderByDesc('id')
                    ->first();

                if (!$ws) {
                    $ws = \App\Models\WebSetting::query()->orderByDesc('id')->first();
                }

                if (!empty($ws) && ($ws->vat_enabled ?? false)) {
                    $vatPercentage = (float) ($ws->vat_percent ?? 0);
                }
            } catch (\Throwable $e) {
                $vatPercentage = 0.0;
            }

            $extraFlat = (float) ($data['extra_flat_discount'] ?? 0);
            $netBeforeVat = max(0, $totalBillAmountBeforeDiscount - $totalDiscountAmount - $extraFlat);
            if ($vatPercentage > 0 && $netBeforeVat > 0) {
                $vatAmount = ($netBeforeVat * $vatPercentage) / 100.0;
            }

            $referrer = isset($data['referrer_id']) ? $this->referrerService->find($data['referrer_id']) : null;

            $billingData = [
                // invoice_number/bill_number/case_number are set below in a retry-safe way
                'patient_id' => $patientId,
                'patient_mobile' => $data['patient_mobile'],
                'gender' => $data['gender'],
                'doctor_id' => $doctorInfo['doctor_id'],
                'doctor_type' => $doctorInfo['doctor_type'],
                'doctor_name' => $doctorInfo['doctor_name'],
                'referrer_id' => $data['referrer_id'] ?? null,
                'card_type' => $data['card_type'],
                'pay_mode' => $data['pay_mode'],
                'card_number' => $data['card_number'] ?? null,
                'total' => $data['total'],
                'discount' => $data['discount'] ?? 0,
                'extra_flat_discount' => $data['extra_flat_discount'] ?? 0,
                'vat_percentage' => $vatPercentage,
                'vat_amount' => $vatAmount,
                'discount_type' => $data['discount_type'] ?? 'percentage',
                // Compute payable_amount from discounted total plus VAT so server and UI stay aligned
                'payable_amount' => max(0, round($netBeforeVat + $vatAmount, 2)),
                'paid_amt' => $data['paid_amt'],
                'invoice_amount' => $data['paid_amt'],
                'change_amt' => $data['change_amt'] ?? 0,
                'due_amount' => $data['due_amount'] ?? 0,
                'receiving_amt' => $data['receiving_amt'] ?? 0,
                'return_amt' => $data['return_amt'] ?? 0,
                'delivery_date' => $data['delivery_date'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'commission_total' => $data['commission_total'] ?? 0,
                'physyst_amt' => $data['physyst_amt'] ?? 0,
                'commission_slider' => $data['commission_slider'] ?? 0,
                'payment_status' => $this->determinePaymentStatus($data['paid_amt'], $data['payable_amount'], $data['total'], $data['receiving_amt'], $data['return_amt'] ?? 0),
                'created_by' => auth('admin')->user()->id,
            ];

            $billing = null;
            $attempts = 0;

            // Prevent duplicate concurrent bills for the same patient.
            // If a billing record for the same patient (and same actor) was
            // created very recently (within the last 10 seconds) with the
            // same total, reuse it instead of creating a new one. We use
            // a SELECT ... FOR UPDATE (lockForUpdate) inside the existing
            // transaction to avoid race conditions.
            try {
                $recentWindow = Carbon::now()->subSeconds(10);
                $maybe = Billing::query()
                    ->where('patient_id', $patientId)
                    ->where('created_by', auth('admin')->id())
                    ->where('total', $billingData['total'])
                    ->where('created_at', '>=', $recentWindow->toDateTimeString())
                    ->lockForUpdate()
                    ->first();

                if ($maybe) {
                    // reuse recent billing to avoid duplicate
                    $billing = $maybe;
                    Log::info('billing.store.reused_recent', ['billing_id' => $billing->id, 'patient_id' => $patientId]);
                }
            } catch (\Throwable $e) {
                // If locking or query fails for any reason, ignore and proceed
                // to create normally. We log at debug level to help future
                // troubleshooting without breaking the flow.
                Log::debug('billing.store.reuse_check_failed', ['error' => $e->getMessage()]);
            }

            while (!$billing && $attempts < 5) {
                $attempts++;

                $billingData['bill_number'] = $this->generateBillNumber();
                $billingData['invoice_number'] = $this->generateInvoiceNumber();
                $billingData['case_number'] = $this->generateCaseNumber();

                try {
                    // If frontend provided a billing date/time, set created_at
                    // so the bill is recorded on that datetime.
                    if (!empty($data['billing_date'])) {
                        $time = $data['billing_time'] ?? '00:00:00';
                        try {
                            $billingData['created_at'] = Carbon::parse($data['billing_date'] . ' ' . $time)->toDateTimeString();
                        } catch (\Throwable $e) {
                        }
                    }

                    $billing = $this->billingService->create($billingData);

                    // Ensure created_at persisted even if model is not mass-assignable
                    if (!empty($billingData['created_at']) && $billing) {
                        try {
                            $billing->created_at = $billingData['created_at'];
                            $billing->save();
                        } catch (\Throwable $e) {
                        }
                    }
                } catch (\Illuminate\Database\QueryException $e) {
                    // Retry on duplicate key (bill_number/invoice_number/case_number)
                    if (($e->errorInfo[0] ?? null) === '23000') {
                        usleep(random_int(10000, 50000));
                        continue;
                    }

                    throw $e;
                }
            }

            if (!$billing) {
                throw new Exception('Failed to create billing record (duplicate number)');
            }


            // Rest of your store method remains the same...
            $totalBillAmountBeforeDiscount = collect($data['items'])->sum('total_amount');
            $totalDiscountAmount = 0;

            if ($data['discount_type'] === 'percentage') {
                $totalDiscountAmount = $totalBillAmountBeforeDiscount * ($data['discount'] / 100);
            } else {
                $totalDiscountAmount = $data['discount'];
            }

            $discountFactor = ($totalBillAmountBeforeDiscount > 0) ? ($totalDiscountAmount / $totalBillAmountBeforeDiscount) : 0;

            if ($data['referrer_id']) {
                $totalCommission = 0;
                $categoryCommissions = [];

                foreach ($data['items'] as $item) {
                    $category = $this->normalizeReferralCommissionCategory($item['category'] ?? '');
                    $commissionRate = $this->resolveReferrerCommissionRate(
                        $referrer,
                        $category,
                        isset($item['id']) ? (int) $item['id'] : null
                    );

                    $itemCommission = ($item['net_amount'] * $commissionRate) / 100;
                    $totalCommission += $itemCommission;

                    if (!isset($categoryCommissions[$category])) {
                        $categoryCommissions[$category] = [
                            'rate' => $commissionRate,
                            'amount' => 0,
                            'items' => []
                        ];
                    }

                    $categoryCommissions[$category]['amount'] += $itemCommission;
                    $categoryCommissions[$category]['items'][] = [
                        'item_id' => $item['id'],
                        'item_name' => $item['name'],
                        'amount' => $item['net_amount'],
                        'commission' => $itemCommission
                    ];
                }

                $refData = [
                    'billing_id' => $billing->id,
                    'payee_id' => $data['referrer_id'],
                    'total_commission_amount' => $totalCommission,
                    'category_commissions' => $categoryCommissions,
                    'date' => ($billing->created_at ? Carbon::parse($billing->created_at)->toDateString() : now()->toDateString()),
                    'total_bill_amount' => $data['total'],
                    'status' => 'Active'
                ];

                if ($billing->created_at) {
                    $refData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
                }

                Referral::create($refData);

                if (empty($data['commission_total'])) {
                    $data['commission_total'] = $totalCommission;
                }
                if (empty($data['physyst_amt'])) {
                    $data['physyst_amt'] = $data['commission_total'] ?? 0;
                }
            }

            foreach ($data['items'] as $item) {
                $itemProportionalDiscount = $item['total_amount'] * $discountFactor;
                $itemNetAmount = $item['total_amount'] - $itemProportionalDiscount;

                // snapshot item-level commission settings when available
                $commissionable = null;
                $commission_rate = null;
                $catLower = strtolower($item['category'] ?? '');
                $isDisposable = false;
                $itemNameLower = strtolower($item['name'] ?? '');
                if ($catLower === 'pathology') {
                    if (str_contains($itemNameLower, 'disposable') || str_contains($itemNameLower, 'tube') || str_contains($itemNameLower, 'butterfly') || str_contains($itemNameLower, 'needle')) {
                        $isDisposable = true;
                    }
                }
                if (in_array($catLower, ['pathology', 'radiology'])) {
                    $master = \App\Models\Test::find($item['id']);
                    if ($master) {
                        $commissionable = $master->commissionable ?? null;
                        $commission_rate = $master->commission_rate ?? null;

                        // If item has referral_percentage, prefer it for commission_rate snapshot
                        try {
                            if (isset($master->referral_percentage) && $master->referral_percentage > 0) {
                                $commission_rate = (float) $master->referral_percentage;
                            }
                        } catch (\Exception $e) {
                            // ignore and keep commission_rate
                        }

                        // Log snapshot values for debugging
                        try {
                            \Illuminate\Support\Facades\Log::debug('billing.store.item.snapshot', [
                                'item_id' => $item['id'] ?? null,
                                'item_name' => $item['name'] ?? null,
                                'category' => $item['category'] ?? null,
                                'commissionable' => $commissionable,
                                'commission_rate_snapshot' => $commission_rate,
                            ]);
                        } catch (\Throwable $logEx) {
                            // ignore
                        }
                    }
                }

                $isRadiologyItem = in_array($catLower, ['radiology', 'ecg', 'ultrasound', 'xray', 'ultrasonogram', 'ultrasonography']);

                BillItem::create([
                    'billing_id' => $billing->id,
                    'item_id' => $item['id'],
                    'item_name' => $item['name'],
                    'room_no' => $item['room_no'] ?? $item['roomNo'] ?? (isset($master) ? ($master->room_no ?? null) : null),
                    'category' => $item['category'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total_amount' => $item['total_amount'],
                    'discount' => $itemProportionalDiscount,
                    'rugound' => $item['rugound'] ?? 0,
                    'net_amount' => $itemNetAmount,
                    // Only pathology items require sample collection. Ensure
                    // radiology/other categories are not marked for sample.
                    'requires_sample' => ($catLower === 'pathology') && ! $isDisposable,
                    // For radiology-like items set sample_collected_at so they
                    // appear immediately in Reporting lists.
                    'sample_collected_at' => $isRadiologyItem ? ($billing->created_at ? Carbon::parse($billing->created_at)->toDateTimeString() : now()) : null,
                    'created_at' => $billing->created_at ? Carbon::parse($billing->created_at)->toDateTimeString() : now(),
                    'sample_collected_by' => $isRadiologyItem ? auth('admin')->id() : null,
                    'commissionable' => $commissionable,
                    'commission_rate' => $commission_rate,
                ]);

                if (strtolower($item['category']) === 'medicine') {
                    $medicine = MedicineInventory::find($item['id']);
                    if ($medicine) {
                        $newQuantity = $medicine->medicine_quantity - $item['quantity'];
                        $medicine->update(['medicine_quantity' => max(0, $newQuantity)]);
                    }
                }
            }

            if ($data['paid_amt'] > 0) {
                $paymentData = [
                    'billing_id' => $billing->id,
                    'amount' => $data['paid_amt'],
                    'payment_method' => $data['pay_mode'],
                    'transaction_id' => $data['card_number'] ?? null,
                    'notes' => $data['remarks'] ?? null,
                    'received_by' => auth('admin')->user()->id,
                    'payment_status' => $this->determinePaymentStatus($data['paid_amt'], $data['payable_amount'], $data['total'], $data['receiving_amt'], $data['return_amt'] ?? 0),
                ];

                if ($billing->created_at) {
                    $paymentData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
                }

                Payment::create($paymentData);
            }

            // Commission expense is recorded on referral payment

            $pathologyItems = collect($data['items'])->where('category', 'Pathology');
            if ($pathologyItems->isNotEmpty()) {
                $this->createPathologyRecord($billing, $pathologyItems, $data);
            }

            $radiologyItems = collect($data['items'])->filter(function ($item) {
                $cat = strtolower((string) ($item['category'] ?? ''));
                $normalized = preg_replace('/[^a-z0-9]/', '', $cat);
                return in_array($normalized, ['radiology', 'ultrasound', 'xray', 'ecg', 'ultrasonogram', 'ultrasonography']);
            });
            if ($radiologyItems->isNotEmpty()) {
                $this->createRadiologyRecord($billing, $radiologyItems, $data);
            }

            $medicineItems = collect($data['items'])->where('category', 'Medicine');
            if ($medicineItems->isNotEmpty()) {
                $this->createPharmacyBillRecord($billing, $medicineItems, $data);
            }

            // Record ledger postings: split payable into paid (cash/bank) and due (AR)
            try {
                $paidAmount = (float) ($billing->paid_amt ?? 0);
                $dueAmount = (float) ($billing->due_amount ?? 0);
                $createdBy = auth('admin')->user()->id ?? null;
                $refType = 'Billing';
                $refId = $billing->id;
                $date = $billing->created_at ? $billing->created_at->toDateString() : now()->toDateString();

                if ($paidAmount > 0) {
                    $counterAccount = 'CASH';
                    if (!empty($data['pay_mode']) && strtolower($data['pay_mode']) !== 'cash') {
                        $counterAccount = \App\Models\Account::where('code', 'BANK')->exists() ? 'BANK' : 'CASH';
                    }
                    $this->ledgerService->recordIncome('DIAG_INC', $counterAccount, $paidAmount, 'Billing paid amount for ' . ($billing->bill_number ?? $billing->id), $date, $refType, $refId, $createdBy);
                }

                if ($dueAmount > 0) {
                    $this->ledgerService->recordIncome('DIAG_INC', 'AR', $dueAmount, 'Billing due amount for ' . ($billing->bill_number ?? $billing->id), $date, $refType, $refId, $createdBy);
                }
            } catch (\Exception $e) {
                $this->storeSystemError('Backend', 'BillingController', 'store->ledger', substr($e->getMessage(), 0, 1000));
            }

            // Try to link this billing to an existing IpdPatient record so
            // Dashboard IPD aggregation can find related bills. Previously
            // linkage was only attempted when an `IPD` item was present; make
            // it more robust by linking whenever the patient has an active
            // IpdPatient without a billing_id.
            try {
                if (!empty($billing->patient_id)) {
                    $ipdpatient = IpdPatient::query()
                        ->where('patient_id', $billing->patient_id)
                        ->whereNull('billing_id')
                        ->whereIn('status', ['Active', 'Inactive'])
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($ipdpatient) {
                        $ipdpatient->billing_id = $billing->id;
                        $ipdpatient->save();

                        // Ensure any IPD-scoped payments are associated with this billing
                        try {
                            Payment::query()
                                ->whereNull('deleted_at')
                                ->where('status', 'Active')
                                ->where('ipd_patient_id', $ipdpatient->id)
                                ->whereNull('billing_id')
                                ->update(['billing_id' => $billing->id]);
                        } catch (\Throwable $_e) {
                            // ignore payment update failures
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('BillingController: failed to link billing to ipdpatient', ['billing_id' => $billing->id, 'error' => $e->getMessage()]);
            }

            $message = 'Billing created successfully with Bill No: ' . ($billing->bill_number ?? ''); 

            $printToken = (string) ($request->input('print_token') ?? '');

            // Map print_token -> billing id so preview tab can resolve invoice quickly.
            try {
                if ($printToken !== '') {
                    Cache::put('print_token_' . $printToken, $billing->id, now()->addMinutes(10));
                }
            } catch (\Throwable $e) {
                // ignore token mapping failures; normal invoice open path still works.
            }

            $this->storeAdminWorkLog($billing->id, 'billings', $message);
                        ActivityLogService::logCreate(
                            'Billing',
                            $billing->id,
                            $billing->bill_number ?? ('Billing#' . $billing->id),
                            ActivityLogService::buildBillingDeleteMeta($billing)
                        );

            DB::commit();

            // If request expects JSON (Inertia/AJAX), return JSON with billId so
            // the frontend can immediately navigate/open invoice without relying
            // on session flash propagation.
            // Also detect Inertia XHRs which send the `X-Inertia` header so that
            // the frontend (Inertia/useForm) receives a direct JSON payload
            // containing the created bill id.
            // If this is an AJAX/Inertia request, return JSON so frontend handlers run.
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson() || $request->hasHeader('X-Inertia') || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                $invoiceUrl = '';
                try {
                    if ($printToken !== '') {
                        $invoiceUrl = route('backend.download.invoice', ['print_token' => $printToken, 'module' => 'billing', 'fast_open' => 1, 'auto_print' => 1]);
                    } else {
                        $invoiceUrl = route('backend.download.invoice', ['id' => $billing->id, 'module' => 'billing', 'fast_open' => 1, 'auto_print' => 1]);
                    }
                } catch (\Throwable $e) {
                    if ($printToken !== '') {
                        $invoiceUrl = url('/download-invoice?print_token=' . urlencode($printToken) . '&module=billing&fast_open=1&auto_print=1');
                    } else {
                        $invoiceUrl = url('/download-invoice?id=' . $billing->id . '&module=billing&fast_open=1&auto_print=1');
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'billId' => $billing->id,
                    'invoiceUrl' => $invoiceUrl,
                ]);
            }

            return redirect()
                ->back()
                ->with('successMessage', $message)
                ->with('billId', $billing->id);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingController', 'store', substr($err->getMessage(), 0, 1000));

            $message = "Server error occurred: " . $err->getMessage();
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson() || $request->hasHeader('X-Inertia') || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'errors' => $message,
                ], 500);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with('errorMessage', $message);
        }
    }

    private function buildFastOpenInvoiceHtml(Billing $billing, Request $request): string
    {
        $module = trim((string) ($request->input('module') ?? 'billing'));
        if ($module === '') {
            $module = 'billing';
        }

        $items = $request->input('items', []);
        if (!is_array($items)) {
            $items = [];
        }

        $billItems = collect($items)
            ->filter(fn($item) => is_array($item))
            ->map(function (array $item) {
                $qty = (float) ($item['quantity'] ?? $item['qty'] ?? 1);
                $unitPrice = (float) ($item['amount'] ?? $item['unitPrice'] ?? $item['unit_price'] ?? 0);
                $totalAmount = (float) ($item['total_amount'] ?? $item['totalAmount'] ?? $item['net_amount'] ?? ($qty * $unitPrice));

                return (object) [
                    'item_name' => (string) ($item['name'] ?? $item['itemName'] ?? 'Item'),
                    'description' => (string) ($item['description'] ?? ''),
                    'category' => (string) ($item['category'] ?? ''),
                    'room_no' => (string) ($item['room_no'] ?? $item['roomNo'] ?? ''),
                    'quantity' => $qty,
                    'qty' => $qty,
                    'amount' => $unitPrice,
                    'discount' => (float) ($item['discount'] ?? 0),
                    'total_amount' => $totalAmount,
                ];
            })
            ->values();

        $patient = null;
        if (!empty($billing->patient_id)) {
            $patient = Patient::query()->find($billing->patient_id);
        }

        $invoiceDesign = $this->resolveFastInvoiceDesign($module);

        $requestTotal = (float) ($request->input('total') ?? 0);
        $requestNetPayable = (float) ($request->input('payable_amount') ?? 0);
        $requestPaid = (float) ($request->input('paid_amt') ?? $request->input('receiving_amt') ?? 0);
        $requestDue = (float) ($request->input('due_amount') ?? 0);

        $totalAmount = $requestTotal > 0
            ? $requestTotal
            : (float) ($billing->total ?? $billItems->sum('total_amount'));
        $netPayable = $requestNetPayable > 0
            ? $requestNetPayable
            : (float) ($billing->invoice_amount ?? $totalAmount);
        $paidAmount = max(0, $requestPaid > 0 ? $requestPaid : (float) ($billing->receiving_amt ?? 0));
        $dueAmount = $requestDue >= 0
            ? max(0, $requestDue)
            : max(0, $netPayable - $paidAmount);

        $requestedReturn = (float) ($request->input('return_amount') ?? $request->input('return_amt') ?? 0);
        $persistedReturn = (float) ($billing->return_amt ?? $billing->return_amount ?? 0);
        $cashReturnFallback = max(0, $paidAmount - $netPayable);
        $returnAmount = $requestedReturn > 0
            ? $requestedReturn
            : ($persistedReturn > 0 ? $persistedReturn : $cashReturnFallback);
        $adjustedDue = max(0, $dueAmount - $returnAmount);

        $discountType = (string) ($request->input('discount_type') ?? $billing->discount_type ?? 'amount');
        $discountValue = (float) ($request->input('discount') ?? $billing->discount ?? 0);
        $extraFlatDiscount = (float) ($request->input('extra_flat_discount') ?? $billing->extra_flat_discount ?? 0);

        // Avoid expensive QR generation in fast-open HTML while keeping the same visual layout.
        $billing->patient_id = 0;
        $billing->setRelation('patient', $patient);
        $billing->setRelation('billItems', $billItems);
        $billing->setRelation('dueCollections', collect());
        $billing->setRelation('payments', collect());

        if (!$billing->relationLoaded('admin')) {
            $billing->load('admin');
        }

        $footerContent = (string) ($invoiceDesign?->footer_content ?? '');
        $footerContent = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', '', $footerContent) ?? $footerContent;

        $fontFile = public_path('fonts/NotoSansBengali-Regular.ttf');
        $fontPath = is_file($fontFile) ? str_replace('\\', '/', $fontFile) : '';

        $data = [
            'billing' => $billing,
            'bill_number' => $billing->bill_number ?? '',
            'invoiceDateTime' => $billing->created_at ? $billing->created_at->format('d-M-Y h:i:s A') : now()->format('d-M-Y h:i:s A'),
            'printed_at' => now()->timezone('Asia/Dhaka')->format('d F, Y h:i:s a'),
            'patient_name' => trim((string) ($request->input('patient_name') ?? $request->input('name') ?? ($patient->name ?? 'N/A'))),
            'age' => trim((string) ($request->input('patient_age') ?? ($patient->age ?? 'N/A'))),
            'contact_no' => trim((string) ($request->input('patientMobile') ?? $request->input('patient_phone') ?? $billing->patient_mobile ?? ($patient->phone ?? ''))),
            'gender' => (string) ($request->input('patient_gender') ?? $billing->gender ?? ($patient->gender ?? '')),
            'refd_by' => (string) ($request->input('doctor_name') ?? $billing->doctor_name ?? 'N/A'),
            'bill_items' => $billItems,
            'total_amount' => round($totalAmount, 2),
            'vat' => 0,
            'net_payable' => round($netPayable, 2),
            'discount' => $discountType === 'percentage'
                ? $discountValue
                : max(0, $discountValue),
            'discount_type' => $discountType,
            'extra_flat_discount' => max(0, $extraFlatDiscount),
            'paid' => round($paidAmount, 2),
            'paid_at_invoice' => round($paidAmount, 2),
            'due' => round($dueAmount, 2),
            'return_amount' => round($returnAmount, 2),
            'adjusted_due' => round($adjustedDue, 2),
            'delivery_date' => $billing->delivery_date,
            'remarks' => (string) ($billing->remarks ?? ''),
            'prepared_by' => (string) ($billing?->admin?->name ?? ''),
            'amount_in_words' => 'In Words: ' . number_format($netPayable, 2) . ' Taka Only',
            'header_image' => $this->fastInvoiceImageUrl($invoiceDesign?->header_photo_path),
            'footer_image' => $this->fastInvoiceImageUrl($invoiceDesign?->footer_photo_path),
            'footer_content' => $footerContent,
            'footer_content_position' => in_array(strtolower((string) ($invoiceDesign?->footer_content_position ?? '')), ['above', 'below'], true)
                ? strtolower((string) $invoiceDesign->footer_content_position)
                : 'above',
            'footer_font_size' => max(6, min(72, (int) ($invoiceDesign?->footer_font_size ?? 14))),
            'header_height' => (int) ($invoiceDesign?->header_height ?? 115),
            'footer_height' => (int) ($invoiceDesign?->footer_height ?? 70),
            'barcode' => '',
            'module' => $module,
            'banglaFontUrl' => is_file($fontFile) ? asset('fonts/NotoSansBengali-Regular.ttf') : '',
            'banglaFontPath' => $fontPath,
            'showHeaderFooter' => true,
            'show_header_footer' => true,
            'reportHeaderHeight' => (int) ($invoiceDesign?->header_height ?? 115),
            'reportFooterHeight' => (int) ($invoiceDesign?->footer_height ?? 70),
        ];

        return view('frontend.invoice.pdf', $data)->render();
    }

    private function resolveFastInvoiceDesign(string $module): ?InvoiceDesign
    {
        $normalizedModule = strtolower(trim($module));

        $moduleDesign = InvoiceDesign::query()
            ->where('status', 'Active')
            ->whereRaw('LOWER(TRIM(module)) = ?', [$normalizedModule])
            ->first();
        if ($moduleDesign) {
            return $moduleDesign;
        }

        return InvoiceDesign::query()
            ->where('status', 'Active')
            ->whereNull('module')
            ->first();
    }

    private function fastInvoiceImageUrl(?string $publicStorageUrl): string
    {
        if (!$publicStorageUrl) {
            return '';
        }

        $rawPath = trim((string) $publicStorageUrl);
        if ($rawPath === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $rawPath) === 1 || str_starts_with($rawPath, 'data:image/')) {
            return $rawPath;
        }

        $resolvedUrl = publicStorageUrl($rawPath);
        if ($resolvedUrl) {
            return $resolvedUrl;
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $rawPath), '/');
        return asset($normalizedPath);
    }


    public function searchDoctors(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:1'
        ]);

        $search = $request->search;

        $doctors = BillingDoctor::where('name', 'like', '%' . $search . '%')
            ->where('status', 'Active')
            ->select('id', 'name')
            ->get()
            ->map(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                ];
            });

        return response()->json($doctors);
    }

    public function searchPrescription(Request $request)
    {
        $validated = $request->validate([
            'prescription_id' => 'required|string',
        ]);

        $prescriptionId = $this->parsePrescriptionId((string) $validated['prescription_id']);
        if (!$prescriptionId) {
            return response()->json([
                'message' => 'Please provide a valid prescription id.',
                'tests' => [],
            ], 422);
        }

        $opdPrescription = OpdPrescription::query()
            ->with(['items', 'opdPatient.patient'])
            ->find($prescriptionId);

        if ($opdPrescription) {
            $testNames = collect($opdPrescription->items)
                ->pluck('test_name')
                ->map(fn ($name) => trim((string) $name))
                ->filter(fn ($name) => $name !== '')
                ->unique()
                ->values();

            return response()->json([
                'source' => 'OPD',
                'prescription_id' => $opdPrescription->id,
                'patient' => [
                    'id' => $opdPrescription->opdPatient?->patient?->id,
                    'name' => $opdPrescription->opdPatient?->patient?->name,
                    'phone' => $opdPrescription->opdPatient?->patient?->phone,
                    'gender' => $opdPrescription->opdPatient?->patient?->gender,
                    'dob' => $opdPrescription->opdPatient?->patient?->dob,
                ],
                'tests' => $testNames,
            ]);
        }

        $ipdPrescription = IpdPrescription::query()
            ->with(['tests', 'ipdPatient.patient'])
            ->find($prescriptionId);

        if ($ipdPrescription) {
            $testNames = collect($ipdPrescription->tests)
                ->pluck('test_name')
                ->map(fn ($name) => trim((string) $name))
                ->filter(fn ($name) => $name !== '')
                ->unique()
                ->values();

            return response()->json([
                'source' => 'IPD',
                'prescription_id' => $ipdPrescription->id,
                'patient' => [
                    'id' => $ipdPrescription->ipdPatient?->patient?->id,
                    'name' => $ipdPrescription->ipdPatient?->patient?->name,
                    'phone' => $ipdPrescription->ipdPatient?->patient?->phone,
                    'gender' => $ipdPrescription->ipdPatient?->patient?->gender,
                    'dob' => $ipdPrescription->ipdPatient?->patient?->dob,
                ],
                'tests' => $testNames,
            ]);
        }

        return response()->json([
            'message' => 'Prescription not found.',
            'tests' => [],
        ], 404);
    }

    public function searchPrescriptionSuggestions(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        if ($search === '') {
            return response()->json([]);
        }

        $normalizedSearch = preg_replace('/\D+/', '', $search) ?: $search;

        $opd = OpdPrescription::query()
            ->with(['opdPatient.patient'])
            ->where('id', 'like', '%' . $normalizedSearch . '%')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function ($prescription) {
                return [
                    'id' => $prescription->id,
                    'source' => 'OPD',
                    'patient_name' => $prescription->opdPatient?->patient?->name,
                    'label' => 'OPD-' . $prescription->id,
                ];
            });

        $ipd = IpdPrescription::query()
            ->with(['ipdPatient.patient'])
            ->where('id', 'like', '%' . $normalizedSearch . '%')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function ($prescription) {
                return [
                    'id' => $prescription->id,
                    'source' => 'IPD',
                    'patient_name' => $prescription->ipdPatient?->patient?->name,
                    'label' => 'IPD-' . $prescription->id,
                ];
            });

        $suggestions = collect()
            ->merge($opd)
            ->merge($ipd)
            ->sortByDesc('id')
            ->take(12)
            ->values();

        return response()->json($suggestions);
    }

    private function parsePrescriptionId(string $rawInput): ?int
    {
        $rawInput = trim($rawInput);
        if ($rawInput === '') {
            return null;
        }

        if (ctype_digit($rawInput)) {
            return max(1, (int) $rawInput);
        }

        if (preg_match('/(?:opd|ipd)?\s*[-_:#]?\s*0*(\d+)/i', $rawInput, $matches)) {
            $id = (int) ($matches[1] ?? 0);
            return $id > 0 ? $id : null;
        }

        return null;
    }

    private function handleDoctor($doctorName)
    {
        if (empty($doctorName)) {
            return [
                'doctor_id' => null,
                'doctor_type' => null,
                'doctor_name' => null
            ];
        }

        $doctor = BillingDoctor::where('name', $doctorName)
            ->where('status', 'Active')
            ->first();

        if (!$doctor) {
            $doctor = BillingDoctor::create([
                'name' => $doctorName,
                'status' => 'Active'
            ]);
        }

        return [
            'doctor_id' => $doctor->id,
            'doctor_type' => 'billing',
            'doctor_name' => $doctor->name
        ];
    }

    private function handleDoctorSelection($data)
    {
        $doctorId = null;
        $doctorType = null;
        $doctorName = null;

        if (!empty($data['doctor_id'])) {
            $doctor = BillingDoctor::find($data['doctor_id']);
            if ($doctor) {
                $doctorId = $doctor->id;
                $doctorType = 'billing';
                $doctorName = $doctor->name;
            }
        } elseif (!empty($data['doctor_name'])) {
            $doctor = BillingDoctor::create([
                'name' => $data['doctor_name'],
                'status' => 'Active'
            ]);
            $doctorId = $doctor->id;
            $doctorType = 'billing';
            $doctorName = $doctor->name;
        }

        return [
            'doctor_id' => $doctorId,
            'doctor_type' => $doctorType,
            'doctor_name' => $doctorName
        ];
    }

    private function updateOrCreateExpenseRecord($billing, $data)
    {
        $billDate = $billing->created_at ? Carbon::parse($billing->created_at) : now();
        $commissionAmount = $data['physyst_amt'] ?? $data['commission_total'] ?? 0;

        $categories = collect($data['items'])->pluck('category')->map(function ($category) {
            return strtolower($category);
        })->unique()->toArray();

        $expenseHeaderName = count($categories) > 1 ? 'billing' : $categories[0];

        $categoryMap = [
            'medicine' => 'pharmacy',
            'pathology' => 'pathology',
            'radiology' => 'radiology',
            'billing' => 'billing'
        ];

        $headerName = $categoryMap[$expenseHeaderName] ?? 'billing';

        $expenseHeader = \App\Models\ExpenseHead::where('name', ucfirst($headerName))->first();

        if (!$expenseHeader) {
            $expenseHeader = \App\Models\ExpenseHead::create([
                'name' => ucfirst($headerName),
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $expenseData = [
            'expense_header_id' => $expenseHeader->id,
            'bill_number' => $billing->bill_number,
            'case_id' => $billing->case_number,
            'name' => auth('admin')->user()->name ?? '',
            'description' => 'Commission expense for ' . implode(', ', $categories) . ' services',
            'amount' => $commissionAmount,
            'date' => $billDate->toDateString(),
            'status' => 'Active'
        ];

        $expenseData['updated_by'] = auth('admin')->user()->id;
        $expenseData['created_by'] = auth('admin')->user()->id;

        Expense::updateOrCreate(
            ['bill_number' => $billing->bill_number],
            $expenseData
        );
    }

    private function createPathologyRecord($billing, $pathologyItems, $data)
    {
        $billDate = $billing->created_at ? Carbon::parse($billing->created_at) : now();
        $totalPathologyDiscount = $pathologyItems->sum('discount');
        $pathologyNetAmount = $pathologyItems->sum('net_amount');

        $existingPathology = Pathology::where('bill_no', $billing->bill_number)->first();

        if ($existingPathology) {
            $existingPathology->update([
                'patient_id' => $billing->patient_id,
                'apply_tpa' => false,
                'payee_id' => $data['referrer_id'],
                'date' => $billDate->format('Y-m-d'),
                'doctor_id' => $billing->doctor_id,
                'doctor_name' => $billing->doctor_name,
                'commission_percentage' => $data['commission_slider'] ?? 0,
                'commission_amount' => $data['physyst_amt'] ?? 0,
                'tests' => json_encode($pathologyItems->map(function ($item) {
                    return [
                        'test_id' => $item['id'],
                        'test_name' => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'total_amount' => $item['total_amount'],
                        'net_amount' => $item['net_amount']
                    ];
                })->toArray()),
                'subtotal' => $pathologyItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalPathologyDiscount,
                'net_amount' => $pathologyNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $pathologyItems->sum('net_amount'),
                'note' => $data['remarks'],
                'updated_by' => auth('admin')->user()->id,
            ]);

            return $existingPathology;
        } else {
            $lastPathology = Pathology::withTrashed()->orderby('id', 'desc')->first();
            $pathologyNo = $this->generatePathologyNumber($lastPathology);

            $pathologyData = [
                'pathology_no' => $pathologyNo,
                'patient_id' => $billing->patient_id,
                'bill_no' => $billing->bill_number,
                'case_id' => $billing->case_number,
                'apply_tpa' => false,
                'payee_id' => $data['referrer_id'],
                'date' => $billDate->format('Y-m-d'),
                'doctor_id' => $billing->doctor_id,
                'doctor_name' => $billing->doctor_name,
                'commission_percentage' => $data['commission_slider'] ?? 0,
                'commission_amount' => $data['physyst_amt'] ?? 0,
                'tests' => json_encode($pathologyItems->map(function ($item) {
                    return [
                        'test_id' => $item['id'],
                        'test_name' => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'total_amount' => $item['total_amount'],
                        'net_amount' => $item['net_amount']
                    ];
                })->toArray()),
                'subtotal' => $pathologyItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalPathologyDiscount,
                'net_amount' => $pathologyNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $pathologyItems->sum('net_amount'),
                'note' => $data['remarks'],
                'created_by' => auth('admin')->user()->id
            ];

            if ($billing->created_at) {
                $pathologyData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
            }

            return Pathology::create($pathologyData);
        }
    }

    private function generatePathologyNumber($lastPathology = null)
    {
        $prefix = web_setting_prefix('pathology_bill_prefix', 'Bill');

        if ($lastPathology && $lastPathology->pathology_no) {
            $lastNumber = (int) substr($lastPathology->pathology_no, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $newNumber;
    }

    private function createRadiologyRecord($billing, $radiologyItems, $data)
    {
        $billDate = $billing->created_at ? Carbon::parse($billing->created_at) : now();

        $totalRadiologyDiscount = $radiologyItems->sum('discount');
        $radiologyNetAmount = $radiologyItems->sum('net_amount');

        $existingRadiology = Radiology::withTrashed()->where('bill_no', $billing->bill_number)->first();

        if ($existingRadiology) {
            if ($existingRadiology->trashed()) {
                $existingRadiology->restore();
            }
            $existingRadiology->update([
                'patient_id' => $billing->patient_id,
                'referral_doctor_id' => $billing->doctor_id,
                'note' => $data['remarks'],
                'test_details' => json_encode($radiologyItems->map(function ($item) {
                    return [
                        'test_id' => $item['id'],
                        'test_name' => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'total_amount' => $item['total_amount'],
                        'net_amount' => $item['net_amount']
                    ];
                })->toArray()),
                'total_amount' => $radiologyItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalRadiologyDiscount,
                'net_amount' => $radiologyNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $radiologyItems->sum('net_amount'),
                'updated_by' => auth('admin')->user()->id,
            ]);
        } else {
            $lastRadiology = Radiology::withTrashed()->orderby('id', 'desc')->first();
            $radiologyNo = $this->generateRadiologyNumber($lastRadiology);

            $lastBilling = $this->billingService->getLastBilling();
            $billNumber = $this->generateBillNumber($lastBilling);

            $caseNumber = $this->generateCaseNumber($lastBilling);

            $radiologyData = [
                'bill_no' => $billing->bill_number,
                'case_id' => $billing->case_number,
                'radiology_no' => $radiologyNo,
                'patient_id' => $billing->patient_id,
                'referral_doctor_id' => $billing->doctor_id,
                'note' => $data['remarks'],
                'test_details' => json_encode($radiologyItems->map(function ($item) {
                    return [
                        'test_id' => $item['id'],
                        'test_name' => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'total_amount' => $item['total_amount'],
                        'net_amount' => $item['net_amount']
                    ];
                })->toArray()),
                'total_amount' => $radiologyItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalRadiologyDiscount,
                'net_amount' => $radiologyNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $radiologyItems->sum('net_amount'),
                'created_by' => auth('admin')->user()->id
            ];

            // set created_at to billing date/time when backdated
            if ($billing->created_at) {
                $radiologyData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
            }

            return Radiology::create($radiologyData);
        }
    }

    private function normalizeBillingPaymentData(array $data): array
    {
        $payableAmount = max(0, (float) ($data['payable_amount'] ?? $data['total'] ?? 0));
        $requestedPaid = max(0, (float) ($data['paid_amt'] ?? 0));

        $receivingAmount = max(0, (float) ($data['receiving_amt'] ?? 0));
        $requestedReturn = max(0, (float) ($data['return_amt'] ?? 0));

        $effectivePaid = min($payableAmount, $requestedPaid);

        $hasExplicitReceiving = $receivingAmount > 0.0001;
        $hasExplicitReturn = $requestedReturn > 0.0001;
        $preserveExistingReturnOnEdit = !$hasExplicitReceiving && $hasExplicitReturn;

        if ($preserveExistingReturnOnEdit) {
            $grossReceived = max($requestedPaid, $requestedPaid + $requestedReturn);
        } else {
            $grossReceived = $hasExplicitReceiving ? max($receivingAmount, $requestedPaid) : max($requestedPaid, $receivingAmount);
        }

        $maxReturn = max(0, $grossReceived - $effectivePaid);
        $returnAmount = $hasExplicitReturn ? min($requestedReturn, $maxReturn) : $maxReturn;
        if ($returnAmount <= 0.0001 && $maxReturn > 0.0001) {
            $returnAmount = $maxReturn;
        }

        $effectivePaid = max(0, min($payableAmount, $grossReceived - $returnAmount));
        $dueAmount = max(0, round($payableAmount - $effectivePaid, 2));

        return [
            'payable_amount' => round($payableAmount, 2),
            'paid_amt' => round($effectivePaid, 2),
            'invoice_amount' => round($effectivePaid, 2),
            'receiving_amt' => round($grossReceived, 2),
            'change_amt' => round(max(0, (float) ($data['change_amt'] ?? 0)), 2),
            'due_amount' => round($dueAmount, 2),
            'return_amt' => round($returnAmount, 2),
        ];
    }

    private function determinePaymentStatus($paidAmount, $payableAmount, $total, $recevingAmount, $returnAmount = 0)
    {
        $paidAmount = floatval($paidAmount);
        $payableAmount = floatval($payableAmount);
        $returnAmount = floatval($returnAmount);

        if ($paidAmount >= $payableAmount) {
            if ($returnAmount > 0.0001) {
                return 'Partial';
            }
            return 'Paid';
        } elseif ($paidAmount > 0) {
            return 'Partial';
        } else {
            return 'Pending';
        }
    }

    private function createPharmacyBillRecord($billing, $medicineItems, $data)
    {
        $billDate = $billing->created_at ? Carbon::parse($billing->created_at) : now();
        $totalMedicineDiscount = $medicineItems->sum('discount');
        $medicineNetAmount = $medicineItems->sum('net_amount');

        $existingPharmacyBill = PharmacyBill::withTrashed()->where('bill_no', $billing->bill_number)->first();

        if ($existingPharmacyBill) {
            if ($existingPharmacyBill->trashed()) {
                $existingPharmacyBill->restore();
            }
            $existingPharmacyBill->update([
                'patient_id' => $billing->patient_id,
                'doctor_id' => $billing->doctor_id,
                'doctor_name' => $billing->doctor_name,
                'products' => json_encode($medicineItems->map(function ($item) {
                    return [
                        'medicine_id' => $item['id'],
                        'medicine_name' => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'total_amount' => $item['total_amount'],
                        'discount' => $item['discount'] ?? 0,
                        'net_amount' => $item['net_amount']
                    ];
                })->toArray()),
                'subtotal' => $medicineItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalMedicineDiscount,
                'net_amount' => $medicineNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $medicineNetAmount,
                'note' => $data['remarks'],
                'updated_by' => auth('admin')->user()->id,
            ]);

            return $existingPharmacyBill;
        } else {
            $lastPharmacyBill = PharmacyBill::orderby('id', 'desc')->first();
            $pharmacyNo = $this->generatePharmacyNumber($lastPharmacyBill);

            $vatPercentage = 0.0;
            try {
                $ws = \App\Models\WebSetting::query()
                    ->where('status', 'Active')
                    ->orderByDesc('id')
                    ->first();

                if (!$ws) {
                    $ws = \App\Models\WebSetting::query()->orderByDesc('id')->first();
                }

                if (!empty($ws) && ($ws->vat_enabled ?? false)) {
                    $vatPercentage = (float) ($ws->vat_percent ?? 0);
                }
            } catch (\Throwable $e) {
                $vatPercentage = 0.0;
            }
            $vatAmount = ($medicineNetAmount * $vatPercentage) / 100.0;

            $pharmacyBillData = [
                'pharmacy_no' => $pharmacyNo,
                'bill_no' => $billing->bill_number,
                'case_id' => $billing->case_number,
                'date' => $billDate->format('Y-m-d'),
                'patient_id' => $billing->patient_id,
                'doctor_id' => $billing->doctor_id,
                'doctor_name' => $billing->doctor_name,
                'products' => json_encode($medicineItems->map(function ($item) {
                    return [
                        'medicine_id' => $item['id'],
                        'medicine_name' => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'total_amount' => $item['total_amount'],
                        'discount' => $item['discount'] ?? 0,
                        'net_amount' => $item['net_amount']
                    ];
                })->toArray()),
                'subtotal' => $medicineItems->sum('total_amount'),
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
                'discount_amount' => $totalMedicineDiscount,
                'vat_percentage' => $vatPercentage,
                'vat_amount' => $vatAmount,
                'extra_discount' => $data['extra_flat_discount'] ?? 0,
                'net_amount' => $medicineNetAmount,
                'payment_mode' => $data['pay_mode'],
                'payment_amount' => $medicineNetAmount,
                'note' => $data['remarks'],
                'created_by' => auth('admin')->user()->id,
                'status' => 'Active',
            ];

            // if billing was backdated, set pharmacy created_at accordingly
            if ($billing->created_at) {
                $pharmacyBillData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
            }

            return PharmacyBill::create($pharmacyBillData);
        }
    }

    private function generatePharmacyNumber($lastPharmacyBill = null)
    {
        $prefix = web_setting_prefix('pharmacy_bill_prefix', 'PHAB');
        $year = date('Y');

        if ($lastPharmacyBill && $lastPharmacyBill->pharmacy_no) {
            $lastNumber = (int) substr($lastPharmacyBill->pharmacy_no, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $year . $newNumber;
    }

    public function edit($id)
    {
        $billing = $this->billingService->find($id);

        if (!$billing) {
            return redirect()
                ->route('backend.billing.list')
                ->with('errorMessage', 'Billing not found.');
        }

        $billing->load('billItems');

        $pathologyAndRadiologyTests = Test::whereIn('category_type', ['Pathology', 'Radiology', 'ECG', 'Ultrasound'])
            ->where('status', 'Active')
            ->select('id', 'category_type', 'test_name', 'test_short_name', 'report_days', 'room_no', 'tax', 'standard_charge', 'amount')
            ->orderBy('test_name')
            ->get()
            ->map(function ($test) {
                return [
                    'id' => $test->id,
                    'category_type' => $test->category_type,
                    'test_name' => $test->test_name,
                    'test_short_name' => $test->test_short_name,
                    'report_days' => $test->report_days,
                    'room_no' => $test->room_no ?? null,
                    'tax' => $test->tax,
                    'standard_charge' => $test->standard_charge,
                    'amount' => $test->amount,
                ];
            });

        $medicineInventories = $this->medicineInventoryService->activeList();
        $doctors = $this->adminService->activeDoctors();
        $patients = $this->patientService->activeList();

        $patientDetails = $this->patientService->find($billing->patient_id);

        $editData = [
            'patient_id' => $billing->patient_id,
            'doctor_id' => $billing->doctor_id,
            'doctor_name' => $billing->doctor_name,
            'patient_mobile' => $billing->patient_mobile,
            'gender' => $billing->gender,
            'card_type' => $billing->card_type,
            'pay_mode' => $billing->pay_mode,
            'payment_type' => $billing->payment_type ?? $billing->pay_mode,
            'card_number' => $billing->card_number,
            'total' => $billing->total,
            'discount' => $billing->discount,
            'extra_flat_discount' => $billing->extra_flat_discount ?? '',
            'discount_type' => $billing->discount_type,
            'payable_amount' => $billing->payable_amount,
            'paid_amt' => $billing->paid_amt,
            'change_amt' => $billing->change_amt,
            'due_amount' => $billing->due_amount,
            'receiving_amt' => $billing->receiving_amt,
            'return_amt' => $billing->return_amt,
            'delivery_date' => $billing->delivery_date,
            'remarks' => $billing->remarks,
            'commission_total' => $billing->commission_total,
            'physyst_amt' => $billing->physyst_amt,
            'commission_slider' => $billing->commission_slider,
            'referrer_id' => $billing->referrer_id,
            'items' => $billing->billItems->map(function ($item) {
                return [
                    'id' => $item->item_id,
                    'name' => $item->item_name,
                    'category' => $item->category,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'total_amount' => $item->total_amount,
                    'discount' => $item->discount,
                    'rugound' => $item->rugound,
                    'net_amount' => $item->net_amount,
                ];
            })->toArray()
        ];

        // Expose billing date/time so frontend can pre-populate/backdate when editing
        if (!empty($billing->created_at)) {
            try {
                $editData['billing_date'] = $billing->created_at->format('Y-m-d');
                $editData['billing_time'] = $billing->created_at->format('H:i:s');
            } catch (\Throwable $e) {
                $editData['billing_date'] = null;
                $editData['billing_time'] = null;
            }
        } else {
            $editData['billing_date'] = null;
            $editData['billing_time'] = null;
        }

        if ($billing->doctor_id) {
            $doctorPrefix = $billing->doctor_type === 'billing' ? 'billing_' : 'admin_';
            $editData['doctor_id'] = $doctorPrefix . $billing->doctor_id;
        }

        $referrers = $this->referrerService->activeList();
        return Inertia::render(
            'Backend/Billing/BillingPage',
            [
                'pageTitle' => fn() => 'Edit Billing - ' . $billing->bill_number,
                'breadcrumbs' => fn() => [
                    ['link' => null, 'title' => 'Billing Manage'],
                    ['link' => route('backend.billing.list'), 'title' => 'Billing List'],
                    ['link' => route('backend.billing.edit', $id), 'title' => 'Edit Billing'],
                ],
                'billing' => fn() => $billing,
                'editData' => fn() => $editData,
                'pathologyAndRadiologyTests' => fn() => $pathologyAndRadiologyTests,
                'medicineInventories' => fn() => $medicineInventories,
                'doctors' => fn() => $doctors,
                'patients' => fn() => $patients,
                'id' => fn() => $id,
                'referrers' => fn() => $referrers,

            ]
        );
    }

    protected static function resolvePersistedCreatedAt($existingCreatedAt, array $data): ?string
    {
        if (!empty($data['billing_date'])) {
            $time = trim((string) ($data['billing_time'] ?? ''));

            if ($time === '' && $existingCreatedAt) {
                try {
                    $time = Carbon::parse($existingCreatedAt)->format('H:i:s');
                } catch (\Throwable $e) {
                    $time = '00:00:00';
                }
            }

            if ($time === '') {
                $time = '00:00:00';
            }

            try {
                return Carbon::parse($data['billing_date'] . ' ' . $time)->toDateTimeString();
            } catch (\Throwable $e) {
                return $existingCreatedAt ? Carbon::parse($existingCreatedAt)->toDateTimeString() : null;
            }
        }

        return $existingCreatedAt ? Carbon::parse($existingCreatedAt)->toDateTimeString() : null;
    }

    public function update(BillingRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $expectsJsonResponse = $request->wantsJson()
                || $request->ajax()
                || $request->expectsJson()
                || $request->hasHeader('X-Inertia')
                || $request->header('X-Requested-With') === 'XMLHttpRequest';

            $adminUser = auth('admin')->user();
            $adminId = (int) ($adminUser?->id ?? 0);
            $adminName = trim((string) ($adminUser?->name ?? ''));
            if (!$this->hasOpenCounterSessionForAdmin($adminId, $adminName)) {
                $message = 'No active counter session found for your login. Please Counter Start first.';
                if ($expectsJsonResponse) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'errors' => ['counter' => $message],
                    ], 422);
                }

                return redirect()->back()->with('errorMessage', $message);
            }

            $doctorInfo = $this->handleDoctor($data['doctor_name'] ?? null);
            $billing = $this->billingService->find($id);


            if (!$billing) {
                return redirect()
                    ->route('backend.billing.list')
                    ->with('errorMessage', 'Billing not found.');
            }

            // Use unified patient handler with existing billing
            $patientResult = $this->handlePatientData($data, $billing);
            $patientId = $patientResult['patient_id'];
            $data = $patientResult['processed_data'];

            // Preserve existing return amount when edit does not include a new receipt.
            $incomingReturn = max(0, (float) ($data['return_amt'] ?? $data['return_amount'] ?? 0));
            $incomingReceiving = max(0, (float) ($data['receiving_amt'] ?? 0));
            if ($incomingReturn <= 0 && $incomingReceiving <= 0 && !empty($billing->return_amt)) {
                $data['return_amt'] = $billing->return_amt;
            }

            if ($incomingReturn <= 0.0001 && $incomingReceiving <= 0.0001 && !empty($billing->return_amt)) {
                $data['paid_amt'] = max(0, (float) ($billing->paid_amt ?? 0));
            }

            $data = array_merge($data, $this->normalizeBillingPaymentData($data));

            // Store old quantities for medicine inventory rollback
            $oldBillItems = BillItem::where('billing_id', $id)->get();
            $oldBillItemSnapshots = $oldBillItems->map(function ($item) {
                return [
                    'id' => $item->item_id ?? $item->id,
                    'item_id' => $item->item_id ?? $item->id,
                    'item_name' => $item->item_name ?? $item->name ?? 'Item',
                    'total_amount' => $item->total_amount ?? $item->net_amount ?? $item->amount ?? 0,
                ];
            })->all();

            // Rollback medicine quantities from old items
            foreach ($oldBillItems as $oldItem) {
                if (strtolower($oldItem->category) === 'medicine') {
                    $medicine = MedicineInventory::find($oldItem->item_id);
                    if ($medicine) {
                        $medicine->increment('medicine_quantity', $oldItem->quantity);
                    }
                }
            }

            // Prepare updated billing data
            $billingData = [
                'patient_id' => $patientId,
                'patient_mobile' => $data['patient_mobile'],
                'gender' => $data['gender'],
                'referrer_id' => $data['referrer_id'] ?? null,
                'card_type' => $data['card_type'],
                'pay_mode' => $data['pay_mode'],
                'card_number' => $data['card_number'] ?? null,
                'total' => $data['total'],
                'discount' => $data['discount'] ?? 0,
                'extra_flat_discount' => $data['extra_flat_discount'] ?? 0,
                'discount_type' => $data['discount_type'] ?? 'percentage',
                // compute VAT for updated billing if applicable
                'vat_percentage' => 0.0,
                'vat_amount' => 0.0,
                'payable_amount' => $data['payable_amount'] ?? $data['total'],
                'paid_amt' => $data['paid_amt'],
                'invoice_amount' => $data['paid_amt'],
                'change_amt' => $data['change_amt'] ?? 0,
                'due_amount' => $data['due_amount'] ?? 0,
                'receiving_amt' => $data['receiving_amt'] ?? 0,
                'return_amt' => $data['return_amt'] ?? 0,
                'delivery_date' => $data['delivery_date'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'commission_total' => $data['commission_total'] ?? 0,
                'physyst_amt' => $data['physyst_amt'] ?? 0,
                'commission_slider' => $data['commission_slider'] ?? 0,
                'payment_status' => $this->determinePaymentStatus($data['paid_amt'], $data['payable_amount'], $data['total'], $data['receiving_amt'], $data['return_amt'] ?? 0),
                'updated_by' => auth('admin')->user()->id,
                'doctor_id' => $doctorInfo['doctor_id'],
                'doctor_type' => $doctorInfo['doctor_type'],
                'doctor_name' => $doctorInfo['doctor_name'],
            ];

            // Use the requested billing date/time for backdated bills when
            // provided, otherwise preserve the existing created_at value.
            $billingData['created_at'] = self::resolvePersistedCreatedAt($billing->created_at ?? null, $data);

            // Update billing record
            $updatedBilling = $this->billingService->update($billingData, $id);

            // Persist created_at if provided so downstream records use the backdate
            if (!empty($billingData['created_at']) && $updatedBilling) {
                try {
                    $updatedBilling->created_at = $billingData['created_at'];
                    $updatedBilling->save();
                } catch (\Throwable $e) {
                }
            }
            // Replace the in-memory $billing with the updated model so
            // subsequent operations use the persisted created_at.
            $billing = $updatedBilling;
            if (!$updatedBilling) {
                throw new Exception('Failed to update billing record');
            }

            // Delete existing billing items
            BillItem::where('billing_id', $id)->delete();

            // Recompute totals for update path to calculate VAT
            $totalBillAmountBeforeDiscount = collect($data['items'])->sum('total_amount');
            $totalDiscountAmount = 0;
            if (($data['discount_type'] ?? '') === 'percentage') {
                $totalDiscountAmount = $totalBillAmountBeforeDiscount * (($data['discount'] ?? 0) / 100);
            } else {
                $totalDiscountAmount = $data['discount'] ?? 0;
            }

            $vatPercentage = 0.0;
            $vatAmount = 0.0;
            try {
                $ws = \App\Models\WebSetting::query()
                    ->where('status', 'Active')
                    ->orderByDesc('id')
                    ->first();

                if (!$ws) {
                    $ws = \App\Models\WebSetting::query()->orderByDesc('id')->first();
                }

                if (!empty($ws) && ($ws->vat_enabled ?? false)) {
                    $vatPercentage = (float) ($ws->vat_percent ?? 0);
                }
            } catch (\Throwable $e) {
                $vatPercentage = 0.0;
            }
            $extraFlat = (float) ($data['extra_flat_discount'] ?? 0);
            $netBeforeVat = max(0, $totalBillAmountBeforeDiscount - $totalDiscountAmount - $extraFlat);
            if ($vatPercentage > 0 && $netBeforeVat > 0) {
                $vatAmount = ($netBeforeVat * $vatPercentage) / 100.0;
            }

            // Update billingData vat values and payable_amount from the discounted base amount
            $billingData['vat_percentage'] = $vatPercentage;
            $billingData['vat_amount'] = $vatAmount;
            $billingData['payable_amount'] = max(0, round($netBeforeVat + $vatAmount, 2));
            $billingData['due_amount'] = max(0, round($billingData['payable_amount'] - $billingData['paid_amt'], 2));
            $billingData['payment_status'] = $this->determinePaymentStatus(
                $billingData['paid_amt'],
                $billingData['payable_amount'],
                $data['total'],
                $billingData['receiving_amt'],
                $billingData['return_amt'] ?? 0
            );

            $newBillItemSnapshots = [];

            // Create new billing items and update medicine inventory
            foreach ($data['items'] as $item) {
                $commissionable = null;
                $commission_rate = null;
                $catLower = strtolower($item['category'] ?? '');
                if (in_array($catLower, ['pathology', 'radiology'])) {
                    $master = \App\Models\Test::find($item['id']);
                    if ($master) {
                        $commissionable = $master->commissionable ?? null;
                        $commission_rate = $master->commission_rate ?? null;
                    }
                }

                    $isDisposable = false;
                    $itemNameLower = strtolower($item['name'] ?? '');
                    if ($catLower === 'pathology') {
                        if (str_contains($itemNameLower, 'disposable') || str_contains($itemNameLower, 'tube') || str_contains($itemNameLower, 'butterfly') || str_contains($itemNameLower, 'needle')) {
                            $isDisposable = true;
                        }
                    }

                $isRadiologyItem = in_array($catLower, ['radiology', 'ecg', 'ultrasound', 'xray', 'ultrasonogram', 'ultrasonography']);

                $newBillItemSnapshots[] = [
                    'id' => $item['id'],
                    'item_id' => $item['id'],
                    'item_name' => $item['name'],
                    'total_amount' => $item['total_amount'] ?? $item['net_amount'] ?? 0,
                ];

                BillItem::create([
                    'billing_id' => $id,
                    'item_id' => $item['id'],
                    'item_name' => $item['name'],
                    'room_no' => $item['room_no'] ?? $item['roomNo'] ?? (isset($master) ? ($master->room_no ?? null) : null),
                    'category' => $item['category'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total_amount' => $item['total_amount'],
                    'discount' => $item['discount'] ?? 0,
                    'rugound' => $item['rugound'] ?? 0,
                    'net_amount' => $item['net_amount'],
                    'requires_sample' => ($catLower === 'pathology') && ! $isDisposable,
                    'sample_collected_at' => $isRadiologyItem ? ($billing->created_at ? Carbon::parse($billing->created_at)->toDateTimeString() : now()) : null,
                    'sample_collected_by' => $isRadiologyItem ? auth('admin')->id() : null,
                    'created_at' => $billing->created_at ? Carbon::parse($billing->created_at)->toDateTimeString() : now(),
                    'status' => 'Active',
                    'commissionable' => $commissionable,
                    'commission_rate' => $commission_rate,
                ]);

                // Update medicine inventory for new quantities
                if (strtolower($item['category']) === 'medicine') {
                    $medicine = MedicineInventory::find($item['id']);
                    if ($medicine) {
                        $newQuantity = $medicine->medicine_quantity - $item['quantity'];
                        $medicine->update(['medicine_quantity' => max(0, $newQuantity)]);
                    }
                }
            }

            $itemChanges = ActivityLogService::buildBillingItemChangeSummary($oldBillItemSnapshots, $newBillItemSnapshots);

            $this->updatePaymentRecords($id, $data);

            // Update related records (pathology, radiology, pharmacy)
            $pathologyItems = collect($data['items'])->where('category', 'Pathology');
            if ($pathologyItems->isNotEmpty()) {
                $this->createOrUpdatePathologyRecord($billing, $pathologyItems, $data);
            } else {
                Pathology::where('bill_no', $billing->bill_number)->delete();
            }

            $radiologyItems = collect($data['items'])->filter(function ($item) {
                $cat = strtolower((string) ($item['category'] ?? ''));
                $normalized = preg_replace('/[^a-z0-9]/', '', $cat);
                return in_array($normalized, ['radiology', 'ultrasound', 'xray', 'ecg', 'ultrasonogram', 'ultrasonography']);
            });
            if ($radiologyItems->isNotEmpty()) {
                Radiology::where('case_id', $billing->case_number . '-RAD')->delete();
                $this->createRadiologyRecord($billing, $radiologyItems, $data);
            } else {
                Radiology::where('case_id', $billing->case_number . '-RAD')->delete();
            }

            $medicineItems = collect($data['items'])->where('category', 'Medicine');
            if ($medicineItems->isNotEmpty()) {
                PharmacyBill::where('bill_no', $billing->bill_number)->delete();
                $this->createPharmacyBillRecord($billing, $medicineItems, $data);
            } else {
                PharmacyBill::where('bill_no', $billing->bill_number)->delete();
            }

            // Handle referral commission
            if ($data['referrer_id']) {
                Referral::where('billing_id', $id)->delete();

                $referrer = $this->referrerService->find($data['referrer_id']);
                $totalCommission = 0;
                $categoryCommissions = [];

                foreach ($data['items'] as $item) {
                    $category = $this->normalizeReferralCommissionCategory($item['category'] ?? '');
                    $commissionRate = $this->resolveReferrerCommissionRate(
                        $referrer,
                        $category,
                        isset($item['id']) ? (int) $item['id'] : null
                    );

                    $itemCommission = ($item['net_amount'] * $commissionRate) / 100;
                    $totalCommission += $itemCommission;

                    if (!isset($categoryCommissions[$category])) {
                        $categoryCommissions[$category] = [
                            'rate' => $commissionRate,
                            'amount' => 0,
                            'items' => []
                        ];
                    }

                    $categoryCommissions[$category]['amount'] += $itemCommission;
                    $categoryCommissions[$category]['items'][] = [
                        'item_id' => $item['id'],
                        'item_name' => $item['name'],
                        'amount' => $item['net_amount'],
                        'commission' => $itemCommission
                    ];
                }

                $refData = [
                    'billing_id' => $id,
                    'payee_id' => $data['referrer_id'],
                    'total_commission_amount' => $totalCommission,
                    'category_commissions' => $categoryCommissions,
                    'date' => ($billing->created_at ? Carbon::parse($billing->created_at)->toDateString() : now()->toDateString()),
                    'total_bill_amount' => $data['total'],
                    'status' => 'Active'
                ];

                if ($billing->created_at) {
                    $refData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
                }

                Referral::create($refData);

                if (empty($data['commission_total'])) {
                    $data['commission_total'] = $totalCommission;
                }
                if (empty($data['physyst_amt'])) {
                    $data['physyst_amt'] = $data['commission_total'] ?? 0;
                }
            } else {
                Referral::where('billing_id', $id)->delete();
            }

            if (!$data['referrer_id']) {
                Expense::where('bill_number', $billing->bill_number)->delete();
            }

            $message = 'Billing updated successfully with Bill No: ' . $billing->bill_number;
            $this->storeAdminWorkLog($id, 'billings', $message);
            ActivityLogService::logUpdate(
                'Billing',
                $billing->id,
                $billing->bill_number ?? ('Billing#' . $billing->id),
                [
                    'changes' => [
                        'bill_number' => $billing->bill_number,
                        'invoice_number' => $billing->invoice_number ?? null,
                        'case_number' => $billing->case_number ?? null,
                        'patient_id' => $billing->patient_id,
                        'total_amount' => $billing->total,
                        'payable_amount' => $billing->payable_amount,
                        'paid_amt' => $billing->paid_amt,
                        'due_amount' => $billing->due_amount,
                        'payment_status' => $billing->payment_status,
                        'item_changes' => $itemChanges,
                    ],
                    'snapshot' => ActivityLogService::buildBillingDeleteMeta($billing),
                ],
                []
            );

            DB::commit();

            $printToken = (string) ($request->input('print_token') ?? '');
            if ($printToken !== '') {
                try {
                    Cache::put('print_token_' . $printToken, $billing->id, now()->addMinutes(10));
                } catch (\Throwable $e) {
                    Log::debug('billing.update.print_token_cache_failed', ['id' => $id, 'print_token' => $printToken, 'error' => $e->getMessage()]);
                }
            }

            if ($request->wantsJson() || $request->ajax() || $request->expectsJson() || $request->hasHeader('X-Inertia') || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                try {
                    if ($printToken !== '') {
                        $invoiceUrl = route('backend.download.invoice', ['print_token' => $printToken, 'module' => 'billing', 'fast_open' => 1, 'auto_print' => 1]);
                    } else {
                        $invoiceUrl = route('backend.download.invoice', ['id' => $billing->id, 'module' => 'billing', 'fast_open' => 1, 'auto_print' => 1]);
                    }
                } catch (\Throwable $e) {
                    if ($printToken !== '') {
                        $invoiceUrl = url('/download-invoice?print_token=' . urlencode($printToken) . '&module=billing&fast_open=1&auto_print=1');
                    } else {
                        $invoiceUrl = url('/download-invoice?id=' . $billing->id . '&module=billing&fast_open=1&auto_print=1');
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'billId' => $billing->id,
                    'invoiceUrl' => $invoiceUrl,
                ]);
            }

            return redirect()
                ->route('backend.billing.list')
                ->with('successMessage', $message)
                ->with('billId', $billing->id);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingController', 'update', substr($err->getMessage(), 0, 1000));

            $message = "Server error occurred: " . $err->getMessage();
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson() || $request->hasHeader('X-Inertia') || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'errors' => $message,
                ], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('errorMessage', $message);
        }
    }

    private function createOrUpdatePathologyRecord($billing, $pathologyItems, $data)
    {
        $billDate = $billing->created_at ? Carbon::parse($billing->created_at) : now();
        $totalPathologyDiscount = $pathologyItems->sum('discount');
        $pathologyNetAmount = $pathologyItems->sum('net_amount');

        $pathologyData = [
            'patient_id' => $billing->patient_id,
            'apply_tpa' => false,
            'payee_id' => $data['referrer_id'] ?? null,
            'date' => $billDate->format('Y-m-d'),
            'doctor_id' => $billing->doctor_id,
            'doctor_name' => $billing->doctor_name,
            'commission_percentage' => $data['commission_slider'] ?? 0,
            'commission_amount' => $data['physyst_amt'] ?? 0,
            'tests' => json_encode($pathologyItems->map(function ($item) {
                return [
                    'test_id' => $item['id'],
                    'test_name' => $item['name'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total_amount' => $item['total_amount'],
                    'net_amount' => $item['net_amount']
                ];
            })->toArray()),
            'subtotal' => $pathologyItems->sum('total_amount'),
            'discount_percentage' => $data['discount_type'] === 'percentage' ? $data['discount'] : 0,
            'discount_amount' => $totalPathologyDiscount,
            'net_amount' => $pathologyNetAmount,
            'payment_mode' => $data['pay_mode'],
            'payment_amount' => $pathologyItems->sum('net_amount'),
            'note' => $data['remarks'],
            'updated_by' => auth('admin')->user()->id,
        ];

        $existingPathology = Pathology::withTrashed()->where('bill_no', $billing->bill_number)->first();

        if ($existingPathology) {
            if ($existingPathology->trashed()) {
                $existingPathology->restore();
            }
            $existingPathology->update($pathologyData);
            return $existingPathology;
        } else {
            $lastPathology = Pathology::withTrashed()->orderby('id', 'desc')->first();
            $pathologyNo = $this->generatePathologyNumber($lastPathology);

            $pathologyData['pathology_no'] = $pathologyNo;
            $pathologyData['bill_no'] = $billing->bill_number;
            $pathologyData['case_id'] = $billing->case_number;
            $pathologyData['created_by'] = auth('admin')->user()->id;
            if ($billing->created_at) {
                $pathologyData['created_at'] = Carbon::parse($billing->created_at)->toDateTimeString();
            }

            return Pathology::create($pathologyData);
        }
    }

    // Helper method for updating payment records
    private function updatePaymentRecords($billingId, $data)
    {
        // The incoming `paid_amt` is the total paid on the billing record.
        // To avoid creating duplicate payments when editing/printing,
        // only create a new Payment for the positive difference (delta)
        // between the requested total and already recorded payments.
        $incomingPaid = floatval($data['paid_amt'] ?? 0);

        if ($incomingPaid <= 0) {
            return;
        }

        // Consider both Payments and DueCollections when computing what has
        // already been paid for this billing. This avoids creating duplicate
        // payment records when due collections exist.
        $existingPaymentsSum = (float) Payment::where('billing_id', $billingId)->whereNull('deleted_at')->sum('amount');
        $existingDueCollected = (float) \App\Models\DueCollection::where('billing_id', $billingId)->sum('collected_amount');
        $existingPaid = $existingPaymentsSum + $existingDueCollected;
        $delta = $incomingPaid - $existingPaid;

        // Small epsilon to avoid floating point noise
        if ($delta > 0.0001) {
            // Only create a Payment when there is an explicit receiving amount
            // provided in the request. This prevents accidental payment records
            // when editing unrelated patient info where `paid_amt` may differ
            // on the client-side but no real payment was made.
            $receivingAmount = floatval($data['receiving_amt'] ?? 0);
            if ($receivingAmount > 0.0001) {
                // Create payment for the amount actually received (cap by delta)
                $paymentAmount = round(min($delta, $receivingAmount), 2);
                if ($paymentAmount > 0.0001) {
                    $paymentData = [
                        'billing_id' => $billingId,
                        'amount' => $paymentAmount,
                        'payment_method' => $data['pay_mode'] ?? null,
                        'transaction_id' => $data['card_number'] ?? null,
                        'notes' => $data['remarks'] ?? null,
                        'received_by' => auth('admin')->user()->id,
                        'payment_status' => $this->determinePaymentStatus($incomingPaid, $data['payable_amount'] ?? 0, $data['total'] ?? 0, $data['receiving_amt'] ?? 0, $data['return_amt'] ?? 0),
                    ];

                    $billingForPayment = Billing::find($billingId);
                    if ($billingForPayment && $billingForPayment->created_at) {
                        $paymentData['created_at'] = Carbon::parse($billingForPayment->created_at)->toDateTimeString();
                    }

                    Payment::create($paymentData);
                }
            }
        }

        // Always refresh billing aggregates from DB (payments + due collections)
        $billing = Billing::find($billingId);
        if ($billing) {
            $paymentsSum = (float) Payment::where('billing_id', $billingId)->whereNull('deleted_at')->sum('amount');
            $dueCollected = (float) \App\Models\DueCollection::where('billing_id', $billingId)->sum('collected_amount');
            $totalPaid = round($paymentsSum + $dueCollected, 2);

            $billing->paid_amt = $totalPaid;
            $payable = round(max(0, (float) ($data['payable_amount'] ?? $billing->payable_amount ?? $billing->total ?? 0)), 2);
            $billing->payable_amount = $payable;
            $billing->due_amount = max(0, round($payable - $billing->paid_amt, 2));
            $billing->payment_status = $this->determinePaymentStatus($billing->paid_amt, $payable, $billing->total ?? 0, $data['receiving_amt'] ?? 0, $billing->return_amt ?? 0);
            $billing->invoice_amount = $billing->paid_amt;

            $requestedReturn = max(0, (float) ($data['return_amt'] ?? $data['return_amount'] ?? 0));
            $billing->return_amt = round($requestedReturn, 2);
            if ($billing->return_amt <= 0.0001 && (float) ($data['paid_amt'] ?? 0) > (float) ($payable)) {
                $billing->return_amt = round(max(0, (float) ($data['paid_amt'] ?? 0) - (float) ($payable)), 2);
            }

            $billing->save();
        }
    }

    private function generateRadiologyNumber($lastRadiology = null)
    {
        $prefix = web_setting_prefix('radiology_bill_prefix', 'RADB');
        $year = date('Y');

        if ($lastRadiology && $lastRadiology->radiology_no) {
            $lastNumber = (int) substr($lastRadiology->radiology_no, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $year . $newNumber;
    }

        private function nextSequentialBillingNumber(string $field, string $prefix, int $digits): string
    {
        $ym = now()->format('Ym');
        $like = $prefix . $ym . '%';

        $lastValue = Billing::withTrashed()
            ->where($field, 'like', $like)
            ->lockForUpdate()
            ->orderBy($field, 'desc')
            ->value($field);

        $lastNumber = $lastValue ? (int) substr($lastValue, -$digits) : 0;

        return $prefix . $ym . str_pad((string) ($lastNumber + 1), $digits, '0', STR_PAD_LEFT);
    }

    private function generateBillNumber($lastBilling = null)
    {
        $prefix = web_setting_prefix('billing_bill_prefix', 'BILL');
        return $this->nextSequentialBillingNumber('bill_number', $prefix, 4);
    }

    private function generateInvoiceNumber($lastBilling = null)
    {
        $prefix = web_setting_prefix('billing_bill_prefix', 'BILL');
        // Keep the existing compact format: PREFIXYYYYMMxxxxx
        return $this->nextSequentialBillingNumber('invoice_number', $prefix, 5);
    }

    private function generateCaseNumber($lastBilling = null)
    {
        return $this->nextSequentialBillingNumber('case_number', 'CASE', 4);
    }


    public function destroy($id)
    {

        DB::beginTransaction();

        try {
            $billingInfo = Billing::find($id);

            if ($this->billingService->deleteBIllingWithPathoRadioPharm($id)) {
                $message = 'Billing deleted successfully';
                $this->storeAdminWorkLog($id, 'billings', $message);
                ActivityLogService::logDelete(
                    'Billing',
                    $id,
                    $billingInfo?->bill_number ?? ('Billing#' . $id),
                    ActivityLogService::buildBillingDeleteMeta($billingInfo)
                );

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Billing.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingController', 'destroy', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function changeStatus(Request $request, $id, $status)
    {
        DB::beginTransaction();

        try {

            $dataInfo = $this->billingService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Billing ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'billings', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Billing.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function pendingList()
    {
        return Inertia::render(
            'Backend/Billing/PendingList',
            [
                'pageTitle' => fn() => 'Pending Billing List',
                'tableHeaders' => fn() => $this->getpendingListTableHeaders(),
                'dataFields' => fn() => $this->datapendingListFields(),
                'datas' => fn() => $this->getpendingListDatas(),
            ]
        );
    }

    private function getpendingListDatas()
    {
        $nameFilter = trim((string) request('name', ''));

        $billingRows = $this->billingService->pendingList()
            ->when($nameFilter !== '', function ($query) use ($nameFilter) {
                $query->whereHas('patient', function ($patientQuery) use ($nameFilter) {
                    $patientQuery->where('name', 'like', '%' . $nameFilter . '%');
                });
            })
            ->get()
            ->map(function ($data) {
                $customData = new \stdClass();
                $customData->sort_at = $data->created_at;
                $customData->bill_number = $data->bill_number;
                $customData->row_id = $data->id;
                $customData->row_type = 'billing';
                $customData->case_number = $data->case_number;
                $customData->patient_id = $data?->patient?->name ?? '';
                $customData->total = number_format((float) ($data->total ?? 0), 2);
                $customData->paid_amt = number_format((float) ($data->paid_amt ?? 0), 2);
                $customData->due_amount = (float) ($data->due_amount ?? 0);
                $customData->due_amount_display = number_format((float) ($data->due_amount ?? 0), 2);
                if (!empty($data->delivery_date)) {
                    try {
                        $customData->delivery_date = ($data->delivery_date instanceof \DateTime)
                            ? $data->delivery_date->format('d-m-Y h:i A')
                            : Carbon::parse($data->delivery_date)->format('d-m-Y h:i A');
                    } catch (\Throwable $e) {
                        $customData->delivery_date = (string) $data->delivery_date;
                    }
                } else {
                    $customData->delivery_date = '';
                }
                $customData->created_by = $data?->admin?->name ?? '';
                $customData->created_at_display = $data->created_at instanceof \DateTime
                    ? $data->created_at->format('d-m-Y h:i A')
                    : (string) $data->created_at;
                $customData->payment_status = $data->payment_status;
                $customData->hasLink = true;

                $links = [];

                if (
                    $data->payment_status !== 'Paid' &&
                    (float) $data->due_amount > 0 &&
                    \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->check('billing-due-collect')
                ) {
                    $links[] = [
                        'linkClass' => 'bg-purple-600 text-white semi-bold',
                        'link' => route('backend.due.collect', $data->id),
                        'linkLabel' => 'Due Collect',
                        'action_name' => 'due-collect',
                        'action_id' => 'billing|' . $data->id,
                    ];
                }

                $links[] = [
                    'linkClass' => 'bg-teal-500 text-white semi-bold',
                    'link' => route('backend.download.invoice', [
                        'id' => $data->id,
                        'module' => 'billing',
                        'fast_open' => 1,
                    ]),
                    'linkLabel' => 'Invoice',
                    'target' => '_blank',
                ];

                $customData->links = $links;

                return $customData;
            });

        $opdRows = OpdPatient::query()
            ->with(['patient', 'doctor'])
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('payment_status', '!=', 'Paid')
            ->where('balance_amount', '>', 0)
            ->when($nameFilter !== '', function ($query) use ($nameFilter) {
                $query->whereHas('patient', function ($patientQuery) use ($nameFilter) {
                    $patientQuery->where('name', 'like', '%' . $nameFilter . '%');
                });
            })
            ->get()
            ->map(function ($data) {
                $customData = new \stdClass();
                $customData->sort_at = $data->created_at;
                $customData->bill_number = 'OPD-' . str_pad((string) $data->id, 4, '0', STR_PAD_LEFT);
                $customData->row_id = $data->id;
                $customData->row_type = 'opd';
                $customData->case_number = 'OPD';
                $customData->patient_id = $data?->patient?->name ?? '';
                $customData->total = number_format((float) ($data->amount ?? 0), 2);
                $customData->paid_amt = number_format((float) ($data->paid_amount ?? 0), 2);
                $customData->due_amount = (float) ($data->balance_amount ?? 0);
                $customData->due_amount_display = number_format((float) ($data->balance_amount ?? 0), 2);
                if (!empty($data->appointment_date)) {
                    try {
                        $customData->delivery_date = ($data->appointment_date instanceof \DateTime)
                            ? $data->appointment_date->format('d-m-Y h:i A')
                            : Carbon::parse($data->appointment_date)->format('d-m-Y h:i A');
                    } catch (\Throwable $e) {
                        $customData->delivery_date = (string) $data->appointment_date;
                    }
                } else {
                    $customData->delivery_date = '';
                }
                $customData->created_by = $data?->doctor?->name ?? '';
                $customData->created_at_display = $data->created_at instanceof \DateTime
                    ? $data->created_at->format('d-m-Y h:i A')
                    : (string) $data->created_at;
                $customData->payment_status = $data->payment_status;
                $customData->hasLink = true;

                $links = [];

                if (\Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->check('billing-due-collect')) {
                    $links[] = [
                        'linkClass' => 'bg-purple-600 text-white semi-bold',
                        'link' => route('backend.opd.due.collect', $data->id),
                        'linkLabel' => 'Due Collect',
                        'action_name' => 'due-collect',
                        'action_id' => 'opd|' . $data->id,
                    ];
                }

                $links[] = [
                    'linkClass' => 'bg-teal-500 text-white semi-bold',
                    'link' => route('backend.download.opd.bill', [
                        'id' => $data->id,
                        'module' => 'opd'
                    ]),
                    'linkLabel' => 'Invoice',
                    'target' => '_blank',
                ];

                $customData->links = $links;

                return $customData;
            });

        $mergedRows = $billingRows
            ->concat($opdRows)
            ->sortByDesc(function ($row) {
                return $row->sort_at;
            })
            ->values();

        $perPage = (int) (request()->numOfData ?? 10);
        $currentPage = (int) request()->get('page', 1);
        $offset = max(0, ($currentPage - 1) * $perPage);

        $pageRows = $mergedRows
            ->slice($offset, $perPage)
            ->values()
            ->map(function ($row, $index) use ($offset) {
                $row->index = $offset + $index + 1;
                unset($row->sort_at);
                return $row;
            });

        return regeneratePagination($pageRows, $mergedRows->count(), $perPage, $currentPage);
    }

    public function refundList()
    {
        return Inertia::render(
            'Backend/Billing/RefundList',
            [
                'pageTitle' => function () {
                    return 'Refund Billing List';
                },
                'tableHeaders' => function () {
                    return $this->getRefundListTableHeaders();
                },
                'dataFields' => function () {
                    return $this->dataRefundListFields();
                },
                'datas' => function () {
                    return $this->getRefundListDatas();
                },
                'filters' => function () {
                    return [
                        'name' => trim((string) request('name', '')),
                        'numOfData' => (int) request()->get('numOfData', 10),
                    ];
                },
            ]
        );
    }

    private function getRefundListDatas()
    {
        $nameFilter = trim((string) request('name', ''));

        $billingRows = Billing::with(['patient', 'payments'])
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('return_amt', '>', 0.0001)
            ->where('total', '>', 0)
            ->whereNotNull('created_at')
            ->whereRaw("TRIM(COALESCE(bill_number, '')) <> ''")
            ->whereHas('patient', function ($patientQuery) {
                $patientQuery->whereNotNull('name')
                    ->whereRaw("TRIM(name) <> ''");
            })
            ->when($nameFilter !== '', function ($query) use ($nameFilter) {
                $query->where(function ($q) use ($nameFilter) {
                    $q->where('bill_number', 'like', '%' . $nameFilter . '%')
                        ->orWhere('invoice_number', 'like', '%' . $nameFilter . '%')
                        ->orWhereHas('patient', function ($patientQuery) use ($nameFilter) {
                            $patientQuery->where('name', 'like', '%' . $nameFilter . '%');
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($data) {
                return $this->buildRefundListRowData($data);
            });

        $perPage = (int) (request()->numOfData ?? 10);
        $currentPage = (int) request()->get('page', 1);
        $offset = max(0, ($currentPage - 1) * $perPage);

        $pageRows = $billingRows
            ->slice($offset, $perPage)
            ->values()
            ->map(function ($row, $index) use ($offset) {
                $row->index = $offset + $index + 1;
                unset($row->sort_at);
                return $row;
            });

        return regeneratePagination($pageRows, $billingRows->count(), $perPage, $currentPage);
    }

    private function buildRefundListRowData($data): \stdClass
    {
        $payments = $this->resolveRefundRowValue($data, 'payments');

        $latestPayment = null;
        if ($payments instanceof \Illuminate\Support\Collection) {
            $latestPayment = $payments->sortByDesc('created_at')->first();
        } elseif (is_array($payments) && count($payments) > 0) {
            $latestPayment = collect($payments)->sortByDesc('created_at')->first();
        } elseif (is_object($payments) && method_exists($payments, 'sortByDesc')) {
            $latestPayment = $payments->sortByDesc('created_at')->first();
        }

        $paidAt = $latestPayment?->created_at ?? null;

        $patient = $this->resolveRefundRowValue($data, 'patient');
        $patientName = '';
        if (is_object($patient)) {
            $patientName = trim((string) ($this->resolveRefundRowValue($patient, 'name', '') ?? ''));
        } elseif (is_array($patient)) {
            $patientName = trim((string) ($patient['name'] ?? ''));
        }

        $billId = $this->resolveRefundRowValue($data, 'id');
        $billNumber = trim((string) ($this->resolveRefundRowValue($data, 'bill_number', '') ?? ''));
        $createdAt = $this->resolveRefundRowValue($data, 'created_at');

        $total = (float) ($this->resolveRefundRowValue($data, 'total', 0) ?? 0);
        $paidAmount = (float) ($this->resolveRefundRowValue($data, 'paid_amt', 0) ?? 0);
        $payableAmount = (float) ($this->resolveRefundRowValue($data, 'payable_amount', $total) ?? $total);

        $returnAmount = 0.0;
        if (is_object($data) && method_exists($data, 'getEffectiveRefundAmount')) {
            $returnAmount = (float) $data->getEffectiveRefundAmount();
        } else {
            $returnAmount = (float) ($this->resolveRefundRowValue($data, 'return_amt', 0) ?? 0);
            if ($returnAmount <= 0.0001) {
                $returnAmount = (float) ($this->resolveRefundRowValue($data, 'return_amount', 0) ?? 0);
            }
        }

        $paymentStatus = 'Pending';
        if (is_object($data) && method_exists($data, 'getPaymentStatus')) {
            $paymentStatus = (string) $data->getPaymentStatus();
        } else {
            if ($paidAmount >= $payableAmount) {
                $paymentStatus = $returnAmount > 0.0001 ? 'Partial' : 'Paid';
            } elseif ($paidAmount > 0.0001) {
                $paymentStatus = 'Partial';
            } else {
                $paymentStatus = 'Pending';
            }
        }

        $customData = new \stdClass();
        $customData->sort_at = $createdAt;
        $customData->bill_number = $billNumber !== '' ? $billNumber : 'N/A';
        $customData->row_id = $billId;
        $customData->row_type = 'billing';
        $customData->patient_name = $patientName !== '' ? $patientName : 'N/A';
        $customData->total = number_format($total, 2);
        $customData->paid_amt = number_format($paidAmount, 2);
        $customData->return_amt = $returnAmount;
        $customData->return_amt_display = number_format($returnAmount, 2);
        $customData->payment_status = $paymentStatus;
        $customData->paid_at_display = $paidAt instanceof \DateTime
            ? $paidAt->format('d-m-Y h:i A')
            : ($paidAt ? (string) $paidAt : '');
        $customData->created_at_display = $createdAt
            ? Carbon::parse($createdAt)->format('d-m-Y h:i A')
            : 'N/A';
        $customData->hasLink = true;

        $invoiceLink = '#';
        if ($billId) {
            try {
                if (function_exists('route')) {
                    $invoiceLink = route('backend.download.invoice', [
                        'id' => $billId,
                        'module' => 'billing',
                        'fast_open' => 1,
                    ]);
                }
            } catch (\Throwable $e) {
                $invoiceLink = '/download-invoice?id=' . urlencode((string) $billId) . '&module=billing&fast_open=1';
            }
        }

        $customData->links = [
            [
                'linkClass' => 'bg-orange-600 text-white semi-bold',
                'linkLabel' => 'Refund',
                'action_name' => 'refund',
                'action_id' => $billId,
            ],
            [
                'linkClass' => 'bg-teal-500 text-white semi-bold',
                'link' => $invoiceLink,
                'linkLabel' => 'Invoice',
                'target' => '_blank',
            ],
        ];

        return $customData;
    }

    private function resolveRefundRowValue($data, string $key, $default = null)
    {
        if (is_array($data)) {
            return array_key_exists($key, $data) ? $data[$key] : $default;
        }

        if (is_object($data)) {
            if (method_exists($data, 'getAttribute')) {
                try {
                    $value = $data->getAttribute($key);
                    if ($value !== null) {
                        return $value;
                    }
                } catch (\Throwable $e) {
                    // fallback to direct access below
                }
            }

            return $data->{$key} ?? $default;
        }

        return $default;
    }

    private function dataRefundListFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'bill_number', 'class' => 'text-center'],
            ['fieldName' => 'patient_name', 'class' => 'text-center'],
            ['fieldName' => 'total', 'class' => 'text-center'],
            ['fieldName' => 'paid_amt', 'class' => 'text-center'],
            ['fieldName' => 'payment_status', 'class' => 'text-center'],
            ['fieldName' => 'paid_at_display', 'class' => 'text-center whitespace-nowrap px-4 py-2'],
            ['fieldName' => 'return_amt_display', 'class' => 'text-center'],
            ['fieldName' => 'created_at_display', 'class' => 'text-center whitespace-nowrap px-4 py-2'],
        ];
    }

    private function getRefundListTableHeaders()
    {
        return [
            'Sl/No',
            'Bill Number',
            'Patient',
            'Total',
            'Paid Amount',
            'Payment Status',
            'Paid Date & Time',
            'Refund Amount',
            'Created Date & Time',
            'Action',
        ];
    }

    private function datapendingListFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'bill_number', 'class' => 'text-center'],
            ['fieldName' => 'case_number', 'class' => 'text-center'],
            ['fieldName' => 'patient_id', 'class' => 'text-center'],
            ['fieldName' => 'total', 'class' => 'text-center'],
            ['fieldName' => 'paid_amt', 'class' => 'text-center'],
            ['fieldName' => 'due_amount_display', 'class' => 'text-center'],
            ['fieldName' => 'delivery_date', 'class' => 'text-center'],
            ['fieldName' => 'created_at_display', 'class' => 'text-center'],
            ['fieldName' => 'created_by', 'class' => 'text-center'],
            ['fieldName' => 'payment_status', 'class' => 'text-center'],
        ];
    }
    private function getpendingListTableHeaders()
    {
        return [
            'Sl/No',
            'Bill Number',
            'Case Number',
            'Patient',
            'Total',
            'Paid Amount',
            'Due Amount',
            'Delivery Date',
            'Created Date & Time',
            'Created By',
            'Payment Status',
            'Action',
        ];
    }

    private function handlePatientData($data, $billing = null)
    {
        $patientId = $billing ? $billing->patient_id : null;
        $processedData = $data;

        if ($data['is_new_patient'] && !empty($data['patient_name'])) {
            $patient = Patient::create([
                'name' => $data['patient_name'],
                'phone' => $data['patient_phone'],
                'gender' => $data['patient_gender'],
                'dob' => $data['dob'] ?? null,
                'age' => $data['patient_age'] ?? null,
            ]);

            $patientId = $patient->id;
            $processedData['patient_mobile'] = $data['patient_phone'];
            $processedData['gender'] = $data['patient_gender'];
        } elseif (!empty($data['patient_id']) && (!$billing || $data['patient_id'] != $billing->patient_id)) {
            $patientId = $data['patient_id'];
        } elseif (!empty($data['patient_id']) && $billing && $data['patient_id'] == $billing->patient_id) {
            $patientId = $data['patient_id'];

            $patient = Patient::find($patientId);
            if ($patient) {
                $updateData = [];

                if (isset($data['patient_name']) && $data['patient_name'] != $patient->name) {
                    $updateData['name'] = $data['patient_name'];
                }
                if (isset($data['patient_phone']) && $data['patient_phone'] != $patient->phone) {
                    $updateData['phone'] = $data['patient_phone'];
                    $processedData['patient_mobile'] = $data['patient_phone'];
                }
                if (isset($data['patient_gender']) && $data['patient_gender'] != $patient->gender) {
                    $updateData['gender'] = $data['patient_gender'];
                    $processedData['gender'] = $data['patient_gender'];
                }
                if (isset($data['dob']) && $data['dob'] != $patient->dob) {
                    $updateData['dob'] = $data['dob'];
                }
                if (isset($data['patient_age']) && $data['patient_age'] != $patient->age) {
                    $updateData['age'] = $data['patient_age'];
                }

                if (!empty($updateData)) {
                    $patient->update($updateData);
                }
            }
        } elseif (empty($data['patient_id']) && !$data['is_new_patient']) {
            $patient = Patient::create([
                'name' => $data['patient_name'] ?? 'Walk-in Patient',
                'phone' => $data['patient_mobile'] ?? 'N/A',
                'gender' => $data['gender'] ?? 'Others',
                'dob' => $data['dob'] ?? null,
                'age' => $data['patient_age'] ?? null,
            ]);

            $patientId = $patient->id;
            $processedData['patient_mobile'] = $data['patient_mobile'] ?? 'N/A';
            $processedData['gender'] = $data['gender'] ?? 'Others';
        }

        return [
            'patient_id' => $patientId,
            'processed_data' => $processedData
        ];
    }

    private function hasOpenCounterSessionForAdmin(int $adminId, string $adminName = ''): bool
    {
        if ($adminId <= 0) {
            return false;
        }

        return CashCounterSession::query()
            ->whereRaw('LOWER(status) = ?', ['open'])
            ->where(function ($query) use ($adminId, $adminName) {
                $query->where('created_by', $adminId);

                if ($adminName !== '') {
                    $query->orWhere(function ($subQuery) use ($adminName) {
                        $subQuery->whereNull('created_by')
                            ->where('user_name', $adminName);
                    });
                }
            })
            ->exists();
    }
}
