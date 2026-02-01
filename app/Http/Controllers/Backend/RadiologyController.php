<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\RadiologyRequest;
use App\Models\Radiology;
use App\Models\RadiologyTest;
use App\Models\Test;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\Expense;
use App\Services\AdminService;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;
use App\Services\RadiologyService;
use App\Services\PatientService;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class RadiologyController extends Controller
{
    use SystemTrait;

    protected $radiologyService, $patientService, $adminService, $billingService;

    public function __construct(
        RadiologyService $radiologyService,
        PatientService $patientService,
        AdminService $adminService,
        BillingService $billingService
    ) {
        $this->radiologyService = $radiologyService;
        $this->patientService = $patientService;
        $this->adminService = $adminService;
        $this->billingService = $billingService;

        $this->middleware('auth:admin');
        $this->middleware('permission:radiology-list');
        $this->middleware('permission:radiology-create', ['only' => ['create']]);
        $this->middleware('permission:radiology-edit', ['only' => ['edit']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/Radiology/Index',
            [
                'pageTitle' => fn() => 'Radiology List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->radiologyService->list();

        if (request()->filled('bill_no'))
            $query->where('bill_no', 'like', '%' . request()->bill_no . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;

            $customData->bill_no = $data->bill_no ?? '';
            $customData->case_id = $data->case_id ?? '';
            $customData->reporting_date = $data->created_at->format('Y-m-d') ?? '';
            $customData->patient_id = $data?->patient?->name ?? '';
            $customData->doctor_id = $data?->referralDoctor?->name ?? $data->doctor_name ?? '';
            $customData->amount = $data->total_amount ?? '';
            $customData->balance_amount = $data->net_amount - $data->payment_amount ?? '';
            $customData->paid_amount = $data->payment_amount ?? '';

            $billing = Billing::where('bill_number', $data->bill_no)->first();

            $user = auth('admin')->user();

            $customData->links = [];

            if ($user->can('radiology-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.radiology.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('radiology-invoice') && $billing) {
                $customData->links[] = [
                    'linkClass' => 'bg-teal-500 text-white semi-bold',
                    'link' => route('backend.download.invoice', ['id' => $billing->id, 'module' => 'radiology']),
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
            ['fieldName' => 'bill_no', 'class' => 'text-center'],
            ['fieldName' => 'case_id', 'class' => 'text-center'],
            ['fieldName' => 'reporting_date', 'class' => 'text-center'],
            ['fieldName' => 'patient_id', 'class' => 'text-center'],
            ['fieldName' => 'doctor_id', 'class' => 'text-center'],
            ['fieldName' => 'amount', 'class' => 'text-center'],
            ['fieldName' => 'balance_amount', 'class' => 'text-center'],
            ['fieldName' => 'paid_amount', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Bill No',
            'Case Id',
            'Reporting Date',
            'Patient Name',
            'Reference Doctor',
            'Main Amount(TK)',
            'Payable Amount(TK)',
            'Paid Amount(TK)',
            'Action',
        ];
    }

    public function create()
    {
        $lastRadiology = Radiology::latest()->first();
        $lastRadiologyNo = $lastRadiology ? $lastRadiology->radiology_no : null;
        $lastBilling = $this->billingService->getLastBilling();
        $lastCase = $this->billingService->getLastCaseId();
        $lastBillNo = $lastBilling ? $lastBilling->bill_number : null;
        $lastCaseId = $lastCase ? $lastCase->case_number : null;

        // Get active radiology tests
        $radiologyTests = Test::where('category_type', 'Radiology')
            ->where('status', 'Active')
            ->select('id', 'test_name', 'test_short_name', 'report_days', 'tax', 'standard_charge', 'amount')
            ->orderBy('test_name')
            ->get();

        return Inertia::render(
            'Backend/Radiology/Form',
            [
                'pageTitle' => fn() => 'Radiology Create',
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
                'radiologyNo' => $lastRadiologyNo,
                'billNo' => $lastBillNo,
                'lastCaseId' => $lastCaseId,
                'radiologyTests' => $radiologyTests,
            ]
        );
    }

    public function store(RadiologyRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            // Generate unique numbers
            $lastRadiology = Radiology::latest()->first();
            $radiologyNo = $this->generateRadiologyNumber($lastRadiology);

            $lastBilling = $this->billingService->getLastBilling();
            $billNumber = $this->generateBillNumber($lastBilling);

            $caseNumber = $this->generateCaseNumber($lastBilling);

            // Create radiology record
            $radiologyData = [
                'case_id' => $caseNumber,
                'bill_no' => $billNumber,
                'radiology_no' => $radiologyNo,
                'patient_id' => $data['patient_id'],
                'referral_doctor_id' => $data['referral_doctor_id'] ?? null,
                'doctor_name' => $data['doctor_name'] ?? null,
                'note' => $data['note'] ?? null,
                'test_details' => json_encode($data['tests'] ?? []),
                'total_amount' => $data['total_amount'],
                'tax_amount' => $data['tax_amount'],
                'discount_amount' => $data['discount_amount'],
                'discount_percentage' => $data['discount_percentage'],
                'net_amount' => $data['net_amount'],
                'payment_mode' => $data['payment_mode'],
                'payment_amount' => $data['payment_amount'],
                'status' => 'Active',
                'created_by' => auth('admin')->user()->id
            ];

            $radiology = Radiology::create($radiologyData);

            if ($data['referral_doctor_id']) {
                $this->updateOrCreateExpenseRecord($radiology, $data);
            }

            if ($radiology) {
                // Create radiology tests
                if (isset($data['tests']) && !empty($data['tests'])) {
                    $this->createRadiologyTests($radiology, $data['tests']);
                }

                // Create billing record
                $billing = $this->createBillingRecord($radiology, $data, $billNumber, $caseNumber);

                // Create bill items
                if (isset($data['tests']) && !empty($data['tests'])) {
                    $this->createBillItems($billing, $data['tests']);
                }

                $message = 'Radiology created successfully';
                $this->storeAdminWorkLog($radiology->id, 'radiologies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message)
                    ->with('billId', $billing->id);
            } else {
                DB::rollBack();
                $message = "Failed to create Radiology.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'RadiologyController', 'store', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $radiology = Radiology::with(['radiologyTests', 'patient', 'referralDoctor'])->find($id);

        if (!$radiology) {
            return redirect()->route('backend.radiology.index')
                ->with('errorMessage', 'Radiology record not found.');
        }

        $radiologyTests = Test::where('category_type', 'Radiology')
            ->where('status', 'Active')
            ->select('id', 'test_name', 'test_short_name', 'report_days', 'tax', 'standard_charge', 'amount')
            ->orderBy('test_name')
            ->get();

        $tests = [];
        if ($radiology->test_details) {
            $testDetails = json_decode($radiology->test_details, true);

            $tests = collect($testDetails)->map(function ($test) {
                return [
                    'id' => $test['id'] ?? null,
                    'testId' => $test['test_id'] ?? $test['testId'] ?? '',
                    'test_name' => $test['test_name'] ?? $test['testName'] ?? '',
                    'reportDays' => $test['report_days'] ?? $test['reportDays'] ?? '',
                    'reportDate' => $test['report_date'] ?? $test['reportDate'] ?? '',
                    'tax' => $test['tax'] ?? $test['tax_percentage'] ?? 0,
                    'amount' => $test['amount'] ?? $test['net_amount'] ?? 0,
                ];
            })->filter()->values()->toArray();
        }

        $radiology->tests = $tests;

        return Inertia::render(
            'Backend/Radiology/Form',
            [
                'pageTitle' => 'Radiology Edit',
                'radiology' => $radiology,
                'id' => $id,
                'patients' => $this->patientService->activeList(),
                'doctors' => $this->adminService->activeDoctors(),
                'radiologyTests' => $radiologyTests, // Add this for the test dropdown
            ]
        );
    }

    public function update(RadiologyRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $radiology = Radiology::find($id);

            if (!$radiology) {
                DB::rollBack();
                return redirect()->back()->with('errorMessage', 'Radiology record not found.');
            }

            // Update radiology record
            $updateData = [
                'patient_id' => $data['patient_id'],
                'referral_doctor_id' => $data['referral_doctor_id'] ?? null,
                'doctor_name' => $data['doctor_name'] ?? null,
                'note' => $data['note'] ?? null,
                'test_details' => json_encode($data['tests'] ?? []),
                'total_amount' => $data['total_amount'],
                'tax_amount' => $data['tax_amount'],
                'discount_amount' => $data['discount_amount'],
                'discount_percentage' => $data['discount_percentage'],
                'net_amount' => $data['net_amount'],
                'payment_mode' => $data['payment_mode'],
                'payment_amount' => $data['payment_amount'],
                'updated_by' => auth('admin')->user()->id
            ];

            $dataInfo = $radiology->update($updateData);

            if ($data['referral_doctor_id']) {
                $this->updateOrCreateExpenseRecord($radiology, $data);
            }

            // Update radiology tests
            if (isset($data['tests']) && !empty($data['tests'])) {
                // Delete existing tests
                $billing = RadiologyTest::where('radiology_id', $radiology->id)->delete();
                // Create new tests
                $this->createRadiologyTests($radiology, $data['tests']);
            }

            // Update billing record
            $this->updateBillingRecord($radiology, $data);

            $message = 'Radiology updated successfully';
            $this->storeAdminWorkLog($radiology->id, 'radiologies', $message);

            DB::commit();

            $billing = Billing::where('bill_number', $radiology->bill_no)->first();
            return redirect()
                ->back()
                ->with('successMessage', $message)
                ->with('billId', $billing->id);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'RadiologyController', 'update', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $radiology = Radiology::findOrFail($id);

            if ($radiology->delete()) {
                // Delete related radiology tests
                RadiologyTest::where('radiology_id', $radiology->id)->delete();

                if ($radiology->bill_no) {
                    // Delete related billing records
                    Billing::where('bill_number', $radiology->bill_no)->delete();

                    // Delete related bill items
                    BillItem::whereHas('billing', function ($q) use ($radiology) {
                        $q->where('bill_number', $radiology->bill_no);
                    })->delete();
                }

                $message = 'Radiology deleted successfully';
                $this->storeAdminWorkLog($id, 'radiologies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To Delete Radiology.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'RadiologyController', 'destroy', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function createRadiologyTests($radiology, $tests)
    {
        foreach ($tests as $test) {
            RadiologyTest::create([
                'radiology_id' => $radiology->id,
                'test_id' => $test['testId'],
                'report_days' => $test['reportDays'] ?? 0,
                'report_date' => $test['reportDate'] ?? null,
                'tax_percentage' => $test['tax'] ?? 0,
                'amount' => $test['amount'],
                'status' => 'Active'
            ]);
        }
    }

    private function createBillingRecord($radiology, $data, $billNumber, $caseNumber)
    {
        $patient = $radiology->patient;

        // Calculate amounts
        $subtotal = floatval($data['total_amount'] ?? 0);
        $discount = floatval($data['discount_amount'] ?? 0);
        $netAmount = floatval($data['net_amount'] ?? 0);
        $paidAmount = floatval($data['payment_amount'] ?? 0);
        $dueAmount = max($netAmount - $paidAmount, 0);

        // Determine payment status
        if ($paidAmount <= 0) {
            $paymentStatus = 'Pending';
        } elseif ($paidAmount >= $netAmount) {
            $paymentStatus = 'Paid';
        } else {
            $paymentStatus = 'Partial';
        }

        return Billing::create([
            'invoice_number' => $this->generateInvoiceNumber(),
            'bill_number' => $billNumber,
            'case_number' => $caseNumber,
            'patient_id' => $radiology->patient_id,
            'patient_mobile' => $patient->mobile ?? '',
            'gender' => $patient->gender ?? 'Male',
            'doctor_id' => $radiology->referral_doctor_id ?? null,
            'doctor_name' => $radiology->doctor_name ?? '',
            'referrer_id' => null,
            'card_type' => $data['payment_mode'] ?? 'Cash',
            'pay_mode' => $data['payment_mode'] ?? 'Cash',
            'card_number' => null,
            'total' => $subtotal,
            'discount' => $discount,
            'discount_type' => 'flat',
            'payable_amount' => $netAmount,
            'paid_amt' => $paidAmount,
            'change_amt' => max($paidAmount - $netAmount, 0),
            'receiving_amt' => $paidAmount,
            'due_amount' => $dueAmount,
            'delivery_date' => null,
            'remarks' => $data['note'] ?? '',
            'commission_total' => 0,
            'physyst_amt' => 0,
            'commission_slider' => 0,
            'created_by' => auth('admin')->user()->id,
            'payment_status' => $paymentStatus,
            'status' => 'Active'
        ]);
    }

    private function updateBillingRecord($radiology, $data)
    {
        $billing = Billing::where('bill_number', $radiology->bill_no)->first();

        if ($billing) {
            $patient = $radiology->patient;

            // Calculate amounts
            $subtotal = floatval($data['total_amount'] ?? $billing->total);
            $discount = floatval($data['discount_amount'] ?? $billing->discount);
            $netAmount = floatval($data['net_amount'] ?? $billing->payable_amount);
            $paidAmount = floatval($data['payment_amount'] ?? $billing->paid_amt);
            $dueAmount = max($netAmount - $paidAmount, 0);

            // Determine payment status
            if ($paidAmount <= 0) {
                $paymentStatus = 'Pending';
            } elseif ($paidAmount >= $netAmount) {
                $paymentStatus = 'Paid';
            } else {
                $paymentStatus = 'Partial';
            }

            $billing->update([
                'patient_id' => $data['patient_id'] ?? $billing->patient_id,
                'patient_mobile' => $patient->mobile ?? $billing->patient_mobile,
                'gender' => $patient->gender ?? $billing->gender,
                'doctor_id' => $data['referral_doctor_id'] ?? $billing->doctor_id,
                'doctor_name' => $data['doctor_name'] ?? $billing->doctor_name,
                'card_type' => $data['payment_mode'] ?? $billing->card_type,
                'pay_mode' => $data['payment_mode'] ?? $billing->pay_mode,
                'total' => $subtotal,
                'discount' => $discount,
                'payable_amount' => $netAmount,
                'paid_amt' => $paidAmount,
                'change_amt' => max($paidAmount - $netAmount, 0),
                'receiving_amt' => $paidAmount,
                'due_amount' => $dueAmount,
                'remarks' => $data['note'] ?? $billing->remarks,
                'payment_status' => $paymentStatus,
                'updated_by' => auth('admin')->user()->id
            ]);

            // Update bill items if tests exist
            if (isset($data['tests']) && !empty($data['tests'])) {
                BillItem::where('billing_id', $billing->id)->delete();
                $this->createBillItems($billing, $data['tests']);
            }
        }
    }

    private function updateOrCreateExpenseRecord($radiology, $data)
    {
        $existingExpense = Expense::where('bill_number', $radiology->bill_number)->first();

        $headerName = 'Radiology';

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
            'bill_number' => $radiology->bill_number,
            'case_id' => $radiology->case_number,
            'name' => auth('admin')->user()->name ?? '',
            'description' => 'Commission expense for ' . $headerName . ' services',
            'amount' => $data['physyst_amt'] ?? 0,
            'date' => now(),
            'status' => 'Active'
        ];

        if ($existingExpense) {
            $expenseData['updated_by'] = auth('admin')->user()->id;
            $existingExpense->update($expenseData);
        } else {
            $expenseData['created_by'] = auth('admin')->user()->id;
            Expense::create($expenseData);
        }
    }

    private function createBillItems($billing, $tests)
    {
        foreach ($tests as $test) {
            $testInfo = Test::find($test['testId']);

            if ($testInfo) {
                BillItem::create([
                    'billing_id' => $billing->id,
                    'item_id' => $test['testId'],
                    'item_name' => $testInfo->test_name,
                    'category' => 'Radiology',
                    'unit_price' => floatval($test['amount']),
                    'quantity' => 1,
                    'total_amount' => floatval($test['amount']),
                    'discount' => 0,
                    'rugound' => 0,
                    'net_amount' => floatval($test['amount']),
                    'status' => 'Active'
                ]);
            }
        }
    }

    private function generateInvoiceNumber()
    {
        $year = date('Y');
        $lastInvoice = Billing::where('invoice_number', 'like', "INV-{$year}-%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = intval(substr($lastInvoice->invoice_number, -6));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return "INV-{$year}-" . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    private function generateRadiologyNumber($lastRadiology = null)
    {
        $prefix = 'RAD';
        $year = date('Y');

        if ($lastRadiology && $lastRadiology->radiology_no) {
            $lastNumber = (int) substr($lastRadiology->radiology_no, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $year . $newNumber;
    }

    private function generateBillNumber($lastBilling = null)
    {
        $prefix = 'BILL';
        $year = date('Ym');

        if ($lastBilling && $lastBilling->bill_number) {
            $lastNumber = (int) substr($lastBilling->bill_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $year . $newNumber;
    }

    private function generateCaseNumber($lastBilling = null)
    {
        $prefix = 'CASE';
        $year = date('Ym');

        if ($lastBilling && $lastBilling->case_number) {
            $lastNumber = (int) substr($lastBilling->case_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $year . $newNumber;
    }
}
