<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\IpdPatientRequest;
use App\Services\AdminService;
use App\Services\BedGroupService;
use App\Services\BedService;
use Illuminate\Support\Facades\DB;
use App\Services\IpdPatientService;
use App\Services\PatientService;
use App\Services\IpdAutoChargeService;
use App\Services\IpdDischargeBillingService;
use App\Services\ActivityLogService;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;
use App\Models\IpdPrescription;
use App\Models\IpdPrescriptionMedicine;
use App\Models\IpdPrescriptionTest;
use App\Models\Admin;
use App\Models\InvoiceDesign;
use App\Models\WebSetting;
use App\Models\Payment;
use App\Models\Bed;
use App\Models\IpdNote;
use App\Models\IpdPatient;
use App\Models\SymptomType;
use Illuminate\Support\Facades\Storage;
use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;
use Barryvdh\DomPDF\Facade\Pdf;


class IpdPatientController extends Controller
{
    use SystemTrait;

    protected $ipdpatientService, $patientService, $adminService, $bedGroupService, $bedService;
    protected IpdAutoChargeService $ipdAutoChargeService;
    protected IpdDischargeBillingService $ipdDischargeBillingService;

    public function __construct(IpdPatientService $ipdpatientService, PatientService $patientService, AdminService $adminService, BedGroupService $bedGroupService, BedService $bedService, IpdAutoChargeService $ipdAutoChargeService, IpdDischargeBillingService $ipdDischargeBillingService)

    {
        $this->ipdpatientService = $ipdpatientService;
        $this->patientService = $patientService;
        $this->adminService = $adminService;
        $this->bedGroupService = $bedGroupService;
        $this->bedService = $bedService;
        $this->ipdAutoChargeService = $ipdAutoChargeService;
        $this->ipdDischargeBillingService = $ipdDischargeBillingService;


        $this->middleware('auth:admin');
        $this->middleware('permission:ipd-patient-list');
        $this->middleware('permission:ipd-patient-status', ['only' => ['changeStatus', 'regenerateDischargeBilling']]);
        $this->middleware('permission:ipd-patient-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:ipd-patient-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:ipd-patient-delete', ['only' => ['destroy']]);
    }



    


    public function index()
    {
        return Inertia::render(
            'Backend/IpdPatient/Index',
            [
                'pageTitle' => fn() => 'IpdPatient List',
                'isDischargedPage' => fn() => false,
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(false),
            ]
        );
    }

    public function discharged()
    {
        return Inertia::render(
            'Backend/IpdPatient/Index',
            [
                'pageTitle' => fn() => 'Discharged Patient List',
                'isDischargedPage' => fn() => true,
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(true),
            ]
        );
    }

    private function getDatas(bool $onlyDischarged = false)
    {
        $query = $this->ipdpatientService->list();

        if ($onlyDischarged) {
            $query->where('status', 'Inactive');
        }


        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->case = $data->case ?? '';
            $customData->patient_id = $data?->patient?->name ?? '';
            $customData->gender = $data?->patient?->gender ?? '';
            $customData->phone = $data?->patient?->phone;
            $customData->consultant_doctor_id = $data?->doctor?->name ?? '';
            $customData->bed_id = $data?->bed?->name ?? '';
            $customData->is_antenatal = $data->is_antenatal ?? 'No';
            $customData->credit_limit = $data->credit_limit;
                        $customData->status = $data->status === 'Inactive'
                ? 'Discharged'
                : getStatusText($data->status);


            /** @var \App\Models\Admin|null $user */
            $user = auth('admin')->user();
            $customData->hasLink = true;
            $customData->links = [];

            if ($user && $user->can('ipd-patient-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' .
                        (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.ipdpatient.status.change', [
                        'id' => $data->id,
                        'status' => $data->status == 'Active' ? 'Inactive' : 'Active'
                    ]),
                                        'linkLabel' => getLinkLabel(
                        (($data->status == 'Active') ? 'Discharge' : 'Activate'),
                        null,
                        null
                    )
                ];

            }

                        
                                    $customData->links[] = [
                'linkClass' => 'bg-green-600 hover:bg-green-700 hover:opacity-100 text-white semi-bold',
                'link' => route('backend.ipdpatient.show', $data->id),
                'linkLabel' => getLinkLabel('Overview', null, null)
            ];

