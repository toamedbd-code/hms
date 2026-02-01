<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppoinmentRequest;
use App\Models\Admin;
use App\Services\AdminService;
use Illuminate\Support\Facades\DB;
use App\Services\AppoinmentService;
use App\Services\DepartmentService;
use App\Services\DesignationService;
use App\Services\PatientService;
use App\Services\SpecialistService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Carbon\Carbon;
use Exception;
use Spatie\Permission\Models\Role;

class AppoinmentController extends Controller
{
    use SystemTrait;

    protected $appoinmentService, $patientService, $adminService, $designationService, $departmentSerrvice, $specialistService;

    public function __construct(AppoinmentService $appoinmentService, PatientService $patientService, AdminService $adminService, DesignationService $designationService, DepartmentService $departmentSerrvice, SpecialistService $specialistService)
    {
        $this->appoinmentService = $appoinmentService;
        $this->patientService = $patientService;
        $this->adminService = $adminService;
        $this->designationService = $designationService;
        $this->departmentSerrvice = $departmentSerrvice;
        $this->specialistService = $specialistService;


        $this->middleware('auth:admin');
        $this->middleware('permission:appoinment-list');
        $this->middleware('permission:appoinment-status', ['only' => ['changeStatus']]);
        $this->middleware('permission:appoinment-edit', ['only' => ['edit', 'update']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/Appoinment/Index',
            [
                'pageTitle' => fn() => 'Appoinment List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->appoinmentService->list();

        if (request()->filled('name')) {
            $query->whereHas('patient', function ($q) {
                $q->where('name', 'like', '%' . request()->name . '%');
            });
        }


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->patient_id = $data?->patient?->name ?? 'N/A';
            $customData->doctor_id = $data?->admin?->name ?? 'N/A';
            $customData->doctor_fee = $data->doctor_fee;
            $customData->discount = $data->discount_percentage;
            $customData->priority = $data->appointment_priority;
            $customData->appoinment_date = $data->appoinment_date ? Carbon::parse($data->appoinment_date)->format('d M Y, h:i A') : 'N/A';
            $customData->payment = $data->payment_mode;
            $customData->appoinment_status = $data->appoinment_status;
            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;

            $customData->links = [];

            $user = auth()->guard('admin')->user();

            if ($user->can('appoinment-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.appoinment.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('appoinment-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.appoinment.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('appoinment-invoice')) {
                $customData->links[] = [
                    'linkClass' => 'bg-teal-500 text-white semi-bold',
                    'link' => route('backend.download.appointment.invoice', ['id' => $data->id, 'module' => 'appointment']),
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
            ['fieldName' => 'doctor_id', 'class' => 'text-center'],
            ['fieldName' => 'doctor_fee', 'class' => 'text-center'],
            ['fieldName' => 'discount', 'class' => 'text-center'],
            ['fieldName' => 'appoinment_date', 'class' => 'text-center'],
            ['fieldName' => 'priority', 'class' => 'text-center'],
            ['fieldName' => 'payment', 'class' => 'text-center'],
            ['fieldName' => 'appoinment_status', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Patient Name',
            'Doctor Name',
            'Doctor Fee',
            'Discount',
            'Appoinment Date',
            'Priority',
            'Payment',
            'Appoinment Status',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/Appoinment/Form',
            [
                'pageTitle' => fn() => 'Appoinment Create',
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
                'designations' => fn() => $this->designationService->activeList(),
                'departments' => fn() => $this->departmentSerrvice->activeList(),
                'specialists' => fn() => $this->specialistService->activeList(),
            ]
        );
    }


    public function store(AppoinmentRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $lastAppointmentId = DB::table('appoinments')->max('id') ?? 0;
            $nextId = $lastAppointmentId + 1;
            $paddedId = str_pad($nextId, 3, '0', STR_PAD_LEFT);

            $data['transaction_id'] = 'TRID' . $paddedId;

            $dataInfo = $this->appoinmentService->create($data);

            if ($dataInfo) {
                $message = 'Appoinment created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'appoinments', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Appoinment.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'AppoinmentController', 'store', substr($err->getMessage(), 0, 1000));
            //dd($err);
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            // dd($message);
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $appoinment = $this->appoinmentService->find($id);

        return Inertia::render(
            'Backend/Appoinment/Form',
            [
                'pageTitle' => fn() => 'Appoinment Edit',
                'appoinment' => fn() => $appoinment,
                'id' => fn() => $id,
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
            ]
        );
    }

    public function update(AppoinmentRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $appoinment = $this->appoinmentService->find($id);

            $dataInfo = $this->appoinmentService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Appoinment updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'appoinments', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update appoinments.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AppoinmentController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->appoinmentService->delete($id)) {
                $message = 'Appoinment deleted successfully';
                $this->storeAdminWorkLog($id, 'appoinments', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Appoinment.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AppoinmentController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->appoinmentService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Appoinment ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'appoinments', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Appoinment.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AppoinmentController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function doctorStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins',
            'phone' => 'nullable|string|max:20',
            'gender' => 'required|in:Male,Female,Other',
            'doctor_charge' => 'required|numeric|min:0',
            'designation_id' => 'required',
            'department_id' => 'required',
            'specialist_id' => 'required',
        ]);

        $role = Role::where('name', 'Doctor')->first();
        if (!$role) {
            $role = Role::create([
                'name' => 'Doctor',
                'guard_name' => 'admin',
                'description' => 'Doctor role with access to doctor specific features'
            ]);
        }

        $admin = Admin::create([
            'first_name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => '12345678',
            'role_id' => $role->id,
            'doctor_charge' => $validated['doctor_charge'],
            'status' => 'Active'
        ]);

        $admin->details()->create([
            'gender' => $validated['gender'],
            'designation_id' => $validated['designation_id'],
            'department_id' => $validated['department_id'],
            'specialist_id' => $validated['specialist_id'],
        ]);

        return redirect()->back()->with([
            'successMessage' => 'Doctor created successfully',
            'doctor' => $admin
        ]);
    }
}
