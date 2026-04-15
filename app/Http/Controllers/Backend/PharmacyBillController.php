<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PharmacyBillRequest;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\MedicineCategory;
use App\Models\MedicineInventory;
use App\Models\Patient;
use App\Models\PharmacyBill;
use App\Models\Payment;
use App\Services\BillingService;
use App\Services\PatientService;
use App\Services\PharmacyBillService;
use App\Services\AdminService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class PharmacyBillController extends Controller
{
    use SystemTrait;

    protected $pharmacyBillService, $patientService, $billingService, $adminService;

    public function __construct(
        PharmacyBillService $pharmacyBillService,
        PatientService $patientService,
        BillingService $billingService,
        AdminService $adminService
    ) {
        $this->pharmacyBillService = $pharmacyBillService;
        $this->patientService = $patientService;
        $this->billingService = $billingService;
        $this->adminService = $adminService;

        $this->middleware('auth:admin');
        $this->middleware('permission:pharmacy-bill-list');
        $this->middleware('permission:pharmacy-bill-invoice');
        $this->middleware('permission:pharmacy-bill-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:pharmacy-bill-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:pharmacy-bill-status', ['only' => ['changeStatus']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/PharmacyBill/Index',
            [
                'pageTitle' => 'Pharmacy Bill List',
                'tableHeaders' => $this->getTableHeaders(),
                'dataFields' => $this->dataFields(),
                'datas' => $this->getDatas(),
                'filters' => [
                    'pharmacy_no' => request()->get('pharmacy_no', ''),
                    'patient_name' => request()->get('patient_name', ''),
                    'date_from' => request()->get('date_from', ''),
                    'date_to' => request()->get('date_to', ''),
                    'payment_status' => request()->get('payment_status', ''),
                    'numOfData' => request()->get('numOfData', 10),
                ],
            ]
        );
    }

    public function export(Request $request, string $format)
    {
        $format = strtolower(trim($format));
        if (!in_array($format, ['csv', 'pdf'], true)) {
            return redirect()->back()->with('errorMessage', 'Unsupported export format.');
        }

        $rows = $this->buildFilteredListQuery($request)
            ->with('patient')
            ->orderByDesc('id')
            ->limit(50000)
            ->get();

        $billingMap = Billing::query()
            ->whereIn('bill_number', $rows->pluck('bill_no')->filter()->values())
            ->get()
            ->keyBy('bill_number');

        $filename = 'pharmacy_bill_list_' . now()->format('Ymd_His');

        if ($format === 'csv') {
            ActivityLogService::logDownload('Pharmacy Bill', $filename . '.csv', 'CSV');

            return response()->streamDownload(function () use ($rows, $billingMap) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Pharmacy No', 'Bill No', 'Patient', 'Date', 'Total', 'Paid', 'Due', 'Payment Status']);

                foreach ($rows as $row) {
                    $billing = $billingMap->get($row->bill_no);
                    $paid = (float) ($billing?->paid_amt ?? $row->payment_amount ?? 0);
                    $due = (float) ($billing?->due_amount ?? max((float) $row->net_amount - (float) ($row->payment_amount ?? 0), 0));
                    $status = strtolower((string) ($billing?->payment_status ?? ($due <= 0 ? 'paid' : 'due')));

                    fputcsv($handle, [
                        (string) $row->pharmacy_no,
                        (string) $row->bill_no,
                        trim((string) (($row->patient?->first_name ?? '') . ' ' . ($row->patient?->last_name ?? ''))) ?: 'N/A',
                        (string) $row->date,
                        number_format((float) $row->net_amount, 2, '.', ''),
                        number_format($paid, 2, '.', ''),
                        number_format($due, 2, '.', ''),
                        ucfirst($status),
                    ]);
                }

                fclose($handle);
            }, $filename . '.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $htmlRows = $rows->map(function ($row) use ($billingMap) {
            $billing = $billingMap->get($row->bill_no);
            $paid = (float) ($billing?->paid_amt ?? $row->payment_amount ?? 0);
            $due = (float) ($billing?->due_amount ?? max((float) $row->net_amount - (float) ($row->payment_amount ?? 0), 0));
            $status = strtolower((string) ($billing?->payment_status ?? ($due <= 0 ? 'paid' : 'due')));

            return [
                'pharmacy_no' => (string) $row->pharmacy_no,
                'bill_no' => (string) $row->bill_no,
                'patient' => trim((string) (($row->patient?->first_name ?? '') . ' ' . ($row->patient?->last_name ?? ''))) ?: 'N/A',
                'date' => (string) $row->date,
                'total' => number_format((float) $row->net_amount, 2),
                'paid' => number_format($paid, 2),
                'due' => number_format($due, 2),
                'status' => ucfirst($status),
            ];
        });

        $html = view('backend.exports.pharmacy_bill_list_pdf', [
            'rows' => $htmlRows,
            'generated_at' => now()->format('d-M-Y h:i A'),
        ])->render();

        ActivityLogService::logDownload('Pharmacy Bill', $filename . '.pdf', 'PDF');

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($filename . '.pdf');
    }

    private function buildFilteredListQuery(Request $request)
    {
        $query = PharmacyBill::query();

        if ($request->filled('pharmacy_no')) {
            $searchNo = trim((string) $request->pharmacy_no);
            $query->where(function ($q) use ($searchNo) {
                $q->where('pharmacy_no', 'like', '%' . $searchNo . '%')
                    ->orWhere('bill_no', 'like', '%' . $searchNo . '%');
            });
        }

        if ($request->filled('patient_name')) {
            $searchPatient = trim((string) $request->patient_name);
            $query->whereHas('patient', function ($q) use ($searchPatient) {
                // Some deployments use patients.name instead of first_name/last_name.
                if (Schema::hasColumn('patients', 'name')) {
                    $q->where('name', 'like', '%' . $searchPatient . '%');
                    return;
                }

                $q->where(function ($nameQuery) use ($searchPatient) {
                    if (Schema::hasColumn('patients', 'first_name')) {
                        $nameQuery->orWhere('first_name', 'like', '%' . $searchPatient . '%');
                    }

                    if (Schema::hasColumn('patients', 'last_name')) {
                        $nameQuery->orWhere('last_name', 'like', '%' . $searchPatient . '%');
                    }
                });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        if ($request->filled('payment_status')) {
            $status = Str::lower((string) $request->payment_status);
            $query->whereExists(function ($subQuery) use ($status) {
                $subQuery->select(DB::raw(1))
                    ->from('billings')
                    ->whereColumn('billings.bill_number', 'pharmacybills.bill_no')
                    ->whereRaw('LOWER(COALESCE(billings.payment_status, ?)) = ?', ['', $status]);
            });
        }

        return $query;
    }

    private function getDatas()
    {
        $query = $this->buildFilteredListQuery(request());

        $datas = $query->with('patient')->paginate(request()->numOfData ?? 10);
        $datas->appends(request()->query());

        $formatedDatas = collect($datas->items())->values()->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->row_id = null;
            $customData->row_type = 'billing';
            $customData->pharmacy_no = $data->pharmacy_no;
            $customData->bill_no = $data->bill_no;
            $customData->patient_name = $data->patient ? $data->patient->first_name . ' ' . $data->patient->last_name : 'N/A';
            $customData->net_amount = number_format($data->net_amount, 2);
            $customData->paid_amount = number_format((float) ($data->payment_amount ?? 0), 2);
            $customData->due_amount = number_format(max((float) $data->net_amount - (float) ($data->payment_amount ?? 0), 0), 2);
            $customData->date = $data->date;
            $customData->status = getStatusText('Due');

            $billing = Billing::where('bill_number', $data->bill_no)->first();
            if ($billing) {
                $customData->row_id = $billing->id;
                $customData->paid_amount = number_format((float) ($billing->paid_amt ?? 0), 2);
                $customData->due_amount = number_format((float) ($billing->due_amount ?? 0), 2);
                $paymentStatus = strtolower((string) $billing->payment_status);
                if ($paymentStatus === 'paid') {
                    $customData->status = getStatusText('Paid');
                } elseif ($paymentStatus === 'partial') {
                    $customData->status = getStatusText('Partial');
                } else {
                    $customData->status = getStatusText('Due');
                }
            }

            $customData->hasLink = true;
            $user = auth('admin')->user();
            $gate = Gate::forUser($user);

            $customData->links = [];

            $hasDueAmount = $billing && (float) ($billing->due_amount ?? 0) > 0;
            $paymentStatus = strtolower((string) ($billing->payment_status ?? ''));
            $isDueBill = $hasDueAmount && in_array($paymentStatus, ['due', 'partial', 'pending'], true);

            if ($isDueBill && $gate->allows('billing-due-collect')) {
                $customData->links[] = [
                    'linkClass' => 'bg-purple-600 text-white semi-bold',
                    'link' => route('backend.due.collect', ['id' => $billing->id, 'return_to' => request()->fullUrl()]),
                    'linkLabel' => getLinkLabel('Due Collect', null, null),
                    'action_name' => 'due-collect',
                    'action_id' => 'billing|' . $billing->id,
                ];
            }

            if (!$isDueBill && $gate->allows('pharmacy-bill-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.pharmacybill.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($gate->allows('pharmacy-bill-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.pharmacybill.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];

                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.pharmacybill.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ];
            }

            if ($gate->allows('pharmacy-bill-invoice') && $billing) {
                $customData->links[] = [
                    'linkClass' => 'bg-teal-500 text-white semi-bold',
                    'link' => route('backend.download.invoice', ['id' => $billing->id, 'module' => 'pharmacy']),
                    'linkLabel' => getLinkLabel('Invoice', null, null),
                    'target' => '_blank'
                ];
            }

            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'pharmacy_no', 'class' => 'text-center'],
            ['fieldName' => 'bill_no', 'class' => 'text-center'],
            ['fieldName' => 'patient_name', 'class' => 'text-center'],
            ['fieldName' => 'date', 'class' => 'text-center'],
            ['fieldName' => 'net_amount', 'class' => 'text-center'],
            ['fieldName' => 'paid_amount', 'class' => 'text-center'],
            ['fieldName' => 'due_amount', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Pharmacy No',
            'Bill No',
            'Patient Name',
            'Date',
            'Net Amount',
            'Paid Amount',
            'Due Amount',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        $lastBilling = $this->billingService->getLastBilling();
        $billnumber = $this->generateBillNumber($lastBilling);
        $lastPharmacy = PharmacyBill::latest()->first();
        $pharmacyNo = $this->generatePharmacyNumber($lastPharmacy);
        $caseId = $this->generateCaseNumber($lastBilling);

        $medicines = MedicineInventory::where('status', 'Active')
            ->with('category')
            ->select('id', 'medicine_name', 'medicine_unit_selling_price as sale_price', 'medicine_quantity', 'medicine_category_id')
            ->get();

        $patients = Patient::where('status', 'Active')->get();
        $doctors = $this->adminService->activeDoctors();
        $categories = MedicineCategory::where('status', 'Active')->get();

        return Inertia::render(
            'Backend/PharmacyBill/Form',
            [
                'pageTitle' => 'Pharmacy Bill Create',
                'billnumber' => $billnumber,
                'pharmacyNo' => $pharmacyNo,
                'caseId' => $caseId,
                'medicines' => $medicines,
                'patients' => $patients,
                'doctors' => $doctors,
                'categories' => $categories,
            ]
        );
    }

    public function store(PharmacyBillRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            $grossPayment = (float) ($data['payment_amount'] ?? 0);
            $netAmount = (float) ($data['net_amount'] ?? 0);
            $maxReturnAmount = max($grossPayment - $netAmount, 0);
            $returnAmount = min(max((float) ($data['return_amount'] ?? 0), 0), $maxReturnAmount);
            $effectivePaidAmount = min(max($grossPayment - $returnAmount, 0), $netAmount);
            $data['return_amount'] = $returnAmount;
            $data['effective_paid_amount'] = $effectivePaidAmount;

            $pharmacyBillUpdateData = $data;
            unset($pharmacyBillUpdateData['effective_paid_amount']);

            $pharmacyBillData = [
                'pharmacy_no' => $data['pharmacy_no'],
                'bill_no' => $data['bill_no'],
                'case_id' => $data['case_id'],
                'date' => $data['date'],
                'patient_id' => $data['patient_id'],
                'doctor_id' => $data['doctor_id'] ?? null,
                'doctor_name' => $data['doctor_id'] ? ($this->adminService->find($data['doctor_id'])?->name) : null,
                'products' => json_encode($data['products']),
                'subtotal' => $data['subtotal'],
                'discount_percentage' => $data['discount_percentage'],
                'discount_amount' => $data['discount_amount'],
                'vat_percentage' => $data['vat_percentage'],
                'vat_amount' => $data['vat_amount'],
                'extra_discount' => $data['extra_discount'],
                'net_amount' => $data['net_amount'],
                'payment_mode' => $data['payment_mode'],
                'payment_amount' => $grossPayment,
                'return_amount' => $returnAmount,
                'note' => $data['note'],
                'created_by' => auth('admin')->user()->id,
            ];

            $pharmacyBill = $this->pharmacyBillService->create($pharmacyBillData);

            if ($pharmacyBill) {
                $this->assertSufficientStock($data['products']);

                foreach ($data['products'] as $product) {
                    $medicine = MedicineInventory::find($product['productId']);
                    if ($medicine && $medicine->medicine_quantity >= $product['quantity']) {
                        $beforeQty = (float) $medicine->medicine_quantity;
                        $deltaQty = (float) $product['quantity'];
                        $medicine->decrement('medicine_quantity', $product['quantity']);
                        $medicine->refresh();
                        $afterQty = (float) $medicine->medicine_quantity;
                        $this->logStockMovement('ISSUE', $pharmacyBill, $product, $beforeQty, -$deltaQty, $afterQty);
                    }
                }

                $billing = $this->createBillingRecord($pharmacyBill, $data);
                if (!$billing || !$billing->id) {
                    throw new Exception('Billing record could not be created.');
                }

                if ($effectivePaidAmount > 0) {
                    Payment::create([
                        'billing_id' => $billing->id,
                        'pharmacy_bill_id' => $pharmacyBill->id,
                        'amount' => $effectivePaidAmount,
                        'payment_method' => $data['payment_mode'],
                        'received_by' => auth('admin')->user()->id,
                        'payment_status' => $this->determinePaymentStatus($effectivePaidAmount, $netAmount)
                    ]);
                }

                $message = 'Pharmacy Bill created successfully';
                $this->storeAdminWorkLog($pharmacyBill->id, 'pharmacybills', $message);
                ActivityLogService::logCreate(
                    'Pharmacy Bill',
                    $pharmacyBill->id,
                    $pharmacyBill->bill_no ?? ($pharmacyBill->pharmacy_no ?? ('PharmacyBill#' . $pharmacyBill->id)),
                    [
                        'pharmacy_no' => $pharmacyBill->pharmacy_no,
                        'bill_no' => $pharmacyBill->bill_no,
                        'case_id' => $pharmacyBill->case_id,
                        'patient_id' => $pharmacyBill->patient_id,
                        'doctor_id' => $pharmacyBill->doctor_id,
                        'net_amount' => $pharmacyBill->net_amount,
                        'payment_amount' => $pharmacyBill->payment_amount,
                    ]
                );
                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message)
                    ->with('billId', $billing->id);
            } else {
                DB::rollBack();
                $message = "Failed To create Pharmacy Bill.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyBillController', 'store', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function createBillingRecord($pharmacyBill, $data)
    {
        $lastBilling = $this->billingService->getLastBilling();
        $billNumber = $this->generateBillNumber($lastBilling);
        $invoiceNumber = $this->generateInvoiceNumber($lastBilling);
        $caseNumber = $data['case_id'] ?: $this->generateCaseNumber($lastBilling);

        $patient = $this->patientService->find($data['patient_id']);

        $netAmount = (float) ($data['net_amount'] ?? 0);
        $grossPayment = (float) ($data['payment_amount'] ?? 0);
        $effectivePaidAmount = min(max((float) ($data['effective_paid_amount'] ?? $grossPayment), 0), $netAmount);

        $billingData = [
            'invoice_number' => $invoiceNumber,
            'bill_number' => $billNumber,
            'case_number' => $caseNumber,
            'patient_id' => $data['patient_id'],
            'patient_mobile' => $patient?->phone ?? '',
            'gender' => $patient?->gender ?? 'Male',
            'doctor_id' => $data['doctor_id'] ?? null,
            'doctor_name' => $pharmacyBill->doctor_name,
            'card_type' => $data['payment_mode'],
            'pay_mode' => $data['payment_mode'],
            'total' => $netAmount,
            'discount' => $data['discount_amount'],
            'discount_type' => 'flat',
            'payable_amount' => $netAmount,
            'paid_amt' => $effectivePaidAmount,
            'invoice_amount' => $effectivePaidAmount,
            'receiving_amt' => $grossPayment,
            'due_amount' => max($netAmount - $effectivePaidAmount, 0),
            'remarks' => $data['note'],
            'payment_status' => $this->determinePaymentStatus($effectivePaidAmount, $netAmount),
            'created_by' => auth('admin')->user()->id,
        ];

        $billing = $this->billingService->create($billingData);

        foreach ($data['products'] as $product) {
            BillItem::create([
                'billing_id' => $billing->id,
                'item_id' => $product['productId'],
                'item_name' => $product['productName'],
                'category' => 'Medicine',
                'unit_price' => $product['rate'],
                'quantity' => $product['quantity'],
                'total_amount' => $product['amount'],
                'net_amount' => $product['amount'],
            ]);
        }

        return $billing;
    }

    private function determinePaymentStatus($paidAmount, $totalAmount)
    {
        $paidAmount = floatval($paidAmount);
        $totalAmount = floatval($totalAmount);

        if ($paidAmount >= $totalAmount) {
            return 'Paid';
        } elseif ($paidAmount > 0) {
            return 'Partial';
        } else {
            return 'Pending';
        }
    }

    public function edit($id)
    {
        $pharmacyBill = $this->pharmacyBillService->find($id);

        $mappedProducts = [];
        if ($pharmacyBill && $pharmacyBill->products) {
            $products = is_string($pharmacyBill->products)
                ? json_decode($pharmacyBill->products, true)
                : $pharmacyBill->products;

            if (is_array($products)) {
                foreach ($products as $product) {
                    $medicine = MedicineInventory::find($product['productId'] ?? null);

                    $mappedProducts[] = [
                        'productId' => $product['productId'] ?? null,
                        'productName' => $product['productName'] ?? '',
                        'medicineCategory' => $product['medicineCategory'] ?? '',
                        'batchNo' => $product['batchNo'] ?? '',
                        'expiryDate' => $product['expiryDate'] ?? '',
                        'quantity' => (float)($product['quantity'] ?? 1),
                        'availableQty' => $medicine ? (float)$medicine->medicine_quantity : (float)($product['availableQty'] ?? 0),
                        'rate' => (float)($product['rate'] ?? 0),
                        'tax' => (float)($product['tax'] ?? 0),
                        'amount' => (float)($product['amount'] ?? 0),

                        'medicine' => $medicine ? [
                            'id' => $medicine->id,
                            'medicine_name' => $medicine->medicine_name,
                            'medicine_unit_selling_price' => $medicine->medicine_unit_selling_price,
                            'sale_price' => $medicine->medicine_unit_selling_price,
                            'medicine_quantity' => $medicine->medicine_quantity,
                            'medicine_category_id' => $medicine->medicine_category_id,
                            'category' => $medicine->category
                        ] : null
                    ];
                }
            }
        }

        $pharmacyBill->mapped_products = $mappedProducts;

        $patients = Patient::where('status', 'Active')->get();
        $medicines = MedicineInventory::where('status', 'Active')
            ->with('category')
            ->select('id', 'medicine_name', 'medicine_unit_selling_price as sale_price', 'medicine_quantity', 'medicine_category_id')
            ->get();
        $doctors = $this->adminService->activeDoctors();
        $categories = MedicineCategory::where('status', 'Active')->get();

        return Inertia::render(
            'Backend/PharmacyBill/Form',
            [
                'pageTitle' => 'Pharmacy Bill Edit',
                'pharmacybill' => $pharmacyBill,
                'id' => $id,
                'patients' => $patients,
                'medicines' => $medicines,
                'doctors' => $doctors,
                'categories' => $categories,
            ]
        );
    }


    public function update(PharmacyBillRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $oldPharmacyBill = $this->pharmacyBillService->find($id);

            $grossPayment = (float) ($data['payment_amount'] ?? 0);
            $netAmount = (float) ($data['net_amount'] ?? 0);
            $maxReturnAmount = max($grossPayment - $netAmount, 0);
            $returnAmount = min(max((float) ($data['return_amount'] ?? 0), 0), $maxReturnAmount);
            $effectivePaidAmount = min(max($grossPayment - $returnAmount, 0), $netAmount);
            $data['return_amount'] = $returnAmount;
            $data['effective_paid_amount'] = $effectivePaidAmount;

            if ($oldPharmacyBill && $oldPharmacyBill->products) {
                $oldProducts = is_string($oldPharmacyBill->products)
                    ? json_decode($oldPharmacyBill->products, true)
                    : $oldPharmacyBill->products;

                if (is_array($oldProducts)) {
                    foreach ($oldProducts as $oldProduct) {
                        $medicine = MedicineInventory::find($oldProduct['productId']);
                        if ($medicine) {
                            $beforeQty = (float) $medicine->medicine_quantity;
                            $deltaQty = (float) $oldProduct['quantity'];
                            $medicine->increment('medicine_quantity', $oldProduct['quantity']);
                            $medicine->refresh();
                            $afterQty = (float) $medicine->medicine_quantity;
                            $this->logStockMovement('RESTORE', $oldPharmacyBill, $oldProduct, $beforeQty, $deltaQty, $afterQty);
                        }
                    }
                }
            }

            $pharmacyBillUpdateData = $data;
            unset($pharmacyBillUpdateData['effective_paid_amount']);
            $data['updated_by'] = auth('admin')->user()->id;
            $pharmacyBillUpdateData['updated_by'] = $data['updated_by'];
            $dataInfo = $this->pharmacyBillService->update($pharmacyBillUpdateData, $id);

            if ($dataInfo->save()) {
                $this->assertSufficientStock($data['products']);

                foreach ($data['products'] as $product) {
                    $medicine = MedicineInventory::find($product['productId']);
                    if ($medicine && $medicine->medicine_quantity >= $product['quantity']) {
                        $beforeQty = (float) $medicine->medicine_quantity;
                        $deltaQty = (float) $product['quantity'];
                        $medicine->decrement('medicine_quantity', $product['quantity']);
                        $medicine->refresh();
                        $afterQty = (float) $medicine->medicine_quantity;
                        $this->logStockMovement('ISSUE', $dataInfo, $product, $beforeQty, -$deltaQty, $afterQty);
                    }
                }

                $billing = $this->updateBillingRecord($dataInfo, $data);

                $message = 'Pharmacy Bill updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pharmacybills', $message);
                ActivityLogService::logUpdate(
                    'Pharmacy Bill',
                    $dataInfo->id,
                    $dataInfo->bill_no ?? ($dataInfo->pharmacy_no ?? ('PharmacyBill#' . $dataInfo->id)),
                    [
                        'pharmacy_no' => $dataInfo->pharmacy_no,
                        'bill_no' => $dataInfo->bill_no,
                        'case_id' => $dataInfo->case_id,
                        'patient_id' => $dataInfo->patient_id,
                        'doctor_id' => $dataInfo->doctor_id,
                        'net_amount' => $dataInfo->net_amount,
                        'payment_amount' => $dataInfo->payment_amount,
                    ],
                    [
                        'pharmacy_no' => $oldPharmacyBill?->pharmacy_no,
                        'bill_no' => $oldPharmacyBill?->bill_no,
                        'case_id' => $oldPharmacyBill?->case_id,
                        'patient_id' => $oldPharmacyBill?->patient_id,
                        'doctor_id' => $oldPharmacyBill?->doctor_id,
                        'net_amount' => $oldPharmacyBill?->net_amount,
                        'payment_amount' => $oldPharmacyBill?->payment_amount,
                    ]
                );
                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message)
                    ->with('billId', $billing?->id);
            } else {
                DB::rollBack();
                $message = "Failed To update Pharmacy Bill.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyBillController', 'update', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function assertSufficientStock(array $products): void
    {
        $errors = [];

        foreach ($products as $product) {
            $medicineId = $product['productId'] ?? null;
            $requiredQty = (float) ($product['quantity'] ?? 0);
            $medicineName = (string) ($product['productName'] ?? 'Unknown medicine');

            if (!$medicineId || $requiredQty <= 0) {
                continue;
            }

            $medicine = MedicineInventory::query()->find($medicineId);
            $availableQty = (float) ($medicine?->medicine_quantity ?? 0);

            if (!$medicine || $availableQty < $requiredQty) {
                $errors[] = $medicineName . ' stock is insufficient. Available: ' . $availableQty . ', Required: ' . $requiredQty;
            }
        }

        if (!empty($errors)) {
            throw new Exception(implode(' | ', $errors));
        }
    }

    private function updateBillingRecord($pharmacyBill, $data)
    {
        // Find corresponding billing record by case number or bill number
        $billing = Billing::where('case_number', $data['case_id'])
            ->orWhere('bill_number', $data['bill_no'])
            ->first();

        if ($billing) {
            $patient = $this->patientService->find($data['patient_id']);

            $netAmount = (float) ($data['net_amount'] ?? 0);
            $grossPayment = (float) ($data['payment_amount'] ?? 0);
            $effectivePaidAmount = min(max((float) ($data['effective_paid_amount'] ?? $grossPayment), 0), $netAmount);

            $billing->update([
                'patient_id' => $data['patient_id'],
                'patient_mobile' => $patient?->phone ?? '',
                'gender' => $patient?->gender ?? 'Male',
                'doctor_id' => $data['doctor_id'] ?? null,
                'doctor_name' => $pharmacyBill->doctor_name,
                'total' => $netAmount,
                'discount' => $data['discount_amount'],
                'payable_amount' => $netAmount,
                'paid_amt' => $effectivePaidAmount,
                'invoice_amount' => $effectivePaidAmount,
                'receiving_amt' => $grossPayment,
                'due_amount' => max($netAmount - $effectivePaidAmount, 0),
                'remarks' => $data['note'],
                'payment_status' => $this->determinePaymentStatus($effectivePaidAmount, $netAmount),
                'updated_by' => auth('admin')->user()->id,
            ]);

            // Update bill items
            BillItem::where('billing_id', $billing->id)->delete();
            foreach ($data['products'] as $product) {
                BillItem::create([
                    'billing_id' => $billing->id,
                    'item_id' => $product['productId'],
                    'item_name' => $product['productName'],
                    'category' => 'Medicine',
                    'unit_price' => $product['rate'],
                    'quantity' => $product['quantity'],
                    'total_amount' => $product['amount'],
                    'net_amount' => $product['amount'],
                ]);
            }

            return $billing;
        }

        return null;
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $pharmacyBill = $this->pharmacyBillService->find($id);

            // Restore medicine quantities before deletion
            if ($pharmacyBill && $pharmacyBill->products) {
                $products = is_string($pharmacyBill->products)
                    ? json_decode($pharmacyBill->products, true)
                    : $pharmacyBill->products;

                if (is_array($products)) {
                    foreach ($products as $product) {
                        $medicine = MedicineInventory::find($product['productId']);
                        if ($medicine) {
                            $beforeQty = (float) $medicine->medicine_quantity;
                            $deltaQty = (float) $product['quantity'];
                            $medicine->increment('medicine_quantity', $product['quantity']);
                            $medicine->refresh();
                            $afterQty = (float) $medicine->medicine_quantity;
                            $this->logStockMovement('RESTORE', $pharmacyBill, $product, $beforeQty, $deltaQty, $afterQty);
                        }
                    }
                }
            }

            if ($this->pharmacyBillService->delete($id)) {
                // Delete corresponding billing record
                $billing = Billing::where('case_number', $pharmacyBill->case_id)->first();
                if ($billing) {
                    BillItem::where('billing_id', $billing->id)->delete();
                    $billing->delete();
                }

                $message = 'Pharmacy Bill deleted successfully';
                $this->storeAdminWorkLog($id, 'pharmacybills', $message);
                ActivityLogService::logDelete(
                    'Pharmacy Bill',
                    $id,
                    $pharmacyBill?->bill_no ?? ($pharmacyBill?->pharmacy_no ?? ('PharmacyBill#' . $id)),
                    [
                        'pharmacy_no' => $pharmacyBill?->pharmacy_no,
                        'bill_no' => $pharmacyBill?->bill_no,
                        'case_id' => $pharmacyBill?->case_id,
                        'patient_id' => $pharmacyBill?->patient_id,
                        'doctor_id' => $pharmacyBill?->doctor_id,
                        'net_amount' => $pharmacyBill?->net_amount,
                        'payment_amount' => $pharmacyBill?->payment_amount,
                    ]
                );
                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To Delete Pharmacy Bill.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyBillController', 'destroy', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function logStockMovement(string $action, $bill, array $product, float $beforeQty, float $deltaQty, float $afterQty): void
    {
        $medicineId = $product['productId'] ?? null;
        $medicine = $medicineId ? MedicineInventory::find($medicineId) : null;

        $billNumber = is_array($bill)
            ? ($bill['bill_no'] ?? 'N/A')
            : ($bill->bill_no ?? 'N/A');

        $medicineName = $medicine->medicine_name ?? ($product['name'] ?? 'Unknown medicine');
        $signedDelta = $deltaQty >= 0 ? '+' . $deltaQty : (string) $deltaQty;

        ActivityLogService::log(
            'Pharmacy Stock Movement',
            strtoupper($action),
            sprintf(
                'Bill: %s | Medicine: %s | Before: %.2f | Change: %s | After: %.2f',
                (string) $billNumber,
                (string) $medicineName,
                $beforeQty,
                $signedDelta,
                $afterQty
            ),
            [
                'bill_no' => (string) $billNumber,
                'medicine_name' => (string) $medicineName,
                'before' => $beforeQty,
                'change' => $deltaQty,
                'after' => $afterQty,
            ]
        );
    }

    public function changeStatus(Request $request, $id, $status)
    {
        DB::beginTransaction();
        try {
            $dataInfo = $this->pharmacyBillService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Pharmacy Bill ' . $status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pharmacybills', $message);
                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To " . $status . " Pharmacy Bill.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyBillController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function nextSequentialPharmacyNumber(string $field, string $prefix, string $datePattern, int $digits): string
    {
        $base = Carbon::now()->format($datePattern);
        $like = $prefix . $base . '%';

        $lastValue = ($field === 'pharmacy_no' ? PharmacyBill::query() : Billing::withTrashed())
            ->where($field, 'like', $like)
            ->lockForUpdate()
            ->orderBy($field, 'desc')
            ->value($field);

        $lastNumber = $lastValue ? (int) substr((string) $lastValue, -$digits) : 0;

        return $prefix . $base . str_pad((string) ($lastNumber + 1), $digits, '0', STR_PAD_LEFT);
    }

    private function generatePharmacyNumber($lastPharmacy = null)
    {
        $prefix = web_setting_prefix('pharmacy_bill_prefix', 'PHAB');
        return $this->nextSequentialPharmacyNumber('pharmacy_no', $prefix, 'Y', 4);
    }

    private function generateBillNumber($lastBilling = null)
    {
        $prefix = web_setting_prefix('pharmacy_bill_prefix', 'PHAB');
        return $this->nextSequentialPharmacyNumber('bill_number', $prefix, 'Ym', 4);
    }

    private function generateInvoiceNumber($lastBilling = null)
    {
        $prefix = web_setting_prefix('pharmacy_bill_prefix', 'PHAB');
        return $this->nextSequentialPharmacyNumber('invoice_number', $prefix, 'Ym', 4);
    }

    private function generateCaseNumber($lastBilling = null)
    {
        return $this->nextSequentialPharmacyNumber('case_number', 'CASE', 'Ym', 4);
    }
}
