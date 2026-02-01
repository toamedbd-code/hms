<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpdPatientRequest;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use App\Services\OpdPatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;
use App\Services\AdminService;
use App\Services\ChargeService;
use App\Services\PatientService;

class OpdPatientController extends Controller
{
    use SystemTrait;

    protected $opdpatientService, $patientService, $adminService, $chargeService;

    public function __construct(OpdPatientService $opdpatientService, PatientService $patientService, AdminService $adminService, ChargeService $chargeService)
    {
        $this->opdpatientService = $opdpatientService;
        $this->patientService = $patientService;
        $this->adminService = $adminService;
        $this->chargeService = $chargeService;

        $this->middleware('auth:admin');
        $this->middleware('permission:opd-patient-list');
        $this->middleware('permission:opd-patient-status', ['only' => ['changeStatus']]);
        $this->middleware('permission:opd-patient-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:opd-patient-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:opd-patient-invoice');
    }

    public function index()
    {
        return Inertia::render(
            'Backend/OpdPatient/Index',
            [
                'pageTitle' => fn() => 'Opd Patient List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->opdpatientService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();

            $customData->index = $index + 1;
            $customData->patient_id = $data?->patient?->name ?? '';
            $customData->consultant_doctor_id = $data?->doctor?->name ?? '';
            $customData->symptom_type = $data->symptom_type;
            $customData->symptom_title = $data->symptom_title;
            $customData->allergies = $data->allergies;
            $customData->appointment_date = $data->appointment_date;
            $customData->casualty = $data->casualty ?? $data->casualty == 'yes' ? 'Yes' : 'No';
            $customData->apply_tpa = $data->apply_tpa ?? $data->apply_tpa == 1 ? 'Yes' : 'No';
            $customData->amount = number_format($data->amount, 2);
            $customData->paid_amount = number_format($data->paid_amount, 2);
            $customData->balance_amount = number_format($data->balance_amount, 2);
            $customData->payment_status = $data->payment_status;

            $customData->hasLink = true;
            $user = auth('admin')->user();

            $customData->links = [];

            if ($user && $user->can('opd-patient-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.opdpatient.status.change', [
                        'id' => $data->id,
                        'status' => $data->status == 'Active' ? 'Inactive' : 'Active'
                    ]),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user && $user->can('opd-patient-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.opdpatient.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user && $user->can('opd-patient-invoice')) {
                $customData->links[] = [
                    'linkClass' => 'bg-teal-500 text-white semi-bold',
                    'link' => route('backend.download.opd.bill', [
                        'id' => $data->id,
                        'module' => 'opd'
                    ]),
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
            ['fieldName' => 'patient_id', 'class' => 'text-center'],
            ['fieldName' => 'consultant_doctor_id', 'class' => 'text-center'],
            ['fieldName' => 'symptom_type', 'class' => 'text-center'],
            ['fieldName' => 'symptom_title', 'class' => 'text-center'],
            ['fieldName' => 'allergies', 'class' => 'text-center'],
            ['fieldName' => 'appointment_date', 'class' => 'text-center'],
            ['fieldName' => 'casualty', 'class' => 'text-center'],
            ['fieldName' => 'apply_tpa', 'class' => 'text-center'],
            ['fieldName' => 'amount', 'class' => 'text-center'],
            ['fieldName' => 'paid_amount', 'class' => 'text-center'],
            ['fieldName' => 'balance_amount', 'class' => 'text-center'],
            ['fieldName' => 'payment_status', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Patient name',
            'Consultant Doctor',
            'Symptom Type',
            'Symptom Title',
            'Allergies',
            'Appointment Date',
            'Casualty',
            'Apply TPA',
            'Amount',
            'Paid Amount',
            'Balance Amount',
            'Payment Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/OpdPatient/Form',
            [
                'pageTitle' => fn() => 'Opd Patient Create',
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
                'chargeTypes' => fn() => $this->opdpatientService->chargeTypes(),
                'charges' => fn() => $this->chargeService->activeList(),
            ]
        );
    }

    public function store(OpdPatientRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            // Calculate balance amount and determine payment status
            $amount = floatval($data['amount']);
            $paidAmount = floatval($data['paid_amount']);
            $balanceAmount = $amount - $paidAmount;

            // Determine payment status
            $paymentStatus = 'Pending';
            if ($paidAmount <= 0) {
                $paymentStatus = 'Pending';
            } elseif ($paidAmount >= $amount) {
                $paymentStatus = 'Paid';
                $balanceAmount = 0; // Ensure no negative balance
            } else {
                $paymentStatus = 'Partial';
            }

            // Add calculated fields to data
            $data['balance_amount'] = $balanceAmount;
            $data['payment_status'] = $paymentStatus;

            // Ensure apply_tpa is properly handled
            $data['apply_tpa'] = isset($data['apply_tpa']) ? (bool) $data['apply_tpa'] : false;

            $dataInfo = $this->opdpatientService->create($data);

            if ($dataInfo) {
                $message = 'OPD Patient created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'opdpatients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message)
                    ->with('billId', $dataInfo->id);
            } else {
                DB::rollBack();

                $message = "Failed to create OPD Patient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'OpdPatientController', 'store', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $opdpatient = $this->opdpatientService->find($id);

        return Inertia::render(
            'Backend/OpdPatient/Form',
            [
                'pageTitle' => fn() => 'OPD Patient Edit',
                'opdpatient' => fn() => $opdpatient,
                'id' => fn() => $id,
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
                'chargeTypes' => fn() => $this->opdpatientService->chargeTypes(),
                'charges' => fn() => $this->chargeService->activeList(),
            ]
        );
    }

    public function update(OpdPatientRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $opdpatient = $this->opdpatientService->find($id);

            $amount = floatval($data['amount']);
            $paidAmount = floatval($data['paid_amount']);
            $balanceAmount = $amount - $paidAmount;

            $paymentStatus = 'Pending';
            if ($paidAmount <= 0) {
                $paymentStatus = 'Pending';
            } elseif ($paidAmount >= $amount) {
                $paymentStatus = 'Paid';
                $balanceAmount = 0;
            } else {
                $paymentStatus = 'Partial';
            }

            $data['balance_amount'] = $balanceAmount;
            $data['payment_status'] = $paymentStatus;

            $data['apply_tpa'] = isset($data['apply_tpa']) ? (bool) $data['apply_tpa'] : false;

            $dataInfo = $this->opdpatientService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'OPD Patient updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'opdpatients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message)
                    ->with('billId', $dataInfo->id);
            } else {
                DB::rollBack();

                $message = "Failed to update OPD Patient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'OpdPatientController', 'update', substr($err->getMessage(), 0, 1000));
            DB::commit();
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
            if ($this->opdpatientService->delete($id)) {
                $message = 'OPD Patient deleted successfully';
                $this->storeAdminWorkLog($id, 'opdpatients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed to Delete OPD Patient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'OpdPatientController', 'destroy', substr($err->getMessage(), 0, 1000));
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
            $dataInfo = $this->opdpatientService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'OPD Patient ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'opdpatients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed to " . request()->status . " OPD Patient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'OpdPatientController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
