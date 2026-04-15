<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppoinmentRequest;
use App\Models\Admin;
use App\Services\AdminService;
use App\Services\ActivityLogService;
use Spatie\Permission\Traits\HasRoles;
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
use App\Models\OpdPatient;
use App\Models\WebSetting;
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
        $this->middleware('permission:website-inbox', ['only' => ['websiteInbox']]);
        $this->middleware('permission:appoinment-status', ['only' => ['changeStatus']]);
        $this->middleware('permission:appoinment-edit', ['only' => ['edit', 'update']]);
    }

    public function index()
    {
        $isWebsiteInbox = request()->get('booking_source') === 'website';

        return Inertia::render(
            'Backend/Appoinment/Index',
            [
                'pageTitle' => fn() => $isWebsiteInbox ? 'Website Appointment Inbox' : 'Appoinment List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
                'isWebsiteInbox' => fn() => $isWebsiteInbox,
            ]
        );
    }

    public function websiteInbox()
    {
        request()->merge(['booking_source' => 'website']);
        return $this->index();
    }

    private function getDatas()
    {
        $query = $this->appoinmentService->list();

        if (request()->filled('booking_source')) {
            $query->where('booking_source', request()->booking_source);
        }

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
            $customData->booking_source = ucfirst((string) ($data->booking_source ?? 'Panel'));
            $customData->contact_phone = $data->website_contact_phone ?? ($data?->patient?->phone ?? 'N/A');
            $customData->payment = $data->payment_mode;
            $customData->appoinment_status = $data->appoinment_status;
            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;

            $customData->links = [];

            /** @var \Illuminate\Contracts\Auth\Access\Authorizable|null $user */
            $user = auth()->guard('admin')->user();

            if ($user && $user->can('appoinment-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.appoinment.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user && $user->can('appoinment-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.appoinment.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user && $user->can('appoinment-invoice')) {
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
            ['fieldName' => 'booking_source', 'class' => 'text-center'],
            ['fieldName' => 'contact_phone', 'class' => 'text-center'],
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
            'Source',
            'Contact',
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

            $data['transaction_id'] = prefixed_serial('transaction_id_prefix', 'TRID', $nextId, 3);

            $dataInfo = $this->appoinmentService->create($data);

            if ($dataInfo) {
                $this->syncOpdFromAppointment($dataInfo);

                $message = 'Appoinment created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'appoinments', $message);
                ActivityLogService::logCreate(
                    'Appointment',
                    $dataInfo->id,
                    $dataInfo->transaction_id ?? ('Appointment#' . $dataInfo->id),
                    [
                        'transaction_id' => $dataInfo->transaction_id,
                        'patient_id' => $dataInfo->patient_id,
                        'doctor_id' => $dataInfo->doctor_id ?? $dataInfo->admin_id ?? null,
                        'appointment_date' => $dataInfo->appoinment_date,
                        'doctor_fee' => $dataInfo->doctor_fee,
                        'payment_mode' => $dataInfo->payment_mode,
                        'appointment_status' => $dataInfo->appoinment_status,
                    ]
                );

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
                $this->syncOpdFromAppointment($dataInfo);

                $message = 'Appoinment updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'appoinments', $message);
                ActivityLogService::logUpdate(
                    'Appointment',
                    $dataInfo->id,
                    $dataInfo->transaction_id ?? ($appoinment?->transaction_id ?? ('Appointment#' . $dataInfo->id)),
                    [
                        'transaction_id' => $dataInfo->transaction_id,
                        'patient_id' => $dataInfo->patient_id,
                        'doctor_id' => $dataInfo->doctor_id ?? $dataInfo->admin_id ?? null,
                        'appointment_date' => $dataInfo->appoinment_date,
                        'doctor_fee' => $dataInfo->doctor_fee,
                        'payment_mode' => $dataInfo->payment_mode,
                        'appointment_status' => $dataInfo->appoinment_status,
                    ],
                    [
                        'transaction_id' => $appoinment?->transaction_id,
                        'patient_id' => $appoinment?->patient_id,
                        'doctor_id' => $appoinment?->doctor_id ?? $appoinment?->admin_id,
                        'appointment_date' => $appoinment?->appoinment_date,
                        'doctor_fee' => $appoinment?->doctor_fee,
                        'payment_mode' => $appoinment?->payment_mode,
                        'appointment_status' => $appoinment?->appoinment_status,
                    ]
                );

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

            $appoinment = $this->appoinmentService->find($id);

            if ($this->appoinmentService->delete($id)) {
                $message = 'Appoinment deleted successfully';
                $this->storeAdminWorkLog($id, 'appoinments', $message);
                ActivityLogService::logDelete(
                    'Appointment',
                    $id,
                    $appoinment?->transaction_id ?? ('Appointment#' . $id),
                    [
                        'transaction_id' => $appoinment?->transaction_id,
                        'patient_id' => $appoinment?->patient_id,
                        'doctor_id' => $appoinment?->doctor_id ?? $appoinment?->admin_id,
                        'appointment_date' => $appoinment?->appoinment_date,
                        'doctor_fee' => $appoinment?->doctor_fee,
                        'payment_mode' => $appoinment?->payment_mode,
                        'appointment_status' => $appoinment?->appoinment_status,
                    ]
                );

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

    private function syncOpdFromAppointment($appointment): void
    {
        if (!$appointment) {
            return;
        }

        $appointmentStatus = (string) ($appointment->appoinment_status ?? 'Pending');
        $isCancelled = strtolower($appointmentStatus) === 'cancelled';

        $appointmentDate = $appointment->appoinment_date
            ? Carbon::parse($appointment->appoinment_date)->toDateString()
            : now()->toDateString();

        $paymentModeMap = [
            'Cash' => 'cash',
            'Online' => 'online',
            'Card' => 'card',
            'Cheque' => 'cash',
            'Transfer to Bank Account' => 'online',
            'Upi' => 'online',
            'Other' => 'cash',
        ];

        $paymentMode = $paymentModeMap[$appointment->payment_mode ?? ''] ?? 'cash';
        $doctorFee = (float) ($appointment->doctor_fee ?? 0);
        $discount = (float) ($appointment->discount_percentage ?? 0);
        $amount = max(0, $doctorFee);

        $opd = OpdPatient::query()->firstOrNew([
            'reference' => $appointment->transaction_id,
        ]);

        $opd->patient_id = $appointment->patient_id;
        $opd->consultant_doctor_id = $appointment->doctor_id;
        $opd->appointment_date = $appointmentDate;
        $opd->symptom_type = $opd->symptom_type ?: 'Appointment';
        $opd->symptom_title = $opd->symptom_title ?: 'From Appointment';
        $opd->note = $appointment->message;
        $opd->case = $opd->case ?: 'new';
        $opd->casualty = $opd->casualty ?: 'no';
        $opd->old_patient = $opd->old_patient ?: 'yes';
        $opd->apply_tpa = (bool) ($opd->apply_tpa ?? false);
        $opd->charge_id = $opd->charge_id ?? null;
        $opd->charge_type_id = $opd->charge_type_id ?? null;
        $opd->applied_charge = $amount;
        $opd->standard_charge = $amount;
        $opd->tax = (float) ($opd->tax ?? 0);
        $opd->discount = max(0, min(100, $discount));
        $opd->payment_mode = $paymentMode;
        $opd->amount = $amount;
        $opd->paid_amount = (float) ($opd->paid_amount ?? 0);
        $opd->balance_amount = max(0, $amount - (float) $opd->paid_amount);
        $opd->payment_status = $opd->paid_amount <= 0
            ? 'Pending'
            : ((float) $opd->paid_amount >= $amount ? 'Paid' : 'Partial');
        $opd->live_consultation = strtolower((string) ($appointment->live_consultant ?? 'No')) === 'yes' ? 'yes' : 'no';
        $opd->status = $isCancelled ? 'Inactive' : 'Active';

        $opd->save();
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

        // Log doctor creation
        ActivityLogService::logCreate(
            module: 'Doctor',
            recordId: $admin->id,
            recordName: $validated['name'],
            data: [
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'gender' => $validated['gender'],
                'doctor_charge' => $validated['doctor_charge'],
                'designation_id' => $validated['designation_id'],
                'department_id' => $validated['department_id'],
                'specialist_id' => $validated['specialist_id']
            ]
        );

        // Also append this doctor to website featured doctors (CMS) so it's available in appointment dropdowns
        try {
            $webSetting = WebSetting::first();
            if (!$webSetting) {
                $webSetting = new WebSetting();
            }

            $raw = trim((string) ($webSetting->website_featured_doctors_json ?? ''));
            $doctors = [];
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $doctors = $decoded;
                }
            }

            $doctors[] = [
                'name' => $admin->name ?? $admin->first_name,
                'specialty' => '',
                'designation' => '',
                'phone' => $admin->phone ?? '',
                'experience' => '',
                'bio' => '',
                'image_url' => '',
                'admin_id' => $admin->id,
            ];

            $webSetting->website_featured_doctors_json = json_encode($doctors, JSON_UNESCAPED_UNICODE);
            $webSetting->status = $webSetting->status ?? 'Active';
            $webSetting->save();

            // Refresh cached web setting if helper exists
            if (function_exists('get_cached_web_setting')) {
                get_cached_web_setting(true);
            }
        } catch (\Throwable $e) {
            // non-fatal: don't break appointment creation if CMS update fails
        }

        $doctorPayload = [
            'id' => $admin->id,
            'name' => $admin->name ?? $admin->first_name,
            'doctor_charge' => $admin->doctor_charge,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'successMessage' => 'Doctor created successfully',
                'doctor' => $doctorPayload,
            ], 201);
        }

        return redirect()->back()->with([
            'successMessage' => 'Doctor created successfully',
            'doctor' => $doctorPayload,
        ]);
    }
}