            if ($user && $user->can('ipd-patient-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.ipdpatient.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

                        $customData->links[] = [
                'linkClass' => 'bg-indigo-500 text-white semi-bold',
                'link' => route('backend.ipdpatient.prescription', $data->id),
                'linkLabel' => getLinkLabel('View Prescription', null, null)
            ];

                        $customData->links[] = [
                            'linkClass' => 'bg-slate-700 text-white semi-bold',
                            'link' => route('backend.ipdpatient.prescription.print', $data->id),
                            'linkLabel' => getLinkLabel('Print Prescription', null, null),
                            'target' => '_blank',
                        ];

                        $customData->links[] = [
                            'linkClass' => 'bg-cyan-700 text-white semi-bold',
                            'link' => route('backend.ipdpatient.running-bill.print', $data->id),
                            'linkLabel' => getLinkLabel('Running Bill', null, null),
                            'target' => '_blank',
                        ];

                        if ($data->status === 'Inactive') {
                $customData->links[] = [
                    'linkClass' => 'bg-teal-600 text-white semi-bold',
                                'link' => route('backend.ipdpatient.discharge-certificate.print', $data->id),
                    'target' => '_blank',
                    'linkLabel' => getLinkLabel('Discharge Certificate', null, null)
                ];
            }



            if ($user && $user->can('ipd-patient-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.ipdpatient.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
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
            ['fieldName' => 'case', 'class' => 'text-center'],
            ['fieldName' => 'patient_id', 'class' => 'text-center'],
            ['fieldName' => 'gender', 'class' => 'text-center'],
            ['fieldName' => 'phone', 'class' => 'text-center'],
            ['fieldName' => 'consultant_doctor_id', 'class' => 'text-center'],
            ['fieldName' => 'bed_id', 'class' => 'text-center'],
            ['fieldName' => 'is_antenatal', 'class' => 'text-center'],
            ['fieldName' => 'credit_limit', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Case',
            'Name',
            'Gender',
            'Phone',
            'Consultant Doctor',
            'Bed',
            'Is Antenatal',
            'Credit Limit',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/IpdPatient/Form',
            [
                'pageTitle' => fn() => 'IpdPatient Create',
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
                'bedGroups' => fn() => $this->bedGroupService->activeList(),
                'beds' => fn() => $this->bedService->activeList()->load('bedGroup'),
                'symptomTypes' => fn() => SymptomType::query()->where('status', 'Active')->orderBy('name')->get(),
            ]
        );
    }


    public function store(IpdPatientRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $bedId = $data['bed_id'] ?? null;
            if ($bedId) {
                $bed = Bed::query()->find($bedId);
                if (!$bed || $bed->status !== 'Active') {
                    DB::rollBack();
                    return redirect()
                        ->back()
                        ->with('errorMessage', 'Selected bed is not available.');
                }

                $occupied = IpdPatient::query()
                    ->where('status', 'Active')
                    ->where('bed_id', $bedId)
                    ->exists();

                if ($occupied) {
                    DB::rollBack();
                    return redirect()
                        ->back()
                        ->with('errorMessage', 'Selected bed is already occupied.');
                }
            }

            // Extract advance amount from validated data so we don't try
            // to insert a non-existent column into the `ipdpatients` table.
            $advance = (float) ($data['advance_amount'] ?? 0);
            unset($data['advance_amount']);

            $dataInfo = $this->ipdpatientService->create($data);

            if ($dataInfo) {
                // Auto-create running room rent + bed charge for the selected bed.
                $this->ipdAutoChargeService->syncAdmissionCharges($dataInfo, auth('admin')->id());

                // If an advance was provided during admission, record it as a Payment
                $advance = (float) ($request->input('advance_amount') ?? 0);
                if ($advance > 0) {
                    \App\Models\Payment::create([
                        'ipd_patient_id' => $dataInfo->id,
                        'amount' => $advance,
                        'payment_method' => $request->input('advance_payment_method') ?? 'Advance',
                        'transaction_id' => $request->input('advance_transaction_id') ?? null,
                        'notes' => $request->input('advance_notes') ?? 'Advance on admission',
                        'received_by' => auth('admin')->id(),
                        'payment_status' => 'Paid',
                        'status' => 'Active',
                    ]);
                }

                $message = 'IpdPatient created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'ipdpatients', $message);
                ActivityLogService::logCreate(
                    'IPD Patient',
                    $dataInfo->id,
                    (string) ($dataInfo->case ?? ('IPD#' . $dataInfo->id)),
                    [
                        'case' => $dataInfo->case,
                        'patient_id' => $dataInfo->patient_id,
                        'consultant_doctor_id' => $dataInfo->consultant_doctor_id,
                        'bed_id' => $dataInfo->bed_id,
                        'admission_date' => $dataInfo->date,
                        'status' => $dataInfo->status,
                    ]
                );

                DB::commit();


                return redirect()
                    ->back()
                    ->with('successMessage', $message)
                    ->with('billId', $dataInfo->id);
            } else {
                DB::rollBack();

                $message = "Failed To create IpdPatient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
                } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'IpdPatientController', 'store', substr($err->getMessage(), 0, 1000));

            $message = "Server Errors Occur. Please Try Again.";

            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

        public function show($id)
    {
        $ipdpatient = $this->ipdpatientService->find($id);

        if (!$ipdpatient) {
            return redirect()
                ->route('backend.ipdpatient.index')
                ->with('errorMessage', 'IPD patient not found.');
        }

                $ipdpatient->loadMissing([ 
            'patient', 
            'doctor', 
            'bed', 
            'latestPrescription.medicines', 
            'latestPrescription.tests', 
            'roomRentCharges.bed',
            'bedCharges.bed',
            'otCharges',
            'doctorVisitCharges.doctor',

        ]); 


        $payments = Payment::query()
            ->where('ipd_patient_id', $ipdpatient->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get();

        // Prepare overview totals for the UI
        $overviewTotals = [
            'nurse_notes' => $ipdpatient->ipdNotes()->where('type', 'nurse_note')->count(),
            'consultant_register' => $ipdpatient->ipdNotes()->where('type', 'consultant_register')->count(),
            'operations' => $ipdpatient->ipdNotes()->where('type', 'operation')->count(),
            'bed_history' => $ipdpatient->ipdNotes()->where('type', 'bed_history')->count(),
            'medicines' => optional($ipdpatient->latestPrescription)->medicines ? $ipdpatient->latestPrescription->medicines->count() : 0,
            'tests' => optional($ipdpatient->latestPrescription)->tests ? $ipdpatient->latestPrescription->tests->count() : 0,
            'room_rent_charges' => $ipdpatient->roomRentCharges()->count(),
            'bed_charges' => $ipdpatient->bedCharges()->count(),
            'ot_charges' => $ipdpatient->otCharges()->count(),
            'doctor_visit_charges' => $ipdpatient->doctorVisitCharges()->count(),
            'payments' => $payments->count(),
            'live_consultation' => $ipdpatient->live_consultation ?? null,
        ];

        $runningBill = $this->ipdDischargeBillingService->getRunningSummary($ipdpatient);

        return Inertia::render(
            'Backend/IpdPatient/Show',
            [
                'pageTitle' => fn() => 'IPD Discharged Patient',
                'ipdpatient' => fn() => $ipdpatient,
                'latestPrescription' => fn() => $ipdpatient->latestPrescription,
                'payments' => fn() => $payments,
                'overviewTotals' => fn() => $overviewTotals,
                'runningBill' => fn() => $runningBill,
            ]
        );
    }

        public function edit($id)
    {
        $ipdpatient = $this->ipdpatientService->find($id);

        if (!$ipdpatient) {
            return redirect()
                ->route('backend.ipdpatient.index')
                ->with('errorMessage', 'IPD patient not found.');
        }

        return Inertia::render(
            'Backend/IpdPatient/Form',
            [
                'pageTitle' => fn() => 'IpdPatient Edit',
                'ipdpatient' => fn() => $ipdpatient,
                'id' => fn() => $id,
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),

                // needed by the Form (bed group + bed dropdowns)
                'bedGroups' => fn() => $this->bedGroupService->activeList(),
                'beds' => fn() => $this->bedService->activeList()->load('bedGroup'),
                'symptomTypes' => fn() => SymptomType::query()->where('status', 'Active')->orderBy('name')->get(),
            ]
        );
    }



        public function update(IpdPatientRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();

            $bedId = $data['bed_id'] ?? null;
            if ($bedId) {
                $bed = Bed::query()->find($bedId);
                if (!$bed || $bed->status !== 'Active') {
                    DB::rollBack();
                    return redirect()
                        ->back()
                        ->with('errorMessage', 'Selected bed is not available.');
                }

                $occupied = IpdPatient::query()
                    ->where('status', 'Active')
                    ->where('bed_id', $bedId)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($occupied) {
                    DB::rollBack();
                    return redirect()
                        ->back()
                        ->with('errorMessage', 'Selected bed is already occupied.');
                }
            }

            // ipdpatients table doesn't have image/file columns.
            // Sometimes older UI/controller code can still send these keys.
            unset(
                $data['image'],
                $data['file'],
                $data['created_at'],
                $data['updated_at'],
                $data['deleted_at']
            );

            $ipdpatient = $this->ipdpatientService->find($id);
            if (!$ipdpatient) {
                DB::rollBack();

                return redirect()
                    ->back()
                    ->with('errorMessage', 'IPD patient not found.');
            }

            $oldIpdData = [
                'case' => $ipdpatient->case,
                'patient_id' => $ipdpatient->patient_id,
                'consultant_doctor_id' => $ipdpatient->consultant_doctor_id,
                'bed_id' => $ipdpatient->bed_id,
                'admission_date' => $ipdpatient->date,
                'status' => $ipdpatient->status,
            ];

            $dataInfo = $this->ipdpatientService->update($data, $id);

            // Auto-sync running charges when bed changes (and also fills rate if previously 0).
            $this->ipdAutoChargeService->syncAdmissionCharges($dataInfo, auth('admin')->id());

            $message = 'IpdPatient updated successfully';

            $this->storeAdminWorkLog($dataInfo->id, 'ipdpatients', $message);
            ActivityLogService::logUpdate(
                'IPD Patient',
                $dataInfo->id,
                (string) ($dataInfo->case ?? $oldIpdData['case'] ?? ('IPD#' . $dataInfo->id)),
                [
                    'case' => $dataInfo->case,
                    'patient_id' => $dataInfo->patient_id,
                    'consultant_doctor_id' => $dataInfo->consultant_doctor_id,
                    'bed_id' => $dataInfo->bed_id,
                    'admission_date' => $dataInfo->date,
                    'status' => $dataInfo->status,
                ],
                $oldIpdData
            );

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message)
                ->with('billId', $dataInfo->id);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'IpdPatientController', 'update', substr($err->getMessage(), 0, 1000));

            return redirect()
                ->back()
                ->with('errorMessage', 'Server Errors Occur. Please Try Again.');
        }
    }

        
    public function prescription($id)
    {
        $ipdpatient = $this->ipdpatientService->find($id);

        if (!$ipdpatient) {
            return redirect()
                ->route('backend.ipdpatient.index')
                ->with('errorMessage', 'IPD patient not found.');
        }

        $ipdpatient->loadMissing(['patient', 'doctor', 'bed']);

        $prescription = IpdPrescription::where('ipd_patient_id', $id)
            ->with(['medicines', 'tests'])
            ->latest()
            ->first();

                return Inertia::render(
            'Backend/IpdPatient/Prescription',
            [
                'pageTitle' => fn() => 'IPD Prescription',
                'ipdpatient' => fn() => $ipdpatient,
                'prescription' => fn() => $prescription,
                'doctors' => fn() => $this->adminService->activeDoctors(),
            ]
        );
    }

