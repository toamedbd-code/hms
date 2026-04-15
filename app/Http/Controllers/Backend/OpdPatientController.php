<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpdPatientRequest;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
use App\Models\OpdPrescription;
use App\Models\OpdPrescriptionItem;
use App\Models\Investigation;
use App\Models\DueCollection;
use App\Models\InvoiceDesign;
use App\Models\WebSetting;
use App\Models\SymptomType;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Storage;
use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;
use Barryvdh\DomPDF\Facade\Pdf;

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
        $this->middleware('permission:opd-patient-list')->except(['doctorPortal']);
        $this->middleware('permission:doctor-portal')->only(['doctorPortal']);
        $this->middleware('permission:opd-patient-status', ['only' => ['changeStatus']]);
        $this->middleware('permission:opd-patient-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:opd-patient-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:opd-patient-invoice');
    }

    public function doctorPortal()
    {
        request()->merge(['doctor_portal' => 1]);

        $user = auth('admin')->user();
        $canSelectDoctor = (bool) ($user && Gate::forUser($user)->check('opd-patient-list'));

        if (!request()->filled('doctor_id') || !$canSelectDoctor) {
            request()->merge(['doctor_id' => auth('admin')->id()]);
        }

        return Inertia::render(
            'Backend/OpdPatient/Index',
            [
                'pageTitle' => fn() => 'Doctor OPD Portal',
                'filters' => fn() => request()->only(['name', 'numOfData', 'doctor_id']),
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
                'isDoctorPortal' => true,
                'canSelectDoctor' => $canSelectDoctor,
                'doctors' => fn() => $this->adminService->activeDoctors()->map(function ($doctor) {
                    return [
                        'id' => $doctor->id,
                        'name' => $doctor->name,
                    ];
                })->values(),
            ]
        );
    }

    public function index()
    {
        return Inertia::render(
            'Backend/OpdPatient/Index',
            [
                'pageTitle' => fn() => 'Opd Patient List',
                'filters' => fn() => request()->only(['name', 'numOfData']),
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
                'isDoctorPortal' => false,
                'canSelectDoctor' => false,
                'doctors' => [],
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->opdpatientService->list();
        $isDoctorPortal = (bool) request()->boolean('doctor_portal');

        if ($isDoctorPortal) {
            $requestedDoctorId = request()->input('doctor_id');
            $user = auth('admin')->user();
            $canSelectDoctor = (bool) ($user && Gate::forUser($user)->check('opd-patient-list'));

            $doctorId = ($canSelectDoctor && !empty($requestedDoctorId))
                ? (int) $requestedDoctorId
                : (int) auth('admin')->id();

            $query->where('consultant_doctor_id', $doctorId);
        }

        if (request()->filled('name')) {
            $searchName = trim((string) request()->input('name'));
            $query->whereHas('patient', function ($patientQuery) use ($searchName) {
                $patientQuery->where(function ($innerQuery) use ($searchName) {
                    $innerQuery->where('name', 'like', '%' . $searchName . '%')
                        ->orWhere('phone', 'like', '%' . $searchName . '%');

                    if (ctype_digit($searchName)) {
                        $innerQuery->orWhere('id', (int) $searchName);
                    }
                });
            });
        }

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
            /** @var \Illuminate\Contracts\Auth\Access\Authorizable|null $user */
            $user = auth('admin')->user();
            $isDoctorPortalRow = (bool) request()->boolean('doctor_portal');

            $customData->links = [];

            if ($isDoctorPortalRow) {
                $customData->links[] = [
                    'linkClass' => 'bg-indigo-500 text-white semi-bold',
                    'link' => route('backend.opdpatient.prescription', $data->id),
                    'linkLabel' => getLinkLabel('View Prescription', null, null)
                ];

                $customData->links[] = [
                    'linkClass' => 'bg-slate-700 text-white semi-bold',
                    'link' => route('backend.opdpatient.prescription.print', $data->id),
                    'linkLabel' => getLinkLabel('Print Prescription', null, null),
                    'target' => '_blank',
                ];

                return $customData;
            }

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

            $customData->links[] = [
                'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                'link' => route('backend.opdpatient.destroy', $data->id),
                'linkLabel' => getLinkLabel('Delete', null, null)
            ];

            $customData->links[] = [
                'linkClass' => 'bg-indigo-500 text-white semi-bold',
                'link' => route('backend.opdpatient.prescription', $data->id),
                'linkLabel' => getLinkLabel('View Prescription', null, null)
            ];

            $customData->links[] = [
                'linkClass' => 'bg-slate-700 text-white semi-bold',
                'link' => route('backend.opdpatient.prescription.print', $data->id),
                'linkLabel' => getLinkLabel('Print Prescription', null, null),
                'target' => '_blank',
            ];

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
            'Due Amount',
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
                'symptomTypes' => fn() => SymptomType::query()->where('status', 'Active')->orderBy('name')->get(),
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
                ActivityLogService::logCreate(
                    'OPD Patient',
                    $dataInfo->id,
                    (string) ($dataInfo->case_id ?? ('OPD#' . $dataInfo->id)),
                    [
                        'case_id' => $dataInfo->case_id,
                        'patient_id' => $dataInfo->patient_id,
                        'consultant_doctor_id' => $dataInfo->consultant_doctor_id,
                        'appointment_date' => $dataInfo->appointment_date,
                        'amount' => $dataInfo->amount,
                        'paid_amount' => $dataInfo->paid_amount,
                        'balance_amount' => $dataInfo->balance_amount,
                        'payment_status' => $dataInfo->payment_status,
                    ]
                );

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
                'symptomTypes' => fn() => SymptomType::query()->where('status', 'Active')->orderBy('name')->get(),
            ]
        );
    }

    public function update(OpdPatientRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $opdpatient = $this->opdpatientService->find($id);

            $oldOpdData = [
                'case_id' => $opdpatient?->case_id,
                'patient_id' => $opdpatient?->patient_id,
                'consultant_doctor_id' => $opdpatient?->consultant_doctor_id,
                'appointment_date' => $opdpatient?->appointment_date,
                'amount' => $opdpatient?->amount,
                'paid_amount' => $opdpatient?->paid_amount,
                'balance_amount' => $opdpatient?->balance_amount,
                'payment_status' => $opdpatient?->payment_status,
            ];

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
                ActivityLogService::logUpdate(
                    'OPD Patient',
                    $dataInfo->id,
                    (string) ($dataInfo->case_id ?? $oldOpdData['case_id'] ?? ('OPD#' . $dataInfo->id)),
                    [
                        'case_id' => $dataInfo->case_id,
                        'patient_id' => $dataInfo->patient_id,
                        'consultant_doctor_id' => $dataInfo->consultant_doctor_id,
                        'appointment_date' => $dataInfo->appointment_date,
                        'amount' => $dataInfo->amount,
                        'paid_amount' => $dataInfo->paid_amount,
                        'balance_amount' => $dataInfo->balance_amount,
                        'payment_status' => $dataInfo->payment_status,
                    ],
                    $oldOpdData
                );

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
            $opdpatient = $this->opdpatientService->find($id);

            if ($this->opdpatientService->delete($id)) {
                DueCollection::query()
                    ->where('payment_method', 'opd')
                    ->where(function ($query) use ($id) {
                        $query->where('note', 'like', '%opd_patient_id:' . $id . '%')
                            ->orWhere('note', 'like', '%opd_patient_id: ' . $id . '%');
                    })
                    ->delete();

                $message = 'OPD Patient deleted successfully';
                $this->storeAdminWorkLog($id, 'opdpatients', $message);
                ActivityLogService::logDelete(
                    'OPD Patient',
                    $id,
                    (string) ($opdpatient?->case_id ?? ('OPD#' . $id)),
                    [
                        'case_id' => $opdpatient?->case_id,
                        'patient_id' => $opdpatient?->patient_id,
                        'consultant_doctor_id' => $opdpatient?->consultant_doctor_id,
                        'appointment_date' => $opdpatient?->appointment_date,
                        'amount' => $opdpatient?->amount,
                        'paid_amount' => $opdpatient?->paid_amount,
                        'balance_amount' => $opdpatient?->balance_amount,
                        'payment_status' => $opdpatient?->payment_status,
                    ]
                );

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

    public function prescription($id)
    {
        $opdpatient = $this->opdpatientService->find($id);

        if (!$opdpatient) {
            return redirect()
                ->route('backend.opdpatient.index')
                ->with('errorMessage', 'OPD patient not found.');
        }

        $prescription = OpdPrescription::where('opd_patient_id', $id)
            ->with('items.investigation')
            ->latest()
            ->first();

        return Inertia::render(
            'Backend/OpdPatient/Prescription',
            [
                'pageTitle' => fn() => 'OPD Prescription',
                'opdpatient' => fn() => $opdpatient,
                'prescription' => fn() => $prescription,
            ]
        );
    }

    public function storePrescription(Request $request, $id)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'nibp' => 'nullable|string|max:255',
            'doctor_signature' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'doctor_seal' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'doctor_designation' => 'nullable|string|max:255',
            'items' => 'nullable|array',
            'items.*.test_name' => 'nullable|string',
            'items.*.medicine_name' => 'nullable|string',
            'items.*.dose' => 'nullable|string',
            'items.*.duration' => 'nullable|string',
            'items.*.frequency' => 'nullable|string',
            'items.*.instructions' => 'nullable|string',
            'tests' => 'nullable|array',
            'tests.*' => 'nullable|string',
        ]);

        $medicineItems = collect($validated['items'] ?? [])
            ->filter(function ($item) {
                return trim((string) ($item['medicine_name'] ?? '')) !== '';
            })
            ->values();

        $testItems = collect($validated['tests'] ?? [])
            ->map(function ($name) {
                return trim((string) $name);
            })
            ->filter(function ($name) {
                return $name !== '';
            })
            ->unique()
            ->values();

        if ($medicineItems->isEmpty() && $testItems->isEmpty()) {
            return redirect()
                ->back()
                ->withErrors([
                    'items' => 'At least one medicine or one test is required.',
                ])
                ->withInput();
        }

        $opdpatient = $this->opdpatientService->find($id);
        if (!$opdpatient) {
            return redirect()
                ->route('backend.opdpatient.index')
                ->with('errorMessage', 'OPD patient not found.');
        }

        DB::beginTransaction();
        try {
            $uploadedSignaturePath = null;
            $uploadedSealPath = null;
            if ($request->hasFile('doctor_signature')) {
                $uploadedSignaturePath = $this->imageUpload($request->file('doctor_signature'), 'prescription-signatures');
            }
            if ($request->hasFile('doctor_seal')) {
                $uploadedSealPath = $this->imageUpload($request->file('doctor_seal'), 'prescription-seals');
            }

            if (Schema::hasColumn('opdpatients', 'nibp')) {
                $opdpatient->nibp = trim((string) ($validated['nibp'] ?? '')) ?: null;
                $opdpatient->save();
            }

            $existingPrescription = OpdPrescription::where('opd_patient_id', $id)
                ->latest()
                ->first();

            if ($existingPrescription) {
                $prescription = $existingPrescription;
                $prescription->notes = $validated['notes'] ?? null;
                $prescription->doctor_designation = trim((string) ($validated['doctor_designation'] ?? '')) ?: null;
                if ($uploadedSignaturePath) {
                    $oldSignaturePath = trim((string) ($prescription->getRawOriginal('doctor_signature_path') ?? ''));
                    if ($oldSignaturePath !== '' && Storage::disk('public')->exists($oldSignaturePath)) {
                        Storage::disk('public')->delete($oldSignaturePath);
                    }
                    $prescription->doctor_signature_path = $uploadedSignaturePath;
                }
                if ($uploadedSealPath) {
                    $oldSealPath = trim((string) ($prescription->getRawOriginal('doctor_seal_path') ?? ''));
                    if ($oldSealPath !== '' && Storage::disk('public')->exists($oldSealPath)) {
                        Storage::disk('public')->delete($oldSealPath);
                    }
                    $prescription->doctor_seal_path = $uploadedSealPath;
                }
                $prescription->updated_by = auth('admin')->user()->id;
                $prescription->save();

                OpdPrescriptionItem::where('opd_prescription_id', $prescription->id)->delete();
            } else {
                $prescription = OpdPrescription::create([
                    'opd_patient_id' => $id,
                    'notes' => $validated['notes'] ?? null,
                    'doctor_signature_path' => $uploadedSignaturePath,
                    'doctor_seal_path' => $uploadedSealPath,
                    'doctor_designation' => trim((string) ($validated['doctor_designation'] ?? '')) ?: null,
                    'created_by' => auth('admin')->user()->id,
                    'updated_by' => auth('admin')->user()->id,
                ]);
            }

            foreach ($medicineItems as $item) {
                OpdPrescriptionItem::create([
                    'opd_prescription_id' => $prescription->id,
                    'test_name' => null,
                    'medicine_name' => trim((string) ($item['medicine_name'] ?? '')),
                    'dose' => trim((string) ($item['dose'] ?? 'N/A')),
                    'duration' => trim((string) ($item['duration'] ?? 'N/A')),
                    'frequency' => $item['frequency'] ?? null,
                    'instructions' => $item['instructions'] ?? null,
                ]);
            }

            foreach ($testItems as $testName) {
                OpdPrescriptionItem::create([
                    'opd_prescription_id' => $prescription->id,
                    'test_name' => $testName,
                    'medicine_name' => 'N/A',
                    'dose' => 'N/A',
                    'duration' => 'N/A',
                    'frequency' => null,
                    'instructions' => null,
                ]);

                if (Schema::hasColumn('tests', 'opd_patient_id')) {
                    $masterTest = Investigation::query()
                        ->where('test_name', $testName)
                        ->whereNull('opd_patient_id')
                        ->first();

                    Investigation::updateOrCreate(
                        [
                            'opd_patient_id' => $opdpatient->id,
                            'test_name' => $testName,
                        ],
                        [
                            'category_type' => $masterTest?->category_type ?? 'Pathology',
                            'test_short_name' => $masterTest?->test_short_name,
                            'test_type' => $masterTest?->test_type,
                            'test_category_id' => $masterTest?->test_category_id ?? 0,
                            'test_sub_category_id' => $masterTest?->test_sub_category_id,
                            'method' => $masterTest?->method,
                            'report_days' => $masterTest?->report_days,
                            'charge_category_id' => $masterTest?->charge_category_id,
                            'charge_name' => $masterTest?->charge_name,
                            'tax' => $masterTest?->tax,
                            'standard_charge' => $masterTest?->standard_charge,
                            'amount' => $masterTest?->amount,
                            'test_parameters' => $masterTest?->test_parameters,
                            'status' => $masterTest?->status ?? 'Active',
                        ]
                    );
                }
            }

            $message = 'Prescription saved successfully.';
            $this->storeAdminWorkLog($prescription->id, 'opd_prescriptions', $message);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'OpdPatientController', 'storePrescription', substr($err->getMessage(), 0, 1000));

            return redirect()
                ->back()
                ->with('errorMessage', 'Failed to save prescription.');
        }
    }

    public function printPrescription($id)
    {
        $printData = $this->buildPrescriptionPrintData($id);

        if ($printData instanceof \Illuminate\Http\RedirectResponse) {
            return $printData;
        }

        return view('backend.opd.prescription-print', array_merge($printData, [
            'forPdf' => false,
        ]));
    }

    public function downloadPrescriptionPdf($id)
    {
        $printData = $this->buildPrescriptionPrintData($id);

        if ($printData instanceof \Illuminate\Http\RedirectResponse) {
            return $printData;
        }

        $pdf = Pdf::loadView('backend.opd.prescription-print', array_merge($printData, [
            'forPdf' => true,
        ]));

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'dejavu sans',
            'dpi' => 96,
        ]);

        $fileName = 'opd_prescription_' . ($printData['prescriptionCode'] ?? 'rx') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"'
        ]);
    }

    private function buildPrescriptionPrintData($id)
    {
        $opdpatient = $this->opdpatientService->find($id);

        if (!$opdpatient) {
            return redirect()
                ->route('backend.opdpatient.index')
                ->with('errorMessage', 'OPD patient not found.');
        }

        $opdpatient->loadMissing(['patient', 'doctor.details.designation']);

        $prescription = OpdPrescription::where('opd_patient_id', $id)
            ->with('items.investigation')
            ->latest()
            ->first();

        if (!$prescription) {
            return redirect()
                ->route('backend.opdpatient.prescription', $id)
                ->with('errorMessage', 'No prescription found to print.');
        }

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', 'prescription')->first();

        $websetting = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();

        $safeText = function ($value, string $default = 'N/A'): string {
            if (is_null($value)) {
                return $default;
            }

            $text = trim((string) $value);
            return $text !== '' ? $text : $default;
        };

        $safeDate = function ($value, string $format = 'd-m-Y', string $default = 'N/A'): string {
            if (empty($value)) {
                return $default;
            }

            try {
                return \Carbon\Carbon::parse($value)->format($format);
            } catch (\Throwable $err) {
                return $default;
            }
        };

        $prescriptionCode = prefixed_serial('opd_prescription_prefix', 'OPDP', $prescription->id, 6);
        $printedAt = now()->format('d-m-Y h:i A');
        $visitDate = $safeDate($opdpatient?->appointment_date, 'd-m-Y', now()->format('d-m-Y'));
        $prescriptionDate = $safeDate($prescription?->created_at, 'd-m-Y', now()->format('d-m-Y'));
        $patientCode = prefixed_serial('opd_no_prefix', 'OPDN', ($opdpatient->id ?? 0), 4);

        $doctorDesignation = trim((string) ($prescription?->doctor_designation ?? ''));
        if ($doctorDesignation === '') {
            $doctorDesignation = $opdpatient?->doctor?->details?->designation ?? '';
        }
        if (is_object($doctorDesignation) && isset($doctorDesignation->name)) {
            $doctorDesignation = $doctorDesignation->name;
        }
        if (!is_string($doctorDesignation) || trim((string) $doctorDesignation) === '') {
            $doctorDesignation = $opdpatient?->consultation_type ?? 'Consultant';
        }
        $doctorDesignation = $safeText($doctorDesignation, 'Consultant');

        $patientName = $safeText($opdpatient?->patient?->name);
        $patientAge = $safeText($opdpatient?->patient?->age);
        $patientGender = $safeText($opdpatient?->patient?->gender);

        $followUpDate = 'N/A';
        if (strtolower((string) ($opdpatient->case ?? '')) === 'followup' && $opdpatient?->appointment_date) {
            $followUpDate = $safeDate($opdpatient->appointment_date, 'd-m-Y', 'N/A');
        }

        $qrCodeImage = '';
        try {
            $dns2d = new DNS2D();
            $qrPayload = implode('|', [
                'RX:' . $prescriptionCode,
                'OPD:' . $opdpatient->id,
                'Patient:' . ($opdpatient?->patient?->name ?? 'N/A'),
                'Printed:' . $printedAt,
            ]);
            $qrCodeImage = 'data:image/png;base64,' . $dns2d->getBarcodePNG($qrPayload, 'QRCODE', 5, 5);
        } catch (\Throwable $err) {
            $qrCodeImage = '';
        }

        $patientBarcodeImage = '';
        $rxBarcodeImage = '';
        try {
            $dns1d = new DNS1D();
            $patientBarcodeImage = 'data:image/png;base64,' . $dns1d->getBarcodePNG($patientCode, 'C128', 2.2, 52);
            $rxBarcodeImage = 'data:image/png;base64,' . $dns1d->getBarcodePNG($prescriptionCode, 'C128', 2.2, 52);
        } catch (\Throwable $err) {
            $patientBarcodeImage = '';
            $rxBarcodeImage = '';
        }

        $headerImageBase64 = '';
        $footerImageBase64 = '';
        $showHeaderFooter = (bool) ($websetting?->opd_prescription_header_footer ?? true);
        $banglaFontPath = '';

        $banglaFontFile = public_path('fonts/NotoSansBengali-Regular.ttf');
        if (is_file($banglaFontFile)) {
            $normalized = str_replace('\\', '/', $banglaFontFile);
            $banglaFontPath = str_starts_with($normalized, '/')
                ? 'file://' . $normalized
                : 'file:///' . ltrim($normalized, '/');
        }

        if ($showHeaderFooter && $invoiceDesign && $invoiceDesign->header_photo_path) {
            $storagePath = storage_path('app/public/' . ltrim($invoiceDesign->header_photo_path, '/'));
            if (file_exists($storagePath)) {
                $extension = pathinfo($storagePath, PATHINFO_EXTENSION) ?: 'png';
                $headerImageBase64 = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        if ($showHeaderFooter && $invoiceDesign && $invoiceDesign->footer_photo_path) {
            $storagePath = storage_path('app/public/' . ltrim($invoiceDesign->footer_photo_path, '/'));
            if (file_exists($storagePath)) {
                $extension = pathinfo($storagePath, PATHINFO_EXTENSION) ?: 'png';
                $footerImageBase64 = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        $medicineItems = $prescription->items
            ->filter(function ($item) {
                $name = trim((string) ($item->medicine_name ?? ''));
                return $name !== '' && strtoupper($name) !== 'N/A';
            })
            ->map(function ($item) use ($safeText) {
                return [
                    'medicine_name' => $safeText($item->medicine_name),
                    'dose' => $safeText($item->dose),
                    'duration' => $safeText($item->duration),
                    'frequency' => $safeText($item->frequency, ''),
                    'instructions' => $safeText($item->instructions),
                ];
            })
            ->values()
            ->all();

        $investigationItems = $prescription->items
            ->map(function ($item) use ($safeText) {
                $masterName = $item?->investigation?->test_name ?? null;
                return $safeText($masterName ?: $item->test_name, '');
            })
            ->filter(function ($testName) {
                return $testName !== '' && strtoupper($testName) !== 'N/A';
            })
            ->unique()
            ->values()
            ->all();

        if (empty($investigationItems)) {
            $investigationItems = $opdpatient->investigationItems()
                ->get()
                ->map(function ($item) use ($safeText) {
                    return $safeText($item->test_name, '');
                })
                ->filter(function ($testName) {
                    return $testName !== '' && strtoupper($testName) !== 'N/A';
                })
                ->unique()
                ->values()
                ->all();
        }

        $chiefComplaints = $safeText($opdpatient?->symptom_title);
        $symptomDescription = $safeText($opdpatient?->symptom_description, '');
        $diagnosis = $safeText($opdpatient?->symptom_type, $safeText($opdpatient?->note));
        $adviceNotes = $safeText($prescription->notes, $safeText($opdpatient?->note));

        $doctorSignatureImage = '';
        $rawDoctorSignaturePath = trim((string) ($prescription->getRawOriginal('doctor_signature_path') ?? ''));
        if ($rawDoctorSignaturePath !== '') {
            $signatureStoragePath = storage_path('app/public/' . ltrim($rawDoctorSignaturePath, '/'));
            if (is_file($signatureStoragePath)) {
                $extension = pathinfo($signatureStoragePath, PATHINFO_EXTENSION) ?: 'png';
                $doctorSignatureImage = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($signatureStoragePath));
            }
        }

        $doctorSealImage = '';
        $rawDoctorSealPath = trim((string) ($prescription->getRawOriginal('doctor_seal_path') ?? ''));
        if ($rawDoctorSealPath !== '') {
            $sealStoragePath = storage_path('app/public/' . ltrim($rawDoctorSealPath, '/'));
            if (is_file($sealStoragePath)) {
                $extension = pathinfo($sealStoragePath, PATHINFO_EXTENSION) ?: 'png';
                $doctorSealImage = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($sealStoragePath));
            }
        }

        $invoiceFooterFallback = config('app.invoice_footer_fallback_line', 'Powered By: www.toamed.com,Dhaka. Support: 01919-592638');

        return [
            'opdpatient' => $opdpatient,
            'prescription' => $prescription,
            'headerImage' => $headerImageBase64,
            'footerImage' => $footerImageBase64,
            'footerContent' => $showHeaderFooter ? ($invoiceDesign->footer_content ?? '') : '',
            'prescriptionCode' => $prescriptionCode,
            'printedAt' => $printedAt,
            'qrCodeImage' => $qrCodeImage,
            'hospitalName' => $safeText($websetting?->company_name, config('app.name', 'Hospital')),
            'hospitalPhone' => $safeText($websetting?->phone),
            'hospitalAddress' => $safeText($websetting?->report_title),
            'hospitalLogo' => (string) ($websetting?->logo ?? ''),
            'patientCode' => $patientCode,
            'patientName' => $patientName,
            'patientAge' => $patientAge,
            'patientGender' => $patientGender,
            'visitDate' => $visitDate,
            'prescriptionDate' => $prescriptionDate,
            'doctorName' => $safeText($opdpatient?->doctor?->name),
            'doctorDegree' => $safeText($opdpatient?->doctor?->details?->qualification),
            'doctorDesignation' => $doctorDesignation,
            'doctorSealImage' => $doctorSealImage,
            'doctorSignatureImage' => $doctorSignatureImage,
            'chiefComplaints' => $chiefComplaints,
            'symptomDescription' => $symptomDescription,
            'diagnosis' => $diagnosis,
            'adviceNotes' => $adviceNotes,
            'followUpDate' => $followUpDate,
            'medicineItems' => $medicineItems,
            'investigationItems' => $investigationItems,
            'patientBarcodeImage' => $patientBarcodeImage,
            'rxBarcodeImage' => $rxBarcodeImage,
            'banglaFontPath' => $banglaFontPath,
            'invoiceFooterFallback' => $invoiceFooterFallback,
            // Add paid amount for print view
            'paidAmount' => (float)($opdpatient->paid_amount ?? 0),
        ];
    }
}
