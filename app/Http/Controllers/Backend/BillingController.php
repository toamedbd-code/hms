<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillingRequest;
use App\Models\Admin;
use App\Models\Billing;
use App\Models\BillingDoctor;
use App\Models\BillItem;
use App\Models\Expense;
use App\Models\MedicineInventory;
use App\Models\Pathology;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Radiology;
use App\Models\Referral;
use App\Models\Test;
use App\Models\OpdPatient;
use App\Models\IpdPatient;
use App\Services\AdminService;
use Illuminate\Support\Facades\DB;
use App\Services\BillingService;
use App\Services\IpdDischargeBillingService;

use App\Services\MedicineInventoryService;
use App\Services\PatientService;
use App\Services\ReferralPersonService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;
use App\Models\PharmacyBill;
use Carbon\Carbon;

class BillingController extends Controller
{
    use SystemTrait;

    protected $billingService, $medicineInventoryService, $adminService, $patientService, $referrerService;

    public function __construct(BillingService $billingService, MedicineInventoryService $medicineInventoryService, AdminService $adminService, PatientService $patientService, ReferralPersonService $referrerService)
    {
        $this->billingService = $billingService;
        $this->medicineInventoryService = $medicineInventoryService;
        $this->adminService = $adminService;
        $this->patientService = $patientService;
        $this->referrerService = $referrerService;

        $this->middleware('auth:admin');

        // Add permission middleware
        $this->middleware('permission:billing', ['only' => ['index']]);
        $this->middleware('permission:billing-create', ['only' => ['create', 'billing', 'billingPage', 'store']]);
        $this->middleware('permission:billing-delete', ['only' => ['destroy']]);
        $this->middleware('permission:billing-edit', ['only' => ['edit', 'update']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/Billing/Index',
            [
                'pageTitle' => fn() => 'Billing List',
                'filters' => fn() => request()->only(['search', 'numOfData']),
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->billingService->activeList();

        $search = trim((string) request()->input('search', request()->input('bill_no', '')));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'like', '%' . $search . '%')
                    ->orWhereHas('patient', function ($patientQuery) use ($search) {
                        $patientQuery->where('name', 'like', '%' . $search . '%');
                    });

                $parsedDate = $this->parseBillingSearchDate($search);
                if ($parsedDate) {
                    $q->orWhereDate('created_at', $parsedDate);
                }
            });
        }


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth()->guard('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->bill_number = $data->bill_number;
            $customData->row_id = $data->id;
            $customData->row_type = 'billing';
            $customData->patient_id = $data?->patient?->name ?? '';
            $customData->total = $data->total;
            $customData->paid_amt = $data->paid_amt;
            $customData->due_amount = $data->due_amount ?? 0;
            $customData->due_amount_display = number_format((float) ($data->due_amount ?? 0), 2);
            $customData->delivery_date = !empty($data->delivery_date) ? Carbon::parse($data->delivery_date)->format('d-m-Y h:i A') : '';
            $customData->created_by = $data?->admin?->name ?? '';
            $customData->payment_status = $data->payment_status;
            $customData->created_at = $data->created_at ? $data->created_at->format('d-m-Y h:i A') : '';

            $customData->hasLink = true;
$links = [];

/*
|--------------------------------------------------------------------------
| Due Collect Button
|--------------------------------------------------------------------------
*/
if (
    isset($data->due_amount) &&
    (float)$data->due_amount > 0 &&
    in_array($data->payment_status, ['Pending', 'Partial']) &&
    \Illuminate\Support\Facades\Gate::forUser($user)->check('billing-due-collect')
) {
    $links[] = [
        'linkClass' => 'bg-purple-600 text-white semi-bold',
        'link' => route('backend.due.collect', $data->id),
        'linkLabel' => 'Due Collect',
        'action_name' => 'due-collect',
        'action_id' => 'billing|' . $data->id,
    ];
}

/*
|--------------------------------------------------------------------------
| Invoice Button
|--------------------------------------------------------------------------
*/
$links[] = [
    'linkClass' => 'bg-teal-500 text-white semi-bold',
    'link' => route('backend.download.invoice', [
        'id' => $data->id,
        'module' => 'billing'
    ]),
    'linkLabel' => 'Invoice',
    'target' => '_blank',
];

/*
|--------------------------------------------------------------------------
| Edit Button
|--------------------------------------------------------------------------
*/
if (\Illuminate\Support\Facades\Gate::forUser($user)->check('billing-edit')) {
    $links[] = [
        'linkClass' => 'bg-yellow-400 text-black semi-bold',
        'link' => route('backend.billing.edit', $data->id),
        'linkLabel' => 'Edit',
    ];
}

/*
|--------------------------------------------------------------------------
| Delete Button
|--------------------------------------------------------------------------
*/
if (\Illuminate\Support\Facades\Gate::forUser($user)->check('billing-delete')) {
    $links[] = [
        'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
        'link' => route('backend.billing.destroy', $data->id),
        'linkLabel' => 'Delete',
    ];
}

$customData->links = $links;
            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
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

    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'bill_number', 'class' => 'text-center'],
            ['fieldName' => 'patient_id', 'class' => 'text-center'],
            ['fieldName' => 'total', 'class' => 'text-center'],
            ['fieldName' => 'paid_amt', 'class' => 'text-center'],
            ['fieldName' => 'due_amount_display', 'class' => 'text-center'],
            ['fieldName' => 'delivery_date', 'class' => 'text-center whitespace-nowrap'],
            ['fieldName' => 'created_by', 'class' => 'text-center'],
            ['fieldName' => 'payment_status', 'class' => 'text-center'],
            ['fieldName' => 'created_at', 'class' => 'text-center whitespace-nowrap'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Bill Number',
            'Patient',
            'Total',
            'Paid Amount',
            'Due Amount',
            'Delivery Date',
            'Created By',
            'Payment Status',
            'Date',
            'Action',
        ];
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
            ->where('case_number', 'like', '%' . $caseId . '%')
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

