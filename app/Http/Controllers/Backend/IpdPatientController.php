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
use Illuminate\Support\Facades\Log;
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
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\Charge;
use App\Models\ChargeType;
use App\Models\ChargeCategory;
use App\Models\ChargeUnitType;
use App\Models\ChargeTaxCategory;
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
    protected $chargeService;

    public function __construct(IpdPatientService $ipdpatientService, PatientService $patientService, AdminService $adminService, BedGroupService $bedGroupService, BedService $bedService, IpdAutoChargeService $ipdAutoChargeService, IpdDischargeBillingService $ipdDischargeBillingService, \App\Services\ChargeService $chargeService)

    {
        $this->ipdpatientService = $ipdpatientService;
        $this->patientService = $patientService;
        $this->adminService = $adminService;
        $this->bedGroupService = $bedGroupService;
        $this->bedService = $bedService;
        $this->ipdAutoChargeService = $ipdAutoChargeService;
        $this->ipdDischargeBillingService = $ipdDischargeBillingService;
        $this->chargeService = $chargeService;


        $this->middleware('auth:admin');
        $this->middleware('permission:ipd-patient-list');
        $this->middleware('permission:ipd-patient-status', ['only' => ['changeStatus', 'regenerateDischargeBilling']]);
        $this->middleware('permission:ipd-patient-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:ipd-patient-edit', ['only' => ['edit', 'update', 'addHospitalCharges']]);
        $this->middleware('permission:ipd-patient-delete', ['only' => ['destroy']]);
    }

    protected function normalizeGender($gender): string
    {
        $g = strtolower(trim((string) $gender));

        if (in_array($g, ['male', 'm'], true)) {
            return 'Male';
        }
        if (in_array($g, ['female', 'f'], true)) {
            return 'Female';
        }

        return 'Others';
    }

    protected function mapBillItemCategory(?string $name): string
    {
        $n = strtolower(trim((string) $name));

        if ($n === '') {
            return 'IPD';
        }

        if (str_contains($n, 'path')) {
            return 'Pathology';
        }
        if (str_contains($n, 'radio') || str_contains($n, 'xray') || str_contains($n, 'ct') || str_contains($n, 'mri')) {
            return 'Radiology';
        }
        if (str_contains($n, 'med') || str_contains($n, 'pharm') || str_contains($n, 'drug') || str_contains($n, 'medicine')) {
            return 'Medicine';
        }

        // Map bed/room/ot/visit/doctor charges to explicit DB enum values
        if (str_contains($n, 'room') || str_contains($n, 'rent')) {
            return 'Room Rent';
        }

        if (str_contains($n, 'bed')) {
            return 'Bed Charge';
        }

        if (str_contains($n, 'ot')) {
            return 'OT';
        }

        if (str_contains($n, 'doctor') || str_contains($n, 'visit')) {
            return 'Doctor Visit';
        }

        if (str_contains($n, 'ipd') || str_contains($n, 'admission') || str_contains($n, 'indoor')) {
            return 'IPD';
        }

        if (str_contains($n, 'opd') || str_contains($n, 'outdoor')) {
            return 'OPD';
        }

        // Fallback to IPD for unknown categories in IPD admission/discharge flows
        return 'IPD';
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
            unset($data['hospital_charge_items']);

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

                // If manual hospital charge items were provided at admission,
                // create a Billing with those items.
                $manualItems = $request->input('hospital_charge_items', []);
                if (is_array($manualItems) && count($manualItems) > 0) {
                    $lines = [];
                    foreach ($manualItems as $item) {
                        $name = trim((string) ($item['item_name'] ?? ''));
                        $unitPrice = (float) ($item['unit_price'] ?? 0);
                        $quantity = max(1, (int) ($item['quantity'] ?? 1));

                        if ($name === '' || $unitPrice <= 0) {
                            continue;
                        }

                        $lineTotal = $unitPrice * $quantity;
                        $lines[] = [
                            'item_id' => null,
                            'item_name' => $name,
                            'category' => 'IPD',
                            'unit_price' => $unitPrice,
                            'quantity' => $quantity,
                            'total_amount' => $lineTotal,
                            'discount' => 0,
                            'rugound' => 0,
                            'net_amount' => $lineTotal,
                            'status' => 'Active',
                        ];
                    }

                    if (!empty($lines)) {
                        $total = array_sum(array_map(fn($l) => (float) ($l['net_amount'] ?? 0), $lines));
                        $patient = $dataInfo->patient;
                        $doctor = $dataInfo->doctor;
                        $actorId = (int) (auth('admin')->id() ?? Admin::query()->value('id') ?? 1);

                        $caseNumber = 'IPD-' . str_pad((string) $dataInfo->id, 6, '0', STR_PAD_LEFT);
                        if (Billing::withTrashed()->where('case_number', $caseNumber)->exists()) {
                            $caseNumber .= '-' . now()->format('His');
                        }

                        $billing = Billing::query()->create([
                            'case_number' => $caseNumber,
                            'patient_id' => $patient?->id,
                            'patient_mobile' => (string) ($patient?->mobile ?? $patient?->phone ?? ''),
                            'gender' => $this->normalizeGender($patient?->gender),
                            'doctor_id' => $doctor?->id,
                            'doctor_type' => 'admin',
                            'doctor_name' => $doctor?->name,
                            'card_type' => 'Cash',
                            'pay_mode' => 'Cash',
                            'card_number' => null,
                            'total' => $total,
                            'discount' => 0,
                            'extra_flat_discount' => 0,
                            'discount_type' => 'flat',
                            'payable_amount' => $total,
                            'paid_amt' => 0,
                            'change_amt' => 0,
                            'receiving_amt' => 0,
                            'due_amount' => $total,
                            'delivery_date' => now(),
                            'delivery_time' => null,
                            'remarks' => 'IPD Admission Items | IPD#' . $dataInfo->id,
                            'commission_total' => 0,
                            'physyst_amt' => 0,
                            'commission_slider' => 0,
                            'created_by' => $actorId,
                            'payment_status' => 'Pending',
                            'status' => 'Active',
                        ]);

                        foreach ($lines as $line) {
                            BillItem::query()->create(array_merge($line, ['billing_id' => $billing->id]));
                        }

                        // attach any IPD payments (advance) to this billing
                        Payment::query()->whereNull('deleted_at')->where('status', 'Active')->where('ipd_patient_id', $dataInfo->id)->whereNull('billing_id')->update(['billing_id' => $billing->id]);

                        $dataInfo->billing_id = $billing->id;
                        $dataInfo->save();
                    }
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

        // Ensure related models needed by the view are present
        $ipdpatient->loadMissing([
            'patient',
            'doctor',
            'bed',
            'ipdNotes',
            'billing',
            'billing.billItems',
        ]);

        // Payments related to this IPD patient
        $payments = Payment::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('ipd_patient_id', $ipdpatient->id)
            ->get();

        // Overview counters used by the frontend summary
        $overviewTotals = [
            'nurse_notes' => IpdNote::where('ipd_patient_id', $ipdpatient->id)->where('type', 'nurse_note')->count(),
            'consultant_register' => IpdNote::where('ipd_patient_id', $ipdpatient->id)->where('type', 'consultant_register')->count(),
            'operations' => IpdNote::where('ipd_patient_id', $ipdpatient->id)->where('type', 'operation')->count(),
            'bed_history' => IpdNote::where('ipd_patient_id', $ipdpatient->id)->where('type', 'bed_history')->count(),
            'medicines' => (int) ($ipdpatient->latestPrescription?->medicines?->count() ?? 0),
            'tests' => (int) ($ipdpatient->latestPrescription?->tests?->count() ?? 0),
            'room_rent_charges' => $ipdpatient->roomRentCharges()->count(),
            'bed_charges' => $ipdpatient->bedCharges()->count(),
            'ot_charges' => $ipdpatient->otCharges()->count(),
            'doctor_visit_charges' => $ipdpatient->doctorVisitCharges()->count(),
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
                    'charges' => fn() => $this->chargeService->activeList(),
                    'chargeTypes' => fn() => ChargeType::query()->where('status', 'Active')->orderBy('name')->get(),
                    'chargeCategories' => fn() => ChargeCategory::query()->where('status', 'Active')->orderBy('name')->get(),
                    'chargeUnits' => fn() => ChargeUnitType::query()->where('status', 'Active')->orderBy('name')->get(),
                    'taxCategories' => fn() => ChargeTaxCategory::query()->where('status', 'Active')->orderBy('name')->get(),
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
                $data['deleted_at'],
                $data['hospital_charge_items']
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

            // If manual hospital charge items were provided during update,
            // create or append Billing items.
            $manualItems = $request->input('hospital_charge_items', []);
            if (is_array($manualItems) && count($manualItems) > 0) {
                $lines = [];
                foreach ($manualItems as $item) {
                    $name = trim((string) ($item['item_name'] ?? ''));
                    $unitPrice = (float) ($item['unit_price'] ?? 0);
                    $quantity = max(1, (int) ($item['quantity'] ?? 1));

                    if ($name === '' || $unitPrice <= 0) {
                        continue;
                    }

                    $lineTotal = $unitPrice * $quantity;
                    $lines[] = [
                        'item_id' => null,
                        'item_name' => $name,
                        'category' => 'IPD',
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'total_amount' => $lineTotal,
                        'discount' => 0,
                        'rugound' => 0,
                        'net_amount' => $lineTotal,
                        'status' => 'Active',
                    ];
                }

                if (!empty($lines)) {
                    $total = array_sum(array_map(fn($l) => (float) ($l['net_amount'] ?? 0), $lines));
                    $patient = $dataInfo->patient;
                    $doctor = $dataInfo->doctor;
                    $actorId = (int) (auth('admin')->id() ?? Admin::query()->value('id') ?? 1);

                    $billing = null;
                    if (!empty($dataInfo->billing_id)) {
                        $billing = Billing::query()->find($dataInfo->billing_id);
                    }

                    if (!$billing) {
                        $caseNumber = 'IPD-' . str_pad((string) $dataInfo->id, 6, '0', STR_PAD_LEFT);
                        if (Billing::withTrashed()->where('case_number', $caseNumber)->exists()) {
                            $caseNumber .= '-' . now()->format('His');
                        }

                        $billing = Billing::query()->create([
                            'case_number' => $caseNumber,
                            'patient_id' => $patient?->id,
                            'patient_mobile' => (string) ($patient?->mobile ?? $patient?->phone ?? ''),
                            'gender' => $this->normalizeGender($patient?->gender),
                            'doctor_id' => $doctor?->id,
                            'doctor_type' => 'admin',
                            'doctor_name' => $doctor?->name,
                            'card_type' => 'Cash',
                            'pay_mode' => 'Cash',
                            'card_number' => null,
                            'total' => $total,
                            'discount' => 0,
                            'extra_flat_discount' => 0,
                            'discount_type' => 'flat',
                            'payable_amount' => $total,
                            'paid_amt' => 0,
                            'change_amt' => 0,
                            'receiving_amt' => 0,
                            'due_amount' => $total,
                            'delivery_date' => now(),
                            'delivery_time' => null,
                            'remarks' => 'IPD Items | IPD#' . $dataInfo->id,
                            'commission_total' => 0,
                            'physyst_amt' => 0,
                            'commission_slider' => 0,
                            'created_by' => $actorId,
                            'payment_status' => 'Pending',
                            'status' => 'Active',
                        ]);

                        $dataInfo->billing_id = $billing->id;
                        $dataInfo->save();
                    }

                    foreach ($lines as $line) {
                        BillItem::query()->create(array_merge($line, ['billing_id' => $billing->id]));
                    }

                    // recalc totals
                    $billing->loadMissing('billItems');
                    $newTotal = (float) ($billing->billItems?->sum('net_amount') ?? 0);
                    $paymentsSum = (float) Payment::where('billing_id', $billing->id)->sum('amount');
                    $dueAmount = max(0, $newTotal - $paymentsSum);
                    $billing->fill([
                        'total' => $newTotal,
                        'payable_amount' => $newTotal,
                        'paid_amt' => $paymentsSum,
                        'receiving_amt' => $paymentsSum,
                        'due_amount' => $dueAmount,
                        'payment_status' => ($paymentsSum >= $newTotal) ? 'Paid' : ($paymentsSum > 0 ? 'Partial' : 'Pending'),
                    ]);
                    $billing->save();

                    Payment::query()->whereNull('deleted_at')->where('status', 'Active')->where('ipd_patient_id', $dataInfo->id)->whereNull('billing_id')->update(['billing_id' => $billing->id]);
                }
            }

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

    /**
     * Add selected hospital charges to IPD and create/append a Billing + BillItems.
     */
    public function addHospitalCharges(Request $request, $id)
    {
        $validated = $request->validate([
            'hospital_charge_ids' => 'required|array|min:1',
            'hospital_charge_ids.*' => 'integer|exists:charges,id',
        ]);

        $ipdpatient = $this->ipdpatientService->find($id);
        if (!$ipdpatient) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'IPD patient not found.'], 404);
            }
            return redirect()->back()->with('errorMessage', 'IPD patient not found.');
        }

        DB::beginTransaction();
        try {
            $chargeIds = $validated['hospital_charge_ids'] ?? [];
            $lines = [];

            foreach ($chargeIds as $cid) {
                $charge = Charge::query()->find($cid);
                if (!$charge) continue;
                $amount = (float) ($charge->standard_charge ?? 0);
                if ($amount <= 0) continue;

                $lines[] = [
                    'item_id' => (int) $charge->id,
                    'item_name' => (string) $charge->name,
                    'category' => $this->mapBillItemCategory($charge->chargeCategory?->name ?? null),
                    'unit_price' => $amount,
                    'quantity' => 1,
                    'total_amount' => $amount,
                    'discount' => 0,
                    'rugound' => 0,
                    'net_amount' => $amount,
                    'status' => 'Active',
                ];
            }

            if (empty($lines)) {
                DB::rollBack();
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => 'No valid charges selected.'], 422);
                }
                return redirect()->back()->with('errorMessage', 'No valid charges selected.');
            }

            $total = array_sum(array_map(fn($l) => (float) ($l['net_amount'] ?? 0), $lines));

            $patient = $ipdpatient->patient;
            $doctor = $ipdpatient->doctor;
            $actorId = (int) (auth('admin')->id() ?? Admin::query()->value('id') ?? 1);

            // If a billing already exists for this IPD, append; otherwise create.
            $billing = null;
            if (!empty($ipdpatient->billing_id)) {
                $billing = Billing::query()->find($ipdpatient->billing_id);
            }

            if (!$billing) {
                $caseNumber = 'IPD-' . str_pad((string) $ipdpatient->id, 6, '0', STR_PAD_LEFT);
                if (Billing::withTrashed()->where('case_number', $caseNumber)->exists()) {
                    $caseNumber .= '-' . now()->format('His');
                }

                $billing = Billing::query()->create([
                    'case_number' => $caseNumber,
                    'patient_id' => $patient?->id,
                    'patient_mobile' => (string) ($patient?->mobile ?? $patient?->phone ?? ''),
                    'gender' => $this->normalizeGender($patient?->gender),
                    'doctor_id' => $doctor?->id,
                    'doctor_type' => 'admin',
                    'doctor_name' => $doctor?->name,
                    'card_type' => 'Cash',
                    'pay_mode' => 'Cash',
                    'card_number' => null,
                    'total' => $total,
                    'discount' => 0,
                    'extra_flat_discount' => 0,
                    'discount_type' => 'flat',
                    'payable_amount' => $total,
                    'paid_amt' => 0,
                    'change_amt' => 0,
                    'receiving_amt' => 0,
                    'due_amount' => $total,
                    'delivery_date' => now(),
                    'delivery_time' => null,
                    'remarks' => 'IPD Selected Charges | IPD#' . $ipdpatient->id,
                    'commission_total' => 0,
                    'physyst_amt' => 0,
                    'commission_slider' => 0,
                    'created_by' => $actorId,
                    'payment_status' => 'Pending',
                    'status' => 'Active',
                ]);

                // attach billing id to ipd
                $ipdpatient->billing_id = $billing->id;
                $ipdpatient->save();
            } else {
                // append: update totals after creating items
            }

            $createdItemIds = [];
            foreach ($lines as $line) {
                $line['category'] = $this->mapBillItemCategory($line['category'] ?? null);

                // Prevent rapid duplicate creation of the same hospital charge
                $recentDuplicate = BillItem::query()
                    ->where('billing_id', $billing->id)
                    ->where('item_id', $line['item_id'])
                    ->where('unit_price', $line['unit_price'])
                    ->where('quantity', $line['quantity'])
                    ->where('net_amount', $line['net_amount'])
                    ->where('status', 'Active')
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->exists();

                if ($recentDuplicate) {
                    continue;
                }

                $created = BillItem::query()->create(array_merge($line, ['billing_id' => $billing->id]));
                if ($created && $created->id) {
                    $createdItemIds[] = $created->id;
                }
            }

            // update billing totals
            $billing->loadMissing('billItems');
            $newTotal = (float) ($billing->billItems?->sum('net_amount') ?? 0);
            $paymentsSum = (float) Payment::where('billing_id', $billing->id)->sum('amount');
            $dueAmount = max(0, $newTotal - $paymentsSum);
            $billing->fill([
                'total' => $newTotal,
                'payable_amount' => $newTotal,
                'paid_amt' => $paymentsSum,
                'receiving_amt' => $paymentsSum,
                'due_amount' => $dueAmount,
                'payment_status' => ($paymentsSum >= $newTotal) ? 'Paid' : ($paymentsSum > 0 ? 'Partial' : 'Pending'),
            ]);
            $billing->save();

            // attach any IPD payments to billing
            Payment::query()->whereNull('deleted_at')->where('status', 'Active')->where('ipd_patient_id', $ipdpatient->id)->whereNull('billing_id')->update(['billing_id' => $billing->id]);

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Selected charges added to invoice.',
                    'billId' => $ipdpatient->id,
                    'created_item_ids' => $createdItemIds,
                ], 200);
            }

            return redirect()->back()->with('successMessage', 'Selected charges added to invoice.')->with('billId', $ipdpatient->id)->with('createdItemIds', $createdItemIds);
        } catch (Exception $err) {
            DB::rollBack();
            try {
                Log::error('addHospitalCharges failed', [
                    'message' => $err->getMessage(),
                    'file' => $err->getFile(),
                    'line' => $err->getLine(),
                    'trace' => $err->getTraceAsString(),
                    'request' => $request->all(),
                ]);
            } catch (Exception $_e) {
                // ignore logging failure
            }
            $this->storeSystemError('Backend', 'IpdPatientController', 'addHospitalCharges', substr($err->getMessage(), 0, 1000));
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Failed to add charges.'], 500);
            }
            return redirect()->back()->with('errorMessage', 'Failed to add charges.');
        }
    }

    /**
     * Create a manual hospital charge (ad-hoc item) and attach it to IPD billing.
     * This allows quick item entry from the IPD screen without creating a Charge record.
     */
    public function addManualHospitalCharge(Request $request, $id)
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'unit_price' => 'required|numeric|min:0',
            'quantity' => 'nullable|integer|min:1',
            'category' => 'nullable|string|max:255',
        ]);

        $ipdpatient = $this->ipdpatientService->find($id);
        if (!$ipdpatient) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'IPD patient not found.'], 404);
            }
            return redirect()->back()->with('errorMessage', 'IPD patient not found.');
        }

        DB::beginTransaction();
        try {
            $quantity = max(1, (int) ($validated['quantity'] ?? 1));
            $unitPrice = (float) ($validated['unit_price'] ?? 0);
            $net = $unitPrice * $quantity;

            // create or find billing for this IPD
            $billing = null;
            if (!empty($ipdpatient->billing_id)) {
                $billing = Billing::query()->find($ipdpatient->billing_id);
            }

            $patient = $ipdpatient->patient;
            $doctor = $ipdpatient->doctor;
            $actorId = (int) (auth('admin')->id() ?? Admin::query()->value('id') ?? 1);

            if (!$billing) {
                $caseNumber = 'IPD-' . str_pad((string) $ipdpatient->id, 6, '0', STR_PAD_LEFT);
                if (Billing::withTrashed()->where('case_number', $caseNumber)->exists()) {
                    $caseNumber .= '-' . now()->format('His');
                }

                $billing = Billing::query()->create([
                    'case_number' => $caseNumber,
                    'patient_id' => $patient?->id,
                    'patient_mobile' => (string) ($patient?->mobile ?? $patient?->phone ?? ''),
                    'gender' => $this->normalizeGender($patient?->gender),
                    'doctor_id' => $doctor?->id,
                    'doctor_type' => 'admin',
                    'doctor_name' => $doctor?->name,
                    'card_type' => 'Cash',
                    'pay_mode' => 'Cash',
                    'card_number' => null,
                    'total' => $net,
                    'discount' => 0,
                    'extra_flat_discount' => 0,
                    'discount_type' => 'flat',
                    'payable_amount' => $net,
                    'paid_amt' => 0,
                    'change_amt' => 0,
                    'receiving_amt' => 0,
                    'due_amount' => $net,
                    'delivery_date' => now(),
                    'delivery_time' => null,
                    'remarks' => 'IPD Manual Charge | IPD#' . $ipdpatient->id,
                    'commission_total' => 0,
                    'physyst_amt' => 0,
                    'commission_slider' => 0,
                    'created_by' => $actorId,
                    'payment_status' => 'Pending',
                    'status' => 'Active',
                ]);

                // attach billing id to ipd
                $ipdpatient->billing_id = $billing->id;
                $ipdpatient->save();
            }

            $line = [
                'item_id' => 0,
                'item_name' => (string) $validated['item_name'],
                'category' => $this->mapBillItemCategory($validated['category'] ?? null),
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'total_amount' => $unitPrice * $quantity,
                'discount' => 0,
                'rugound' => 0,
                'net_amount' => $net,
                'status' => 'Active',
            ];

            // Prevent rapid duplicate manual charge creation: if an identical
            // manual item (same name, price, qty, net) was added to this
            // billing within the last few seconds, treat as already-added.
            $recentDuplicate = BillItem::query()
                ->where('billing_id', $billing->id)
                ->where('item_name', $line['item_name'])
                ->where('unit_price', $line['unit_price'])
                ->where('quantity', $line['quantity'])
                ->where('net_amount', $line['net_amount'])
                ->where('status', 'Active')
                ->where('created_at', '>=', now()->subSeconds(30))
                ->exists();

            if ($recentDuplicate) {
                // Nothing to create; commit transaction and return success
                DB::commit();
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['success' => true, 'message' => 'Item already added recently.', 'created_item_id' => null], 200);
                }
                return redirect()->back()->with('successMessage', 'Item already added recently.')->with('billId', $ipdpatient->id)->with('createdItemId', null);
            }

            $created = BillItem::query()->create(array_merge($line, ['billing_id' => $billing->id]));

            // update billing totals
            $billing->loadMissing('billItems');
            $newTotal = (float) ($billing->billItems?->sum('net_amount') ?? 0);
            $paymentsSum = (float) Payment::where('billing_id', $billing->id)->sum('amount');
            $dueAmount = max(0, $newTotal - $paymentsSum);
            $billing->fill([
                'total' => $newTotal,
                'payable_amount' => $newTotal,
                'paid_amt' => $paymentsSum,
                'receiving_amt' => $paymentsSum,
                'due_amount' => $dueAmount,
                'payment_status' => ($paymentsSum >= $newTotal) ? 'Paid' : ($paymentsSum > 0 ? 'Partial' : 'Pending'),
            ]);
            $billing->save();

            // attach any IPD payments to billing
            Payment::query()->whereNull('deleted_at')->where('status', 'Active')->where('ipd_patient_id', $ipdpatient->id)->whereNull('billing_id')->update(['billing_id' => $billing->id]);

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Item added to invoice.', 'billId' => $ipdpatient->id, 'created_item_id' => $created->id ?? null], 200);
            }

            return redirect()->back()->with('successMessage', 'Item added to invoice.')->with('billId', $ipdpatient->id)->with('createdItemId', $created->id ?? null);
        } catch (Exception $err) {
            DB::rollBack();
            try {
                Log::error('addManualHospitalCharge failed', [
                    'message' => $err->getMessage(),
                    'file' => $err->getFile(),
                    'line' => $err->getLine(),
                    'trace' => $err->getTraceAsString(),
                    'request' => $request->all(),
                ]);
            } catch (Exception $_e) {
                // ignore logging failure
            }
            $this->storeSystemError('Backend', 'IpdPatientController', 'addManualHospitalCharge', substr($err->getMessage(), 0, 1000));
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Failed to add manual charge.'], 500);
            }
            return redirect()->back()->with('errorMessage', 'Failed to add manual charge.');
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
            'billing_id' => 'nullable|integer|exists:billings,id',
        ]);

        $ipdpatient = $this->ipdpatientService->find($id);
        if (!$ipdpatient) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'IPD patient not found.'], 404);
            }
            return redirect()->back()->with('errorMessage', 'IPD patient not found.');
        }

        try {
            $billingId = $validated['billing_id'] ?? ($ipdpatient->billing_id ?? null);

            $paymentData = [
                'ipd_patient_id' => $ipdpatient->id,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'] ?? 'Unknown',
                'transaction_id' => $validated['transaction_id'] ?? null,
                'notes' => $validated['notes'] ?? 'Payment on IPD',
                'received_by' => auth('admin')->id(),
                'payment_status' => 'Paid',
                'status' => 'Active',
            ];
            if ($billingId) {
                $paymentData['billing_id'] = $billingId;
            }

            $payment = Payment::create($paymentData);

            // refresh billing totals if billing present
            if ($billingId) {
                try {
                    $this->ipdDischargeBillingService->refreshBillingTotals($ipdpatient, auth('admin')->id());
                } catch (Exception $_e) {
                    Log::warning('Failed to refresh billing totals after payment', ['err' => $_e->getMessage()]);
                }
            }

            if ($request->expectsJson() || $request->ajax()) {
                $runningBill = $this->ipdDischargeBillingService->getRunningSummary($ipdpatient);
                $billing = null;
                if (!empty($ipdpatient->billing_id)) {
                    $billing = Billing::query()->find($ipdpatient->billing_id);
                }
                return response()->json([
                    'success' => true,
                    'message' => 'Payment recorded successfully.',
                    'payment' => $payment,
                    'runningBill' => $runningBill,
                    'billing' => $billing,
                ], 200);
            }

            return redirect()->back()->with('successMessage', 'Payment recorded successfully.');
        } catch (Exception $err) {
            $this->storeSystemError('Backend', 'IpdPatientController', 'storePayment', substr($err->getMessage(), 0, 1000));
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Server Errors Occur. Please Try Again.'], 500);
            }
            return redirect()->back()->with('errorMessage', 'Server Errors Occur. Please Try Again.');
        }
    }

    /**
     * Update an existing payment for an IPD patient (AJAX-friendly).
     */
    public function updatePayment(Request $request, $id, $paymentId)
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:255',
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $ipdpatient = $this->ipdpatientService->find($id);
        if (!$ipdpatient) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'IPD patient not found.'], 404);
            }
            return redirect()->back()->with('errorMessage', 'IPD patient not found.');
        }

        $payment = Payment::query()->where('id', $paymentId)->where('ipd_patient_id', $ipdpatient->id)->first();
        if (!$payment) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Payment not found.'], 404);
            }
            return redirect()->back()->with('errorMessage', 'Payment not found.');
        }

        try {
            if (isset($validated['amount'])) $payment->amount = $validated['amount'];
            if (array_key_exists('payment_method', $validated)) $payment->payment_method = $validated['payment_method'];
            if (array_key_exists('transaction_id', $validated)) $payment->transaction_id = $validated['transaction_id'];
            if (array_key_exists('notes', $validated)) $payment->notes = $validated['notes'];
            $payment->updated_by = auth('admin')->id();
            $payment->save();

            // refresh billing totals if needed
            try {
                $this->ipdDischargeBillingService->refreshBillingTotals($ipdpatient, auth('admin')->id());
            } catch (Exception $_e) {
                Log::warning('Failed to refresh billing totals after payment update', ['err' => $_e->getMessage()]);
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Payment updated successfully.', 'payment' => $payment], 200);
            }

            return redirect()->back()->with('successMessage', 'Payment updated successfully.');
        } catch (Exception $err) {
            $this->storeSystemError('Backend', 'IpdPatientController', 'updatePayment', substr($err->getMessage(), 0, 1000));
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Failed to update payment.'], 500);
            }
            return redirect()->back()->with('errorMessage', 'Failed to update payment.');
        }
    }

    /**
     * Apply discount to IPD billing (creates billing if missing) and refresh totals.
     */
    public function applyBillingDiscount(Request $request, $id)
    {
        $validated = $request->validate([
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:flat,percentage',
            'extra_flat_discount' => 'nullable|numeric|min:0',
        ]);

        $ipdpatient = $this->ipdpatientService->find($id);
        if (!$ipdpatient) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'IPD patient not found.'], 404);
            }
            return redirect()->back()->with('errorMessage', 'IPD patient not found.');
        }

        DB::beginTransaction();
        try {
            $billing = null;
            if (!empty($ipdpatient->billing_id)) {
                $billing = Billing::query()->find($ipdpatient->billing_id);
            }
            if (!$billing) {
                $billing = $this->ipdDischargeBillingService->createOrGetForDischarge($ipdpatient, auth('admin')->id());
                $ipdpatient->billing_id = $billing->id;
                $ipdpatient->save();
            }

            $billing->discount = (float) ($validated['discount'] ?? 0);
            $billing->discount_type = $validated['discount_type'] ?? ($billing->discount_type ?? 'flat');
            $billing->extra_flat_discount = (float) ($validated['extra_flat_discount'] ?? ($billing->extra_flat_discount ?? 0));
            $billing->save();

            $this->ipdDischargeBillingService->refreshBillingTotals($ipdpatient, auth('admin')->id());

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Discount applied successfully.'], 200);
            }

            return redirect()->back()->with('successMessage', 'Discount applied successfully.');
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'IpdPatientController', 'applyBillingDiscount', substr($err->getMessage(), 0, 1000));
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Failed to apply discount.'], 500);
            }
            return redirect()->back()->with('errorMessage', 'Failed to apply discount.');
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

        // Build a compatibility view-model (`$vm`) expected by the
        // canonical final-invoice template and its partials.
        $websetting = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();

        $ipdpatient->loadMissing(['patient', 'doctor', 'bed', 'billing']);
        $patient = $ipdpatient->patient;
        $billing = $ipdpatient->billing ?? null;

        $safeDate = function ($value, $format = 'd-m-Y h:i A') {
            if (empty($value)) return 'N/A';
            try {
                return \Carbon\Carbon::parse($value)->format($format);
            } catch (\Throwable $_) {
                return 'N/A';
            }
        };

        // Map lines to the final-invoice expected structure
        $rawLines = $printData['lines'] ?? [];
        $vmLines = [];
        foreach ($rawLines as $idx => $ln) {
            $category = (string) ($ln['category'] ?? '');
            $serviceAt = $ln['service_at'] ?? ($printData['printed_at'] ?? null);

            $vmLines[] = [
                'sl' => $idx + 1,
                'service_at' => is_string($serviceAt) ? $serviceAt : $safeDate($serviceAt),
                'department_code' => strtoupper(substr(trim($category), 0, 3)),
                'particulars' => (string) ($ln['item_name'] ?? $ln['item_name'] ?? ''),
                'is_package' => false,
                'qty' => (float) ($ln['quantity'] ?? 1),
                'unit_price' => (float) ($ln['unit_price'] ?? 0),
                'gross_amount' => (float) ($ln['total_amount'] ?? $ln['net_amount'] ?? 0),
                'discount_amount' => (float) ($ln['discount'] ?? 0),
                'taxable_amount' => (float) ($ln['net_amount'] ?? 0),
                'tax_rate' => null,
                'tax_amount' => 0.0,
                'net_amount' => (float) ($ln['net_amount'] ?? 0),
            ];
        }

        // Department summary grouping
        $deptMap = [];
        foreach ($vmLines as $ln) {
            $d = $ln['department_code'] ?? 'OTH';
            if (!isset($deptMap[$d])) {
                $deptMap[$d] = [
                    'department_name' => $d,
                    'gross_amount' => 0.0,
                    'package_included_amount' => 0.0,
                    'discount_amount' => 0.0,
                    'taxable_amount' => 0.0,
                    'tax_rate_effective' => null,
                    'tax_amount' => 0.0,
                    'net_amount' => 0.0,
                ];
            }
            $deptMap[$d]['gross_amount'] += $ln['gross_amount'];
            $deptMap[$d]['discount_amount'] += $ln['discount_amount'];
            $deptMap[$d]['taxable_amount'] += $ln['taxable_amount'];
            $deptMap[$d]['tax_amount'] += $ln['tax_amount'];
            $deptMap[$d]['net_amount'] += $ln['net_amount'];
        }
        $deptSummary = array_values($deptMap);

        // Totals
        $totals = [
            'gross_total' => array_sum(array_map(fn($r) => $r['gross_amount'], $vmLines)),
            'package_included_total' => 0.0,
            'discount_total' => array_sum(array_map(fn($r) => $r['discount_amount'], $vmLines)),
            'taxable_total' => array_sum(array_map(fn($r) => $r['taxable_amount'], $vmLines)),
            'tax_total' => array_sum(array_map(fn($r) => $r['tax_amount'], $vmLines)),
            'net_total' => array_sum(array_map(fn($r) => $r['net_amount'], $vmLines)),
        ];

        // Payments breakdown
        $paymentsQuery = Payment::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('ipd_patient_id', $ipdpatient->id);
        $paymentsCol = $paymentsQuery->get();
        $paidTotal = (float) $paymentsCol->sum('amount');

        $modeBreakdown = [
            'cash' => 0.0,
            'card' => 0.0,
            'mfs' => 0.0,
            'bank' => 0.0,
            'cheque' => 0.0,
            'other' => 0.0,
        ];
        foreach ($paymentsCol as $p) {
            $m = strtolower(trim((string) ($p->payment_method ?? '')));
            if (str_contains($m, 'cash')) {
                $modeBreakdown['cash'] += (float) $p->amount;
            } elseif (str_contains($m, 'card')) {
                $modeBreakdown['card'] += (float) $p->amount;
            } elseif (preg_match('/bkash|nagad|rocket|mfs/', $m)) {
                $modeBreakdown['mfs'] += (float) $p->amount;
            } elseif (str_contains($m, 'bank')) {
                $modeBreakdown['bank'] += (float) $p->amount;
            } elseif (str_contains($m, 'cheque') || str_contains($m, 'check')) {
                $modeBreakdown['cheque'] += (float) $p->amount;
            } else {
                $modeBreakdown['other'] += (float) $p->amount;
            }
        }

        $receiptNos = $paymentsCol->pluck('id')->map(fn($v) => 'P#' . $v)->implode(', ');

        $vm = [
            'hospital' => [
                'name' => $websetting?->company_name ?? config('app.name'),
                'address' => $websetting?->address ?? $websetting?->report_title ?? '',
                'phone' => $websetting?->phone ?? '',
                'email' => $websetting?->email ?? '',
                'logo_url' => trim((string) ($websetting?->getRawOriginal('logo') ?? $websetting?->logo ?? '')),
                'bin_vat_no' => $websetting?->bin_vat_no ?? '',
                'tax_reg_no' => $websetting?->tax_reg_no ?? '',
            ],
            'invoice' => [
                'invoice_no' => $billing?->invoice_number ?? '',
                'ipd_no' => prefixed_serial('ipd_no_prefix', 'IPDN', $ipdpatient->id, 4),
                'uhid' => $patient?->tpa_code ?? ($patient?->id ?? ''),
                'printed_at' => $printData['printed_at'] ?? now()->format('d-m-Y h:i A'),
                'admission_at' => $safeDate($printData['admission_at'] ?? $ipdpatient->admission_date ?? null),
                'discharge_at' => $safeDate($ipdpatient->discharged_at ?? null),
                'length_of_stay_label' => (function () use ($ipdpatient) {
                    try {
                        $start = \Carbon\Carbon::parse($ipdpatient->admission_date);
                        $end = $ipdpatient->discharged_at ? \Carbon\Carbon::parse($ipdpatient->discharged_at) : now();
                        $days = $start->diffInDays($end) + 1;
                        return $days . ' days';
                    } catch (\Throwable $_) {
                        return 'N/A';
                    }
                })(),
            ],
            'patient' => [
                'name' => $patient?->name ?? 'N/A',
                'age' => $patient?->age ?? 'N/A',
                'gender' => $patient?->gender ?? 'N/A',
                'mobile' => $patient?->phone ?? ($patient?->mobile ?? ''),
                'credit_limit' => (float) ($ipdpatient->credit_limit ?? $patient?->credit_limit ?? 0),
                'address' => $patient?->address ?? '',
                'ward' => $ipdpatient->bed?->bedGroup?->name ?? '',
                'room' => $ipdpatient->bed?->name ?? '',
                'bed' => $ipdpatient->bed?->name ?? '',
                'consultant_name' => $ipdpatient->doctor?->name ?? '',
                'ref_doctor_name' => '',
            ],
            'payer' => [
                'payer_type' => 'SELF',
                'company_name' => '',
                'policy_no' => '',
                'approval_no' => '',
                'coverage_type' => '',
            ],
            'package' => ['exists' => false],
            'dept_summary' => $deptSummary,
            'lines' => $vmLines,
            'totals' => $totals,
            'payments' => [
                'advance_total' => 0.0,
                'paid_total_excluding_advances' => $paidTotal,
                'patient_final_payable' => max(0.0, $totals['net_total'] - $paidTotal),
                'paid_total' => $paidTotal,
                'due_amount' => max(0.0, $totals['net_total'] - $paidTotal),
                'refund_amount' => max(0.0, $paidTotal - $totals['net_total']),
                'mode_breakdown' => $modeBreakdown,
                'receipt_nos' => $receiptNos,
            ],
            'insurance' => [
                'approved_amount' => 0.0,
                'non_payable_amount' => 0.0,
                'remarks' => '',
            ],
        ];

        return view('prints.ipd.final-invoice', ['vm' => $vm]);
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
        $banglaFontUrl = '';

        $banglaFontFile = public_path('fonts/NotoSansBengali-Regular.ttf');
        if (is_file($banglaFontFile)) {
            // Prefer web-served font URL; avoid emitting file:// URIs that browsers block.
            $banglaFontUrl = asset('fonts/NotoSansBengali-Regular.ttf');
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
            'banglaFontUrl' => $banglaFontUrl,
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
        $banglaFontUrl = '';

        $banglaFontFile = public_path('fonts/NotoSansBengali-Regular.ttf');
        if (is_file($banglaFontFile)) {
            // Prefer web-served font URL; avoid emitting file:// URIs that browsers block.
            $banglaFontUrl = asset('fonts/NotoSansBengali-Regular.ttf');
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
            'banglaFontUrl' => $banglaFontUrl,
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
