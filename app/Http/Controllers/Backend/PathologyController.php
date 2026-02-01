<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PathologyRequest;
use App\Models\Pathology;
use App\Models\Referral;
use App\Models\ReferralPerson;
use App\Models\Test;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\Expense;
use App\Services\AdminService;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;
use App\Services\PathologyService;
use App\Services\PatientService;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class PathologyController extends Controller
{
    use SystemTrait;

    protected $pathologyService, $patientService, $adminService, $billingService;

    public function __construct(PathologyService $pathologyService, PatientService $patientService, AdminService $adminService, BillingService $billingService)
    {
        $this->pathologyService = $pathologyService;
        $this->patientService = $patientService;
        $this->adminService = $adminService;
        $this->billingService = $billingService;

        $this->middleware('auth:admin');
        $this->middleware('permission:pathology-list'); 
        $this->middleware('permission:pathology-create', ['only' => ['create']]);
        $this->middleware('permission:pathology-edit', ['only' => ['edit']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/Pathology/Index',
            [
                'pageTitle' => fn() => 'Pathology List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->pathologyService->list();

        if (request()->filled('bill_no'))
            $query->where('bill_no', 'like', '%' . request()->bill_no . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;

            $customData->bill_no = $data->bill_no ?? '';
            $customData->case_id = $data->case_id ?? '';
            $customData->reporting_date = $data->date ?? '';
            $customData->patient_id = $data?->patient?->name ?? '';
            $customData->doctor_id = $data?->doctor?->name ?? '';
            $customData->amount = $data->subtotal ?? '';
            $customData->balance_amount = $data->net_amount - $data->payment_amount ?? '';
            $customData->paid_amount = $data->payment_amount ?? '';

            $billing = Billing::where('bill_number', $data->bill_no)->first();

            $user = auth('admin')->user();

            $customData->links = [];

            if ($user->can('pathology-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.pathology.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('pathology-invoice') && $billing) {
                $customData->links[] = [
                    'linkClass' => 'bg-teal-500 text-white semi-bold',
                    'link' => route('backend.download.invoice', ['id' => $billing->id, 'module' => 'pathology']),
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
        $lastPathology = Pathology::latest()->first();
        $lastPathologyNo = $lastPathology ? $lastPathology->pathology_no : null;
        $lastBilling = $this->billingService->getLastBilling();
        $lastCase = $this->billingService->getLastCaseId();
        $lastBillNo = $lastBilling ? $lastBilling->bill_number : null;
        $lastCaseId = $lastCase ? $lastCase->case_number : null;

        // Get active pathology tests
        $pathologyTests = Test::where('category_type', 'Pathology')
            ->where('status', 'Active')
            ->select('id', 'test_name', 'test_short_name', 'report_days', 'tax', 'standard_charge', 'amount')
            ->orderBy('test_name')
            ->get();

        $pathoRefferer = ReferralPerson::where('status', 'Active')->get();

        return Inertia::render(
            'Backend/Pathology/Form',
            [
                'pageTitle' => fn() => 'Pathology Create',
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
                'pathologyNo' => $lastPathologyNo,
                'billNo' => $lastBillNo,
                'lastCaseId' => $lastCaseId,
                'pathologyTests' => $pathologyTests,
                'raferrers' => fn() => $pathoRefferer
            ]
        );
    }

    public function store(PathologyRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            $lastPathology = Pathology::latest()->first();
            $data['pathology_no'] = $this->generatePathologyNumber($lastPathology);

            $lastBilling = $this->billingService->getLastBilling();
            $billNumber = $this->generateBillNumber($lastBilling);
            $data['bill_no'] = $billNumber;

            if (!isset($data['net_amount'])) {
                $data['net_amount'] = $data['subtotal'] - ($data['discount_amount'] ?? 0);
            }

            $data['payment_amount'] = $data['payment_amount'] ?? 0;

            if (isset($data['tests'])) {
                $data['tests'] = json_encode($data['tests']);
            }

            $data['created_by'] = auth('admin')->user()->id;

            $pathologyInfo = $this->pathologyService->create($data);

            if ($data['payee_id']) {
                $this->updateOrCreateExpenseRecord($pathologyInfo, $data);
            }

            if ($pathologyInfo) {

                $billing = $this->createBillingRecord($pathologyInfo, $data, $billNumber);

                if (isset($data['tests']) && !empty($data['tests'])) {
                    $this->createBillItems($billing, json_decode($data['tests'], true));
                }

                // Store referral information if payee is provided
                if (!empty($data['payee_id']) && !empty($data['commission_amount'])) {
                    $this->createReferralRecord($billing, $data);
                }

                $message = 'Pathology created successfully';
                $this->storeAdminWorkLog($pathologyInfo->id, 'pathologies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message)
                    ->with('billId', $billing->id);
            } else {
                DB::rollBack();
                $message = "Failed To create Pathology.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyController', 'store', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function updateOrCreateExpenseRecord($pathologyInfo, $data)
    {
        $existingExpense = Expense::where('bill_number', $pathologyInfo->bill_number)->first();

        $headerName = 'Pathology';

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
            'bill_number' => $pathologyInfo->bill_number,
            'case_id' => $pathologyInfo->case_number,
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

    public function edit($id)
    {
        $pathology = $this->pathologyService->find($id);

        $pathologyTests = Test::where('category_type', 'Pathology')
            ->where('status', 'Active')
            ->select('id', 'test_name', 'test_short_name', 'report_days', 'tax', 'standard_charge', 'amount')
            ->orderBy('test_name')
            ->get();

        // Initialize empty tests array
        $tests = [];

        if ($pathology && $pathology->tests) {
            $testDetails = json_decode($pathology->tests, true);

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

        $pathology->tests = $tests;

        $lastPathology = Pathology::latest()->first();
        $lastPathologyNo = $lastPathology ? $lastPathology->pathology_no : null;
        $lastBilling = $this->billingService->getLastBilling();
        $lastCase = $this->billingService->getLastCaseId();
        $lastBillNo = $lastBilling ? $lastBilling->bill_number : null;
        $lastCaseId = $lastCase ? $lastCase->case_number : null;

        return Inertia::render(
            'Backend/Pathology/Form',
            [
                'pageTitle' => 'Pathology Edit',
                'pathology' => $pathology,
                'id' => $id,
                'patients' => $this->patientService->activeList(),
                'doctors' => $this->adminService->activeDoctors(),
                'pathologyTests' => $pathologyTests,
                'pathologyNo' => $lastPathologyNo,
                'billNo' => $lastBillNo,
                'lastCaseId' => $lastCaseId,
            ]
        );
    }

    public function update(PathologyRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $pathology = $this->pathologyService->find($id);

            if (isset($data['tests'])) {
                $data['tests'] = json_encode($data['tests']);
            }

            $data['updated_by'] = auth('admin')->user()->id;

            $pathologyInfo = $this->pathologyService->update($data, $id);

            if ($data['payee_id']) {
                $this->updateOrCreateExpenseRecord($pathologyInfo, $data);
            }

            if ($pathologyInfo) {

                $this->updateBillingRecord($pathology, $data);

                // Update referral information
                if (!empty($data['payee_id']) && !empty($data['commission_amount'])) {
                    $this->updateReferralRecord($pathologyInfo, $data);
                }

                $message = 'Pathology updated successfully';
                $this->storeAdminWorkLog($pathologyInfo->id, 'pathologies', $message);

                DB::commit();

                $billing = Billing::where('bill_number', $pathology->bill_no)->first();
                return redirect()
                    ->back()
                    ->with('successMessage', $message)
                    ->with('billId', $billing->id);
            } else {
                DB::rollBack();
                $message = "Failed To update pathology.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyController', 'update', substr($err->getMessage(), 0, 1000));
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
            $pathology = Pathology::findOrFail($id);

            if ($pathology->delete()) {
                if ($pathology->bill_no) {
                    // Delete related billing records
                    Billing::where('bill_number', $pathology->bill_no)->delete();

                    // Delete related bill items
                    BillItem::whereHas('billing', function ($q) use ($pathology) {
                        $q->where('bill_number', $pathology->bill_no);
                    })->delete();

                    // Delete related referral records
                    Referral::where('bill_no', $pathology->bill_no)->delete();
                }

                $message = 'Pathology deleted successfully';
                $this->storeAdminWorkLog($id, 'pathologies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To Delete Pathology.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyController', 'destroy', substr($err->getMessage(), 0, 1000));
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function createReferralRecord($billing, $data)
    {

        return Referral::create([
            'billing_id' => $billing->id,
            'payee_id' => $data['payee_id'],
            'commission_amount' => floatval($data['commission_amount'] ?? 0),
            'commission_percentage' => floatval($data['commission_percentage'] ?? 0),
            'category' => 'Pathology',
            'date' => $data['date'] ?? now()->format('Y-m-d'),
            'net_amount' => floatval($data['net_amount'] ?? 0),
            'status' => 'Active'
        ]);
    }

    private function updateReferralRecord($pathology, $data)
    {
        $referral = Referral::where('bill_no', $pathology->bill_no)->first();

        if ($referral) {
            // Update existing referral
            $referral->update([
                'payee_id' => $data['payee_id'],
                'commission_amount' => floatval($data['commission_amount'] ?? 0),
                'commission_percentage' => floatval($data['commission_percentage'] ?? 0),
                'date' => $data['date'] ?? $referral->date,
                'net_amount' => floatval($data['net_amount'] ?? 0),
            ]);
        } else {
            // Create new referral if it doesn't exist
            $this->createReferralRecord($pathology, $data);
        }

        return $referral;
    }

    private function createBillingRecord($pathology, $data, $billNumber)
    {
        $caseNumber = $this->generateCaseNumber(Billing::latest()->first());
        $patient = $pathology->patient;

        // Calculate amounts
        $subtotal = floatval($data['subtotal'] ?? 0);
        $discount = floatval($data['discount_amount'] ?? 0);
        $netAmount = floatval($data['net_amount'] ?? ($subtotal - $discount));
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
            'patient_id' => $pathology['patient_id'],
            'patient_mobile' => $patient->mobile ?? '',
            'gender' => $patient->gender ?? 'Male',
            'doctor_id' => $pathology['doctor_id'] ?? null,
            'doctor_name' => $pathology['doctor_name'] ?? '',
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
            'commission_total' => $data['commission_amount'] ?? 0,
            'physyst_amt' => 0,
            'commission_slider' => $data['commission_percentage'] ?? 0,
            'created_by' => auth('admin')->user()->id,
            'payment_status' => $paymentStatus,
            'status' => 'Active'
        ]);
    }

    private function updateBillingRecord($pathology, $data)
    {
        $billing = Billing::where('bill_number', $pathology->bill_no)->first();

        if ($billing) {
            $patient = $pathology->patient;

            // Calculate amounts
            $subtotal = floatval($data['subtotal'] ?? $billing->total);
            $discount = floatval($data['discount_amount'] ?? $billing->discount);
            $netAmount = floatval($data['net_amount'] ?? ($subtotal - $discount));
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
                'doctor_id' => $data['doctor_id'] ?? $billing->doctor_id,
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
                'commission_total' => $data['commission_amount'] ?? $billing->commission_total,
                'commission_slider' => $data['commission_percentage'] ?? $billing->commission_slider,
                'payment_status' => $paymentStatus,
                'updated_by' => auth('admin')->user()->id
            ]);

            // Update bill items if tests exist
            if (isset($data['tests']) && !empty($data['tests'])) {
                BillItem::where('billing_id', $billing->id)->delete();
                $this->createBillItems($billing, json_decode($data['tests'], true));
            }
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
                    'category' => 'Pathology',
                    'unit_price' => floatval($test['amount'] ?? $testInfo->amount ?? $testInfo->standard_charge),
                    'quantity' => 1,
                    'total_amount' => floatval($test['amount'] ?? $testInfo->amount ?? $testInfo->standard_charge),
                    'discount' => 0,
                    'rugound' => 0,
                    'net_amount' => floatval($test['amount'] ?? $testInfo->amount ?? $testInfo->standard_charge),
                    'status' => 'Active',
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

    private function generatePathologyNumber($lastPathology = null)
    {
        $prefix = 'PATB';

        if ($lastPathology && $lastPathology->pathology_no) {
            $lastNumber = (int) substr($lastPathology->pathology_no, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $newNumber;
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