        // Get all active tests
        $pathologyAndRadiologyTests = Test::whereIn('category_type', ['Pathology', 'Radiology'])
            ->where('status', 'Active')
            ->select('id', 'category_type', 'test_name', 'test_short_name', 'report_days', 'tax', 'standard_charge', 'amount')
            ->orderBy('test_name')
            ->get()
            ->map(function ($test) {
                return [
                    'id' => $test->id,
                    'category_type' => $test->category_type,
                    'test_name' => $test->test_name,
                    'test_short_name' => $test->test_short_name,
                    'report_days' => $test->report_days,
                    'tax' => $test->tax,
                    'standard_charge' => $test->standard_charge,
                    'amount' => $test->amount,
                ];
            });

        $medicineInventories = $this->medicineInventoryService->activeList();
        $doctors = $this->adminService->activeDoctors();
        $patients = $this->patientService->activeList();
        $referrers = $this->referrerService->activeList();
        $authInfo = $this->adminService->getAuthInfo();
        // dd($pathologyAndRadiologyTests);

        return Inertia::render(
            'Backend/Billing/BillingPage',
            [
                'pageTitle' => fn() => 'Billing Page',
                'billnumber' => fn() => $lastBillNumber,
                'pathologyAndRadiologyTests' => fn() => $pathologyAndRadiologyTests,
                'medicineInventories' => fn() => $medicineInventories,
                'doctors' => fn() => $doctors,
                'patients' => fn() => $patients,
                'referrers' => fn() => $referrers,
                'authInfo' => fn() => $authInfo,
            ]
        );
    }

    public function store(BillingRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            $doctorInfo = $this->handleDoctor($data['doctor_name'] ?? null);

            // Use unified patient handler
            $patientResult = $this->handlePatientData($data);
            $patientId = $patientResult['patient_id'];
            $data = $patientResult['processed_data'];

            // Duplicate billing check (same patient same day). If billing_date provided
            // use that date for duplicate detection so bills can be created for other dates.
            $billDateToCheck = $data['billing_date'] ?? now()->toDateString();
            $existingBill = Billing::where('patient_id', $patientId)
                ->whereDate('created_at', $billDateToCheck)
                ->first();

if ($existingBill) {
    DB::rollBack();
    return back()->with('error', 'This patient already billed today!');
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
                'discount_type' => $data['discount_type'] ?? 'percentage',
                'payable_amount' => $data['payable_amount'] ?? $data['total'],
                'paid_amt' => $data['paid_amt'],
                'invoice_amount' => $data['paid_amt'],
                'change_amt' => $data['change_amt'] ?? 0,
                'due_amount' => $data['due_amount'] ?? 0,
                'receiving_amt' => $data['receiving_amt'] ?? 0,
                'delivery_date' => $data['delivery_date'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'commission_total' => $data['commission_total'] ?? 0,
                'physyst_amt' => $data['physyst_amt'] ?? 0,
                'commission_slider' => $data['commission_slider'] ?? 0,
                'payment_status' => $this->determinePaymentStatus($data['paid_amt'], $data['payable_amount'], $data['total'], $data['receiving_amt']),
                'created_by' => auth('admin')->user()->id,
            ];

            $billing = null;
            $attempts = 0;

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
                        $billingData['created_at'] = Carbon::parse($data['billing_date'] . ' ' . $time)->toDateTimeString();
                    }

                    $billing = $this->billingService->create($billingData);
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
                    $category = strtolower($item['category']);
                    $commissionRate = 0;

                    switch ($category) {
                        case 'pathology':
                            $commissionRate = $referrer->pathology_commission ?? 0;
                            break;
                        case 'radiology':
                            $commissionRate = $referrer->radiology_commission ?? 0;
                            break;
                        case 'medicine':
                            $commissionRate = $referrer->pharmacy_commission ?? 0;
                            break;
                    }

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

                Referral::create([
                    'billing_id' => $billing->id,
                    'payee_id' => $data['referrer_id'],
                    'total_commission_amount' => $totalCommission,
                    'category_commissions' => $categoryCommissions,
                    'date' => now(),
                    'total_bill_amount' => $data['total'],
                    'status' => 'Active'
                ]);

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

                BillItem::create([
                    'billing_id' => $billing->id,
                    'item_id' => $item['id'],
                    'item_name' => $item['name'],
                    'category' => $item['category'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total_amount' => $item['total_amount'],
                    'discount' => $itemProportionalDiscount,
                    'rugound' => $item['rugound'] ?? 0,
                    'net_amount' => $itemNetAmount,
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
                Payment::create([
                    'billing_id' => $billing->id,
                    'amount' => $data['paid_amt'],
                    'payment_method' => $data['pay_mode'],
                    'transaction_id' => $data['card_number'] ?? null,
                    'notes' => $data['remarks'] ?? null,
                    'received_by' => auth('admin')->user()->id,
                    'payment_status' => $this->determinePaymentStatus($data['paid_amt'], $data['payable_amount'], $data['total'], $data['receiving_amt']),
                ]);
            }

            // Commission expense is recorded on referral payment

            $pathologyItems = collect($data['items'])->where('category', 'Pathology');
            if ($pathologyItems->isNotEmpty()) {
                $this->createPathologyRecord($billing, $pathologyItems, $data);
            }

            $radiologyItems = collect($data['items'])->where('category', 'Radiology');
            if ($radiologyItems->isNotEmpty()) {
                $this->createRadiologyRecord($billing, $radiologyItems, $data);
            }

            $medicineItems = collect($data['items'])->where('category', 'Medicine');
            if ($medicineItems->isNotEmpty()) {
                $this->createPharmacyBillRecord($billing, $medicineItems, $data);
            }

                        $message = 'Billing created successfully with Bill No: ' . ($billing->bill_number ?? ''); 

            $this->storeAdminWorkLog($billing->id, 'billings', $message);
                        ActivityLogService::logCreate(
                            'Billing',
                            $billing->id,
                            $billing->bill_number ?? ('Billing#' . $billing->id),
                            [
                                'bill_number' => $billing->bill_number,
                                'invoice_number' => $billing->invoice_number,
                                'case_number' => $billing->case_number,
                                'patient_id' => $billing->patient_id,
                                'total' => $billing->total,
                            ]
                        );

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message)
                ->with('billId', $billing->id);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingController', 'store', substr($err->getMessage(), 0, 1000));

            $message = "Server error occurred: " . $err->getMessage();
            return redirect()
                ->back()
                ->withInput()
                ->with('errorMessage', $message);
        }
    }


    public function searchDoctors(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:2'
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
            'date' => now(),
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
        $totalPathologyDiscount = $pathologyItems->sum('discount');
        $pathologyNetAmount = $pathologyItems->sum('net_amount');

        $existingPathology = Pathology::where('bill_no', $billing->bill_number)->first();

        if ($existingPathology) {
            $existingPathology->update([
                'patient_id' => $billing->patient_id,
                'apply_tpa' => false,
                'payee_id' => $data['referrer_id'],
                'date' => now()->format('Y-m-d'),
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
                'date' => now()->format('Y-m-d'),
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

        $totalRadiologyDiscount = $radiologyItems->sum('discount');
        $radiologyNetAmount = $radiologyItems->sum('net_amount');

        $existingRadiology = Radiology::where('bill_no', $billing->bill_number)->first();

        if ($existingRadiology) {
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

            return Radiology::create($radiologyData);
        }
    }

    private function determinePaymentStatus($paidAmount, $payableAmount, $total, $recevingAmount)
    {
        $paidAmount = floatval($paidAmount);
        $payableAmount = floatval($payableAmount);

        if ($paidAmount >= $payableAmount) {
            return 'Paid';
        } elseif ($paidAmount > 0) {
            return 'Partial';
        } else {
            return 'Pending';
        }
    }

    private function createPharmacyBillRecord($billing, $medicineItems, $data)
    {
        $totalMedicineDiscount = $medicineItems->sum('discount');
        $medicineNetAmount = $medicineItems->sum('net_amount');

        $existingPharmacyBill = PharmacyBill::where('bill_no', $billing->bill_number)->first();

        if ($existingPharmacyBill) {
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

            $vatPercentage = 0;
            $vatAmount = ($medicineNetAmount * $vatPercentage) / 100;

            $pharmacyBillData = [
                'pharmacy_no' => $pharmacyNo,
                'bill_no' => $billing->bill_number,
                'case_id' => $billing->case_number,
                'date' => now()->format('Y-m-d'),
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

        $pathologyAndRadiologyTests = Test::whereIn('category_type', ['Pathology', 'Radiology'])
            ->where('status', 'Active')
            ->select('id', 'category_type', 'test_name', 'test_short_name', 'report_days', 'tax', 'standard_charge', 'amount')
            ->orderBy('test_name')
            ->get()
            ->map(function ($test) {
                return [
                    'id' => $test->id,
                    'category_type' => $test->category_type,
                    'test_name' => $test->test_name,
                    'test_short_name' => $test->test_short_name,
                    'report_days' => $test->report_days,
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

    public function update(BillingRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
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

            // Store old quantities for medicine inventory rollback
            $oldBillItems = BillItem::where('billing_id', $id)->get();

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
                'payable_amount' => $data['payable_amount'] ?? $data['total'],
                'paid_amt' => $data['paid_amt'],
                'change_amt' => $data['change_amt'] ?? 0,
                'due_amount' => $data['due_amount'] ?? 0,
                'receiving_amt' => $data['receiving_amt'] ?? 0,
                'delivery_date' => $data['delivery_date'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'commission_total' => $data['commission_total'] ?? 0,
                'physyst_amt' => $data['physyst_amt'] ?? 0,
                'commission_slider' => $data['commission_slider'] ?? 0,
                'payment_status' => $this->determinePaymentStatus($data['paid_amt'], $data['payable_amount'], $data['total'], $data['receiving_amt']),
                'updated_by' => auth('admin')->user()->id,
                'doctor_id' => $doctorInfo['doctor_id'],
                'doctor_type' => $doctorInfo['doctor_type'],
                'doctor_name' => $doctorInfo['doctor_name'],
            ];

            // Update billing record
            $updatedBilling = $this->billingService->update($billingData, $id);

            if (!$updatedBilling) {
                throw new Exception('Failed to update billing record');
            }

            // Delete existing billing items
            BillItem::where('billing_id', $id)->delete();

            // Create new billing items and update medicine inventory
            foreach ($data['items'] as $item) {
                BillItem::create([
                    'billing_id' => $id,
                    'item_id' => $item['id'],
                    'item_name' => $item['name'],
                    'category' => $item['category'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total_amount' => $item['total_amount'],
                    'discount' => $item['discount'] ?? 0,
                    'rugound' => $item['rugound'] ?? 0,
                    'net_amount' => $item['net_amount'],
                    'status' => 'Active'
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

            $this->updatePaymentRecords($id, $data);

            // Update related records (pathology, radiology, pharmacy)
            $pathologyItems = collect($data['items'])->where('category', 'Pathology');
            if ($pathologyItems->isNotEmpty()) {
                $this->createOrUpdatePathologyRecord($billing, $pathologyItems, $data);
            } else {
                Pathology::where('bill_no', $billing->bill_number)->delete();
            }

            $radiologyItems = collect($data['items'])->where('category', 'Radiology');
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
                    $category = strtolower($item['category']);
                    $commissionRate = 0;

                    switch ($category) {
                        case 'pathology':
                            $commissionRate = $referrer->pathology_commission ?? 0;
                            break;
                        case 'radiology':
                            $commissionRate = $referrer->radiology_commission ?? 0;
                            break;
                        case 'medicine':
                            $commissionRate = $referrer->pharmacy_commission ?? 0;
                            break;
                    }

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

                Referral::create([
                    'billing_id' => $id,
                    'payee_id' => $data['referrer_id'],
                    'total_commission_amount' => $totalCommission,
                    'category_commissions' => $categoryCommissions,
                    'date' => now(),
                    'total_bill_amount' => $data['total'],
                    'status' => 'Active'
                ]);

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

            DB::commit();

            return redirect()
                ->route('backend.billing.list')
                ->with('successMessage', $message)
                ->with('billId', $billing->id);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingController', 'update', substr($err->getMessage(), 0, 1000));

            $message = "Server error occurred: " . $err->getMessage();
            return redirect()
                ->back()
                ->withInput()
                ->with('errorMessage', $message);
        }
    }

    private function createOrUpdatePathologyRecord($billing, $pathologyItems, $data)
    {
        $totalPathologyDiscount = $pathologyItems->sum('discount');
        $pathologyNetAmount = $pathologyItems->sum('net_amount');

        $pathologyData = [
            'patient_id' => $billing->patient_id,
            'apply_tpa' => false,
            'payee_id' => $data['referrer_id'] ?? null,
            'date' => now()->format('Y-m-d'),
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

        $existingPathology = Pathology::where('bill_no', $billing->bill_number)->first();

        if ($existingPathology) {
            $existingPathology->update($pathologyData);
            return $existingPathology;
        } else {
            $lastPathology = Pathology::withTrashed()->orderby('id', 'desc')->first();
            $pathologyNo = $this->generatePathologyNumber($lastPathology);

            $pathologyData['pathology_no'] = $pathologyNo;
            $pathologyData['bill_no'] = $billing->bill_number;
            $pathologyData['case_id'] = $billing->case_number;
            $pathologyData['created_by'] = auth('admin')->user()->id;

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
                    Payment::create([
                        'billing_id' => $billingId,
                        'amount' => $paymentAmount,
                        'payment_method' => $data['pay_mode'] ?? null,
                        'transaction_id' => $data['card_number'] ?? null,
                        'notes' => $data['remarks'] ?? null,
                        'received_by' => auth('admin')->user()->id,
                        'payment_status' => $this->determinePaymentStatus($incomingPaid, $data['payable_amount'] ?? 0, $data['total'] ?? 0, $data['receiving_amt'] ?? 0),
                    ]);
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
            $payable = floatval($data['payable_amount'] ?? $billing->payable_amount ?? $billing->total ?? 0);
            $billing->due_amount = max(0, round($payable - $billing->paid_amt, 2));
            $billing->payment_status = $this->determinePaymentStatus($billing->paid_amt, $payable, $billing->total ?? 0, $data['receiving_amt'] ?? 0);
            $billing->invoice_amount = $billing->paid_amt;
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
        // Keep the existing compact format: INVYYYYMMxxxxx
        return $this->nextSequentialBillingNumber('invoice_number', 'INV', 5);
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
                    [
                        'bill_number' => $billingInfo?->bill_number,
                        'invoice_number' => $billingInfo?->invoice_number,
                        'case_number' => $billingInfo?->case_number,
                        'patient_id' => $billingInfo?->patient_id,
                    ]
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
                $customData->delivery_date = $data->delivery_date;
                $customData->created_by = $data?->admin?->name ?? '';
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
                        'module' => 'billing'
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
                $customData->delivery_date = $data->appointment_date;
                $customData->created_by = $data?->doctor?->name ?? '';
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
}
