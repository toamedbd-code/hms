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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

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
            ]
        );
    }

    private function getDatas()
    {
        $query = PharmacyBill::query();

        if (request()->filled('pharmacy_no')) {
            $query->where('pharmacy_no', 'like', '%' . request()->pharmacy_no . '%');
        }

        if (request()->filled('patient_name')) {
            $query->whereHas('patient', function ($q) {
                $q->where('first_name', 'like', '%' . request()->patient_name . '%')
                    ->orWhere('last_name', 'like', '%' . request()->patient_name . '%');
            });
        }

        $datas = $query->with('patient')->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->pharmacy_no = $data->pharmacy_no;
            $customData->bill_no = $data->bill_no;
            $customData->patient_name = $data->patient ? $data->patient->first_name . ' ' . $data->patient->last_name : 'N/A';
            $customData->net_amount = number_format($data->net_amount, 2);
            $customData->date = $data->date;
            $customData->status = getStatusText($data->status);

            $billing = Billing::where('bill_number', $data->bill_no)->first();

            $customData->hasLink = true;
            $user = auth('admin')->user();

            $customData->links = [];

            if ($user->can('pharmacy-bill-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.pharmacybill.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('pharmacy-bill-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.pharmacybill.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('pharmacy-bill-list-invoice') && $billing) {
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

            $pharmacyBillData = [
                'pharmacy_no' => $data['pharmacy_no'],
                'bill_no' => $data['bill_no'],
                'case_id' => $data['case_id'],
                'date' => $data['date'],
                'patient_id' => $data['patient_id'],
                'doctor_id' => $data['doctor_id'] ?? null,
                'doctor_name' => $data['doctor_id'] ? $this->adminService->find($data['doctor_id'])->name : null,
                'products' => json_encode($data['products']),
                'subtotal' => $data['subtotal'],
                'discount_percentage' => $data['discount_percentage'],
                'discount_amount' => $data['discount_amount'],
                'vat_percentage' => $data['vat_percentage'],
                'vat_amount' => $data['vat_amount'],
                'extra_discount' => $data['extra_discount'],
                'net_amount' => $data['net_amount'],
                'payment_mode' => $data['payment_mode'],
                'payment_amount' => $data['payment_amount'],
                'note' => $data['note'],
                'created_by' => auth('admin')->user()->id,
            ];

            $pharmacyBill = $this->pharmacyBillService->create($pharmacyBillData);

            if ($pharmacyBill) {

                foreach ($data['products'] as $product) {
                    $medicine = MedicineInventory::find($product['productId']);
                    if ($medicine && $medicine->medicine_quantity >= $product['quantity']) {
                        $medicine->decrement('medicine_quantity', $product['quantity']);
                    }
                }

                $billing = $this->createBillingRecord($pharmacyBill, $data);

                if ($data['payment_amount'] > 0) {
                    Payment::create([
                        'billing_id' => $billing->id,
                        'pharmacy_bill_id' => $pharmacyBill->id,
                        'amount' => $data['payment_amount'],
                        'payment_method' => $data['payment_mode'],
                        'received_by' => auth('admin')->user()->id,
                        'payment_status' => $this->determinePaymentStatus($data['payment_amount'], $data['net_amount'])
                    ]);
                }

                $message = 'Pharmacy Bill created successfully';
                $this->storeAdminWorkLog($pharmacyBill->id, 'pharmacybills', $message);
                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
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
            DB::commit();
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

        $billingData = [
            'invoice_number' => $invoiceNumber,
            'bill_number' => $billNumber,
            'case_number' => $caseNumber,
            'patient_id' => $data['patient_id'],
            'patient_mobile' => $patient->phone ?? '',
            'gender' => $patient->gender ?? 'Male',
            'doctor_id' => $data['doctor_id'] ?? null,
            'doctor_name' => $pharmacyBill->doctor_name,
            'card_type' => $data['payment_mode'],
            'pay_mode' => $data['payment_mode'],
            'total' => $data['net_amount'],
            'discount' => $data['discount_amount'],
            'discount_type' => 'flat',
            'payable_amount' => $data['net_amount'],
            'paid_amt' => $data['payment_amount'],
            'receiving_amt' => $data['payment_amount'],
            'due_amount' => $data['net_amount'] - $data['payment_amount'],
            'remarks' => $data['note'],
            'payment_status' => $this->determinePaymentStatus($data['payment_amount'], $data['net_amount']),
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

            if ($oldPharmacyBill && $oldPharmacyBill->products) {
                $oldProducts = is_string($oldPharmacyBill->products)
                    ? json_decode($oldPharmacyBill->products, true)
                    : $oldPharmacyBill->products;

                if (is_array($oldProducts)) {
                    foreach ($oldProducts as $oldProduct) {
                        $medicine = MedicineInventory::find($oldProduct['productId']);
                        if ($medicine) {
                            $medicine->increment('medicine_quantity', $oldProduct['quantity']);
                        }
                    }
                }
            }

            $data['updated_by'] = auth('admin')->user()->id;
            $dataInfo = $this->pharmacyBillService->update($data, $id);

            if ($dataInfo->save()) {
                foreach ($data['products'] as $product) {
                    $medicine = MedicineInventory::find($product['productId']);
                    if ($medicine && $medicine->medicine_quantity >= $product['quantity']) {
                        $medicine->decrement('medicine_quantity', $product['quantity']);
                    }
                }

                $this->updateBillingRecord($dataInfo, $data);

                $message = 'Pharmacy Bill updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pharmacybills', $message);
                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
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
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
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

            $billing->update([
                'patient_id' => $data['patient_id'],
                'patient_mobile' => $patient->phone ?? '',
                'gender' => $patient->gender ?? 'Male',
                'doctor_id' => $data['doctor_id'] ?? null,
                'doctor_name' => $pharmacyBill->doctor_name,
                'total' => $data['net_amount'],
                'discount' => $data['discount_amount'],
                'payable_amount' => $data['net_amount'],
                'paid_amt' => $data['payment_amount'],
                'receiving_amt' => $data['payment_amount'],
                'due_amount' => $data['net_amount'] - $data['payment_amount'],
                'remarks' => $data['note'],
                'payment_status' => $this->determinePaymentStatus($data['payment_amount'], $data['net_amount']),
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
        }
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
                            $medicine->increment('medicine_quantity', $product['quantity']);
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
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function generatePharmacyNumber($lastPharmacy = null)
    {
        $prefix = 'PHARM';
        $year = date('Y');

        if ($lastPharmacy && $lastPharmacy->pharmacy_no) {
            $lastNumber = (int) substr($lastPharmacy->pharmacy_no, -4);
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

    private function generateInvoiceNumber($lastBilling = null)
    {
        $prefix = 'INV';
        $year = date('Ym');

        if ($lastBilling && $lastBilling->invoice_number) {
            $lastNumber = (int) substr($lastBilling->invoice_number, -4);
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