    public function storePrescription(Request $request, $id)
    {
        $validated = $request->validate([
            'doctor_id' => 'nullable|exists:admins,id',
            'doctor_designation' => 'nullable|string|max:255',
            'complaints' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'advice' => 'nullable|string',
            'follow_up_date' => 'nullable|date',
            'doctor_signature' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'doctor_seal' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'medicines' => 'nullable|array',
            'medicines.*.medicine_name' => 'nullable|string|max:255',
            'medicines.*.dose' => 'nullable|string|max:255',
            'medicines.*.frequency' => 'nullable|string|max:255',
            'medicines.*.duration' => 'nullable|string|max:255',
            'medicines.*.instructions' => 'nullable|string|max:255',
            'tests' => 'nullable|array',
            'tests.*' => 'nullable|string|max:255',
        ]);

        $medicineItems = collect($validated['medicines'] ?? [])
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
                    'medicines' => 'At least one medicine or one test is required.',
                ])
                ->withInput();
        }

        $ipdpatient = $this->ipdpatientService->find($id);
        if (!$ipdpatient) {
            return redirect()
                ->route('backend.ipdpatient.index')
                ->with('errorMessage', 'IPD patient not found.');
        }

        $doctorId = $validated['doctor_id']
            ?? $ipdpatient->consultant_doctor_id
            ?? auth('admin')->id();

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

            $existingPrescription = IpdPrescription::where('ipd_patient_id', $id)
                ->latest()
                ->first();

            if ($existingPrescription) {
                $prescription = $existingPrescription;
                $prescription->doctor_id = $doctorId;
                $prescription->doctor_designation = trim((string) ($validated['doctor_designation'] ?? '')) ?: null;
                $prescription->complaints = $validated['complaints'] ?? null;
                $prescription->diagnosis = $validated['diagnosis'] ?? null;
                $prescription->advice = $validated['advice'] ?? null;
                $prescription->follow_up_date = $validated['follow_up_date'] ?? null;
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
                $prescription->updated_by = auth('admin')->id();
                $prescription->save();

                IpdPrescriptionMedicine::where('ipd_prescription_id', $prescription->id)->delete();
                IpdPrescriptionTest::where('ipd_prescription_id', $prescription->id)->delete();
            } else {
                $prescription = IpdPrescription::create([
                    'ipd_patient_id' => $ipdpatient->id,
                    'patient_id' => $ipdpatient->patient_id,
                    'doctor_id' => $doctorId,
                    'doctor_designation' => trim((string) ($validated['doctor_designation'] ?? '')) ?: null,
                    'complaints' => $validated['complaints'] ?? null,
                    'diagnosis' => $validated['diagnosis'] ?? null,
                    'advice' => $validated['advice'] ?? null,
                    'follow_up_date' => $validated['follow_up_date'] ?? null,
                    'doctor_signature_path' => $uploadedSignaturePath,
                    'doctor_seal_path' => $uploadedSealPath,
                    'created_by' => auth('admin')->id(),
                    'updated_by' => auth('admin')->id(),
                ]);
            }

            foreach ($medicineItems as $item) {
                IpdPrescriptionMedicine::create([
                    'ipd_prescription_id' => $prescription->id,
                    'medicine_name' => trim((string) ($item['medicine_name'] ?? '')),
                    'dose' => trim((string) ($item['dose'] ?? '')) ?: null,
                    'frequency' => trim((string) ($item['frequency'] ?? '')) ?: null,
                    'duration' => trim((string) ($item['duration'] ?? '')) ?: null,
                    'instructions' => trim((string) ($item['instructions'] ?? '')) ?: null,
                ]);
            }

            foreach ($testItems as $testName) {
                IpdPrescriptionTest::create([
                    'ipd_prescription_id' => $prescription->id,
                    'test_name' => $testName,
                ]);
            }

            $message = 'Prescription saved successfully.';
            $this->storeAdminWorkLog($prescription->id, 'ipd_prescriptions', $message);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'IpdPatientController', 'storePrescription', substr($err->getMessage(), 0, 1000));

            return redirect()
                ->back()
                ->with('errorMessage', 'Failed to save prescription.');
        }
    }

    /**
     * Store a payment for the given IPD patient.
     */
    public function storePayment(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:255',
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $ipdpatient = $this->ipdpatientService->find($id);
        if (!$ipdpatient) {
            return redirect()->back()->with('errorMessage', 'IPD patient not found.');
        }

        try {
            $payment = Payment::create([
                'ipd_patient_id' => $ipdpatient->id,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'] ?? 'Unknown',
                'transaction_id' => $validated['transaction_id'] ?? null,
                'notes' => $validated['notes'] ?? 'Payment on IPD',
                'received_by' => auth('admin')->id(),
                'payment_status' => 'Paid',
                'status' => 'Active',
            ]);

            return redirect()->back()->with('successMessage', 'Payment recorded successfully.');
        } catch (Exception $err) {
            $this->storeSystemError('Backend', 'IpdPatientController', 'storePayment', substr($err->getMessage(), 0, 1000));
            return redirect()->back()->with('errorMessage', 'Server Errors Occur. Please Try Again.');
        }
    }

    /**
     * Generic note store for different IPD note types.
     */
    public function storeNote(Request $request, $id)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:nurse_note,consultant_register,operation,bed_history',
            'content' => 'nullable|string',
        ]);

        $ipdpatient = $this->ipdpatientService->find($id);
        if (!$ipdpatient) {
            return redirect()->back()->with('errorMessage', 'IPD patient not found.');
        }

        try {
            $content = $validated['content'] ?? null;

            // Attach a generated operation reference to operation notes.
            if (($validated['type'] ?? null) === 'operation') {
                $referenceNo = $this->generateOperationReferenceNo();
                $content = trim(($referenceNo ? ('[' . $referenceNo . '] ') : '') . (string) ($content ?? ''));
            }

            IpdNote::create([
                'ipd_patient_id' => $ipdpatient->id,
                'type' => $validated['type'],
                'content' => $content,
                'created_by' => auth('admin')->id(),
                'status' => 'Active',
            ]);

            return redirect()->back()->with('successMessage', 'Entry saved successfully.');
        } catch (Exception $err) {
            $this->storeSystemError('Backend', 'IpdPatientController', 'storeNote', substr($err->getMessage(), 0, 1000));
            return redirect()->back()->with('errorMessage', 'Server Errors Occur. Please Try Again.');
        }
    }

    private function generateOperationReferenceNo(): string
    {
        $nextSerial = ((int) IpdNote::query()->where('type', 'operation')->max('id')) + 1;

        return prefixed_serial('operation_reference_no_prefix', 'OPRN', $nextSerial, 6);
    }

    /**
     * Update live consultation flag for IPD patient.
     */
    public function updateLiveConsultation(Request $request, $id)
    {
        $validated = $request->validate([
            'live_consultation' => 'nullable|string|max:255',
        ]);

        $ipdpatient = $this->ipdpatientService->find($id);
        if (!$ipdpatient) {
            return redirect()->back()->with('errorMessage', 'IPD patient not found.');
        }

        try {
            $ipdpatient->live_consultation = $validated['live_consultation'] ?? null;
            $ipdpatient->save();

            return redirect()->back()->with('successMessage', 'Live consultation updated.');
        } catch (Exception $err) {
            $this->storeSystemError('Backend', 'IpdPatientController', 'updateLiveConsultation', substr($err->getMessage(), 0, 1000));
            return redirect()->back()->with('errorMessage', 'Server Errors Occur. Please Try Again.');
        }
    }

        public function printPrescription($id)
    {
        $printData = $this->buildPrescriptionPrintData($id);

        if ($printData instanceof \Illuminate\Http\RedirectResponse) {
            return $printData;
        }

        return view('backend.ipd.prescription-print', array_merge($printData, [
            'forPdf' => false,
        ]));
    }

    public function printRunningBill($id)
    {
        $ipdpatient = $this->ipdpatientService->find($id);

        if (!$ipdpatient) {
            return redirect()
                ->route('backend.ipdpatient.index')
                ->with('errorMessage', 'IPD patient not found.');
        }

        $printData = $this->ipdDischargeBillingService->getRunningDetails($ipdpatient);

        $barcodeImage = '';
        try {
            $dns1d = new DNS1D();
            $code = prefixed_serial('ipd_no_prefix', 'IPDN', $ipdpatient->id, 4);
            $barcodeImage = 'data:image/png;base64,' . $dns1d->getBarcodePNG($code, 'C128', 2.2, 52);
        } catch (Exception $err) {
            $barcodeImage = '';
        }

        return view('backend.ipd.running-bill-print', array_merge($printData, [
            'barcodeImage' => $barcodeImage,
        ]));
    }

        public function downloadPrescriptionPdf($id)
    {
        $printData = $this->buildPrescriptionPrintData($id);

        if ($printData instanceof \Illuminate\Http\RedirectResponse) {
            return $printData;
        }

        $fileName = 'ipd_prescription_' . ($printData['prescriptionCode'] ?? 'rx') . '.pdf';

        $pdf = Pdf::loadView('backend.ipd.prescription-print', array_merge($printData, [
            'forPdf' => true,
        ]))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'dejavu sans',
                'dpi' => 96,
            ]);

                // Some browsers show raw PDF text if Content-Type is not application/pdf.
        // So we force correct headers explicitly.
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }


    private function buildPrescriptionPrintData($id)
    {
        $ipdpatient = $this->ipdpatientService->find($id);

        if (!$ipdpatient) {
            return redirect()
                ->route('backend.ipdpatient.index')
                ->with('errorMessage', 'IPD patient not found.');
        }

        $ipdpatient->loadMissing(['patient', 'doctor.details.designation', 'bed']);

        $prescription = IpdPrescription::where('ipd_patient_id', $id)
            ->with(['medicines', 'tests'])
            ->latest()
            ->first();

        if (!$prescription) {
            return redirect()
                ->route('backend.ipdpatient.prescription', $id)
                ->with('errorMessage', 'No prescription found to print.');
        }

        $invoiceDesign = InvoiceDesign::where('status', 'Active')
            ->where('module', 'prescription')
            ->first();

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

        $prescriptionCode = prefixed_serial('ipd_prescription_prefix', 'IPDP', $prescription->id, 6);
        $printedAt = now()->format('d-m-Y h:i A');
        $admissionDate = $safeDate($ipdpatient?->created_at, 'd-m-Y', now()->format('d-m-Y'));
        $prescriptionDate = $safeDate($prescription?->created_at, 'd-m-Y', now()->format('d-m-Y'));
        $followUpDate = $safeDate($prescription?->follow_up_date, 'd-m-Y', 'N/A');
        $patientCode = prefixed_serial('ipd_no_prefix', 'IPDN', ($ipdpatient->id ?? 0), 4);

        $doctor = Admin::with('details.designation')->find($prescription->doctor_id) ?: ($ipdpatient->doctor ?? null);
        $doctorName = $safeText($doctor?->name);
        $doctorDegree = $safeText($doctor?->details?->qualification);

        $doctorDesignation = trim((string) ($prescription?->doctor_designation ?? ''));
        if ($doctorDesignation === '') {
            $doctorDesignation = $doctor?->details?->designation ?? '';
        }
        if (is_object($doctorDesignation) && isset($doctorDesignation->name)) {
            $doctorDesignation = $doctorDesignation->name;
        }
        if (!is_string($doctorDesignation) || trim((string) $doctorDesignation) === '') {
            $doctorDesignation = 'Consultant';
        }
        $doctorDesignation = $safeText($doctorDesignation, 'Consultant');

        $patientName = $safeText($ipdpatient?->patient?->name);
        $patientAge = $safeText($ipdpatient?->patient?->age);
        $patientGender = $safeText($ipdpatient?->patient?->gender);

        $qrCodeImage = '';
        try {
            $dns2d = new DNS2D();
            $qrPayload = implode('|', [
                'RX:' . $prescriptionCode,
                'IPD:' . $ipdpatient->id,
                'Patient:' . ($ipdpatient?->patient?->name ?? 'N/A'),
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
        $banglaFontPath = '';

        $banglaFontFile = public_path('fonts/NotoSansBengali-Regular.ttf');
        if (is_file($banglaFontFile)) {
            $normalized = str_replace('\\', '/', $banglaFontFile);
            $banglaFontPath = str_starts_with($normalized, '/')
                ? 'file://' . $normalized
                : 'file:///' . ltrim($normalized, '/');
        }

        if ($invoiceDesign && $invoiceDesign->header_photo_path) {
            $storagePath = storage_path('app/public/' . ltrim($invoiceDesign->header_photo_path, '/'));
            if (file_exists($storagePath)) {
                $extension = pathinfo($storagePath, PATHINFO_EXTENSION) ?: 'png';
                $headerImageBase64 = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        if ($invoiceDesign && $invoiceDesign->footer_photo_path) {
            $storagePath = storage_path('app/public/' . ltrim($invoiceDesign->footer_photo_path, '/'));
            if (file_exists($storagePath)) {
                $extension = pathinfo($storagePath, PATHINFO_EXTENSION) ?: 'png';
                $footerImageBase64 = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        $medicineItems = $prescription->medicines
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

        $investigationItems = $prescription->tests
            ->map(function ($item) use ($safeText) {
                return $safeText($item?->test_name, '');
            })
            ->filter(function ($testName) {
                return $testName !== '' && strtoupper($testName) !== 'N/A';
            })
            ->unique()
            ->values()
            ->all();

        $complaints = $safeText($prescription->complaints);
        $diagnosis = $safeText($prescription->diagnosis);
        $adviceNotes = $safeText($prescription->advice);

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

                return [
            'ipdpatient' => $ipdpatient,
            'prescription' => $prescription,
            'headerImage' => $headerImageBase64,
            'footerImage' => $footerImageBase64,
            'footerContent' => $invoiceDesign->footer_content ?? '',
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
            'admissionDate' => $admissionDate,
            'prescriptionDate' => $prescriptionDate,
            'doctorName' => $doctorName,
            'doctorDegree' => $doctorDegree,
            'doctorDesignation' => $doctorDesignation,
            'doctorSealImage' => $doctorSealImage,
            'doctorSignatureImage' => $doctorSignatureImage,
            'bedName' => $safeText($ipdpatient?->bed?->name),
            'caseType' => $safeText($ipdpatient?->case),
            'complaints' => $complaints,
            'diagnosis' => $diagnosis,
            'adviceNotes' => $adviceNotes,
            'followUpDate' => $followUpDate,
            'medicineItems' => $medicineItems,
            'investigationItems' => $investigationItems,
            'patientBarcodeImage' => $patientBarcodeImage,
            'rxBarcodeImage' => $rxBarcodeImage,
            'banglaFontPath' => $banglaFontPath,
        ];
    }

    public function printDischargeCertificate($id)
    {
        $printData = $this->buildDischargeCertificatePrintData($id);

        if ($printData instanceof \Illuminate\Http\RedirectResponse) {
            return $printData;
        }

        return view('backend.ipd.discharge-certificate-print', array_merge($printData, [
            'forPdf' => false,
            'autoPrint' => request()->boolean('auto_print', true),
        ]));
    }

        public function downloadDischargeCertificatePdf($id)
    {
        $printData = $this->buildDischargeCertificatePrintData($id);

        if ($printData instanceof \Illuminate\Http\RedirectResponse) {
            return $printData;
        }

        $fileName = 'ipd_discharge_certificate_' . ($printData['certificateCode'] ?? 'dc') . '.pdf';

        $pdf = Pdf::loadView('backend.ipd.discharge-certificate-print', array_merge($printData, [
            'forPdf' => true,
        ]))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'dejavu sans',
                'dpi' => 96,
            ]);

                // Some browsers show raw PDF text if Content-Type is not application/pdf.
        // So we force correct headers explicitly.
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);

        // If you want forced download instead of opening in browser, use:
        // return $pdf->download($fileName);
    }


    private function buildDischargeCertificatePrintData($id)
    {
        $ipdpatient = $this->ipdpatientService->find($id);

        if (!$ipdpatient) {
            return redirect()
                ->route('backend.ipdpatient.index')
                ->with('errorMessage', 'IPD patient not found.');
        }

                if ($ipdpatient->status !== 'Inactive') {
            return redirect()
                ->route('backend.ipdpatient.show', $id)
                ->with('errorMessage', 'Patient is not discharged yet.');
        }


        $ipdpatient->loadMissing([
            'patient',
            'doctor.details.designation',
            'bed',
            'latestPrescription.medicines',
            'latestPrescription.tests',
        ]);

        $prescription = $ipdpatient->latestPrescription ?: IpdPrescription::where('ipd_patient_id', $id)
            ->with(['medicines', 'tests'])
            ->latest()
            ->first();

        $invoiceDesign = InvoiceDesign::where('status', 'Active')
            ->whereIn('module', ['discharge_certificate', 'prescription'])
            ->orderByRaw("CASE WHEN module = 'discharge_certificate' THEN 0 ELSE 1 END")
            ->first();

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

        $certificateCode = 'IPDDC' . str_pad((string) ($ipdpatient->id ?? 0), 6, '0', STR_PAD_LEFT);
        $printedAt = now()->format('d-m-Y h:i A');
        $patientCode = prefixed_serial('ipd_no_prefix', 'IPDN', ($ipdpatient->id ?? 0), 4);

        $admissionDate = $safeDate($ipdpatient?->admission_date, 'd-m-Y h:i A', now()->format('d-m-Y h:i A'));
        $dischargeAt = $ipdpatient?->discharged_at ?: $ipdpatient?->updated_at;
        $dischargeDate = $safeDate($dischargeAt, 'd-m-Y h:i A', now()->format('d-m-Y h:i A'));

        $doctor = $ipdpatient->doctor ?: null;
        $doctorName = $safeText($doctor?->name);
        $doctorDegree = $safeText($doctor?->details?->qualification);

        $doctorDesignation = $doctor?->details?->designation ?? '';
        if (is_object($doctorDesignation) && isset($doctorDesignation->name)) {
            $doctorDesignation = $doctorDesignation->name;
        }
        if (!is_string($doctorDesignation) || trim((string) $doctorDesignation) === '') {
            $doctorDesignation = 'Consultant';
        }
        $doctorDesignation = $safeText($doctorDesignation, 'Consultant');

        $patientName = $safeText($ipdpatient?->patient?->name);
        $patientAge = $safeText($ipdpatient?->patient?->age);
        $patientGender = $safeText($ipdpatient?->patient?->gender);
        $patientPhone = $safeText($ipdpatient?->patient?->phone);
        $patientAddress = $safeText($ipdpatient?->patient?->address);

        $diagnosis = $safeText($prescription?->diagnosis, $safeText($ipdpatient?->symptom_description));
        $adviceNotes = $safeText($prescription?->advice, $safeText($ipdpatient?->note));
        $followUpDate = $safeDate($prescription?->follow_up_date, 'd-m-Y', 'N/A');

        $medicineItems = collect($prescription?->medicines ?? [])
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

        $headerImageBase64 = '';
        $footerImageBase64 = '';
        $banglaFontPath = '';

        $banglaFontFile = public_path('fonts/NotoSansBengali-Regular.ttf');
        if (is_file($banglaFontFile)) {
            $normalized = str_replace('\\', '/', $banglaFontFile);
            $banglaFontPath = str_starts_with($normalized, '/')
                ? 'file://' . $normalized
                : 'file:///' . ltrim($normalized, '/');
        }

        if ($invoiceDesign && $invoiceDesign->header_photo_path) {
            $storagePath = storage_path('app/public/' . ltrim($invoiceDesign->header_photo_path, '/'));
            if (file_exists($storagePath)) {
                $extension = pathinfo($storagePath, PATHINFO_EXTENSION) ?: 'png';
                $headerImageBase64 = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        if ($invoiceDesign && $invoiceDesign->footer_photo_path) {
            $storagePath = storage_path('app/public/' . ltrim($invoiceDesign->footer_photo_path, '/'));
            if (file_exists($storagePath)) {
                $extension = pathinfo($storagePath, PATHINFO_EXTENSION) ?: 'png';
                $footerImageBase64 = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        $qrCodeImage = '';
        try {
            $dns2d = new DNS2D();
            $qrPayload = implode('|', [
                'DC:' . $certificateCode,
                'IPD:' . $ipdpatient->id,
                'Patient:' . ($ipdpatient?->patient?->name ?? 'N/A'),
                'Discharge:' . $dischargeDate,
            ]);
            $qrCodeImage = 'data:image/png;base64,' . $dns2d->getBarcodePNG($qrPayload, 'QRCODE', 5, 5);
        } catch (\Throwable $err) {
            $qrCodeImage = '';
        }

        $patientBarcodeImage = '';
        $certificateBarcodeImage = '';
        try {
            $dns1d = new DNS1D();
            $patientBarcodeImage = 'data:image/png;base64,' . $dns1d->getBarcodePNG($patientCode, 'C128', 2.2, 52);
            $certificateBarcodeImage = 'data:image/png;base64,' . $dns1d->getBarcodePNG($certificateCode, 'C128', 2.2, 52);
        } catch (\Throwable $err) {
            $patientBarcodeImage = '';
            $certificateBarcodeImage = '';
        }

        return [
            'ipdpatient' => $ipdpatient,
            'prescription' => $prescription,
            'headerImage' => $headerImageBase64,
            'footerImage' => $footerImageBase64,
            'footerContent' => $invoiceDesign->footer_content ?? '',
            'certificateCode' => $certificateCode,
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
            'patientPhone' => $patientPhone,
            'patientAddress' => $patientAddress,
            'admissionDate' => $admissionDate,
            'dischargeDate' => $dischargeDate,
            'doctorName' => $doctorName,
            'doctorDegree' => $doctorDegree,
            'doctorDesignation' => $doctorDesignation,
            'bedName' => $safeText($ipdpatient?->bed?->name),
            'caseType' => $safeText($ipdpatient?->case),
            'diagnosis' => $diagnosis,
            'adviceNotes' => $adviceNotes,
            'followUpDate' => $followUpDate,
            'medicineItems' => $medicineItems,
            'patientBarcodeImage' => $patientBarcodeImage,
            'certificateBarcodeImage' => $certificateBarcodeImage,
            'banglaFontPath' => $banglaFontPath,
        ];
    }

    public function destroy($id)

    {


        DB::beginTransaction();

        try {

            $ipdpatient = $this->ipdpatientService->find($id);

            if ($this->ipdpatientService->delete($id)) {
                $message = 'IpdPatient deleted successfully';
                $this->storeAdminWorkLog($id, 'ipdpatients', $message);
                ActivityLogService::logDelete(
                    'IPD Patient',
                    $id,
                    (string) ($ipdpatient?->case ?? ('IPD#' . $id)),
                    [
                        'case' => $ipdpatient?->case,
                        'patient_id' => $ipdpatient?->patient_id,
                        'consultant_doctor_id' => $ipdpatient?->consultant_doctor_id,
                        'bed_id' => $ipdpatient?->bed_id,
                        'admission_date' => $ipdpatient?->date,
                        'status' => $ipdpatient?->status,
                    ]
                );

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete IpdPatient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
                } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'IpdPatientController', 'destroy', substr($err->getMessage(), 0, 1000));

            $message = "Server Errors Occur. Please Try Again.";

            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

        public function regenerateDischargeBilling($id)
    {
        DB::beginTransaction();

        try {
            $billing = $this->ipdpatientService->regenerateDischargeBilling((int) $id, auth('admin')->id());

            $message = 'Discharge billing regenerated successfully.';
            $this->storeAdminWorkLog($billing->id, 'billings', $message . ' | IPD#' . $id);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'IpdPatientController', 'regenerateDischargeBilling', substr($err->getMessage(), 0, 1000));

            return redirect()
                ->back()
                ->with('errorMessage', 'Failed to regenerate discharge billing.');
        }
    }

    public function changeStatus(Request $request, $id, $status)
    {
        DB::beginTransaction();


        try {

                        $dataInfo = $this->ipdpatientService->changeStatus($id, $status, auth('admin')->id());


            if ($dataInfo->wasChanged()) {
                $message = 'IpdPatient ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'ipdpatients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "IpdPatient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
                } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'IpdPatientController', 'changeStatus', substr($err->getMessage(), 0, 1000));

                    $message = 'Server Errors Occur. Please Try Again. (' . $err->getMessage() . ')';

            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
