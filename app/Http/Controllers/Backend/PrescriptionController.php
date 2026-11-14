<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrescriptionRequest;
use App\Models\IpdPrescriptionMedicine;
use App\Models\MedicineInventory;
use App\Models\OpdPrescriptionItem;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionMedicine;
use App\Models\PrescriptionSetting;
use App\Models\PrescriptionTest;
use App\Models\Test;
use App\Models\WebSetting;
use App\Services\AdminService;
use App\Traits\SystemTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Milon\Barcode\DNS1D;

class PrescriptionController extends Controller
{
    use SystemTrait;

    protected AdminService $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;

        $this->middleware('auth:admin');
        $this->middleware('module.access:prescription');
        $this->middleware('permission:prescription-list', ['only' => ['index']]);
        $this->middleware('permission:prescription-add|prescription-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:prescription-edit|prescription-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:prescription-delete|prescription-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:prescription-print|prescription-list|prescription-add|prescription-edit', ['only' => ['print', 'pdf']]);
        $this->middleware('permission:prescription-settings', ['only' => ['settings', 'updateSettings']]);
    }

    public function index()
    {
        return Inertia::render('Backend/Prescription/Index', [
            'pageTitle' => fn () => 'Prescription List',
            'breadcrumbs' => fn () => [
                ['link' => null, 'title' => 'Prescription'],
                ['link' => route('backend.prescription.index'), 'title' => 'Prescription List'],
            ],
            'tableHeaders' => fn () => $this->getTableHeaders(),
            'dataFields' => fn () => $this->dataFields(),
            'datas' => fn () => $this->getDatas(),
            'filters' => request()->only(['numOfData', 'prescription_no', 'patient_name', 'date_from', 'date_to']),
            'canCreate' => fn () => $this->actorCan('prescription-add') || $this->actorCan('prescription-list-create'),
        ]);
    }

    public function settings()
    {
        $settings = PrescriptionSetting::query()->first();

        return Inertia::render('Backend/Prescription/Settings', [
            'pageTitle' => 'Prescription Settings',
            'settings' => $settings,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'doctor_designation' => 'nullable|string|max:2000',
            'active_pad_type' => 'nullable|in:header,institution',
            'institution_pad_content_offset' => 'nullable|integer|min:0|max:100',
            'doctor_seal' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'doctor_signature' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'doctor_pad' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'prescription_header' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'institution_pad' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        $settings = PrescriptionSetting::query()->firstOrNew();
        $settings->doctor_designation = trim((string) ($validated['doctor_designation'] ?? '')) ?: null;
        $settings->institution_pad_content_offset = (int) ($validated['institution_pad_content_offset'] ?? 0);
        $oldPadPaths = array_filter([
            $settings->prescription_header_path,
            $settings->institution_pad_path,
            $settings->doctor_pad_path,
        ]);

        foreach ([
            'doctor_seal' => 'doctor_seal_path',
            'doctor_signature' => 'doctor_signature_path',
            'doctor_pad' => 'doctor_pad_path',
            'prescription_header' => 'prescription_header_path',
            'institution_pad' => 'institution_pad_path',
        ] as $fileKey => $pathColumn) {
            if ($request->hasFile($fileKey)) {
                $settings->{$pathColumn} = $this->imageUpload(
                    $request->file($fileKey),
                    'prescription-settings'
                );
            }
        }

        $activePadType = $validated['active_pad_type'] ?? null;
        if ($activePadType === null) {
            $activePadType = $request->hasFile('institution_pad')
                ? 'institution'
                : (($request->hasFile('prescription_header') || $request->hasFile('doctor_pad')) ? 'header' : null);
        }
        if ($activePadType === 'header' && ($request->hasFile('prescription_header') || $request->hasFile('doctor_pad'))) {
            $settings->institution_pad_path = null;
            if ($request->hasFile('prescription_header')) {
                $settings->doctor_pad_path = null;
            } elseif ($request->hasFile('doctor_pad')) {
                $settings->prescription_header_path = null;
            }
        } elseif ($activePadType === 'institution' && $request->hasFile('institution_pad')) {
            $settings->prescription_header_path = null;
            $settings->doctor_pad_path = null;
        }

        $settings->save();

        $retainedPadPaths = array_filter([
            $settings->prescription_header_path,
            $settings->institution_pad_path,
            $settings->doctor_pad_path,
        ]);
        foreach (array_diff(array_unique($oldPadPaths), $retainedPadPaths) as $oldPath) {
            $this->deletePublicFile($oldPath);
        }

        return redirect()->back()->with('successMessage', 'Prescription settings saved successfully.');
    }

    public function create()
    {
        return Inertia::render('Backend/Prescription/Form', [
            'pageTitle' => fn () => 'Prescription Add',
            'prescription' => fn () => null,
            'id' => fn () => null,
            'patients' => fn () => $this->activePatients(),
            'doctors' => fn () => $this->adminService->activeDoctors(),
            'hasPharmacyModule' => fn () => $this->actorHasModule('pharmacy'),
            'prescriptionSettings' => fn () => PrescriptionSetting::query()->first(),
            'canCreate' => fn () => $this->actorCan('prescription-add') || $this->actorCan('prescription-list-create'),
        ]);
    }

    public function store(PrescriptionRequest $request)
    {
        $validated = $request->validated();
        $medicineItems = $this->normalizedMedicines($validated['medicines'] ?? []);
        $testItems = $this->normalizedTests($validated['tests'] ?? []);

        if ($medicineItems->isEmpty() && $testItems->isEmpty()) {
            return redirect()
                ->back()
                ->withErrors(['medicines' => 'At least one medicine or one test is required.'])
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $prescription = Prescription::create([
                'patient_id' => $validated['patient_id'],
                'doctor_id' => $validated['doctor_id'] ?? auth('admin')->id(),
                'doctor_designation' => trim((string) ($validated['doctor_designation'] ?? PrescriptionSetting::query()->value('doctor_designation'))) ?: null,
                'nibp' => trim((string) ($validated['nibp'] ?? '')) ?: null,
                'weight' => trim((string) ($validated['weight'] ?? '')) ?: null,
                'pulse' => trim((string) ($validated['pulse'] ?? '')) ?: null,
                'temperature' => trim((string) ($validated['temperature'] ?? '')) ?: null,
                'complaints' => $validated['complaints'] ?? null,
                'examinations' => $validated['examinations'] ?? null,
                'diagnosis' => $validated['diagnosis'] ?? null,
                'advice' => $validated['advice'] ?? null,
                'follow_up_date' => $validated['follow_up_date'] ?? null,
                'doctor_signature_path' => $request->hasFile('doctor_signature')
                    ? $this->imageUpload($request->file('doctor_signature'), 'prescription-signatures')
                    : null,
                'doctor_seal_path' => $request->hasFile('doctor_seal')
                    ? $this->imageUpload($request->file('doctor_seal'), 'prescription-seals')
                    : null,
                'created_by' => auth('admin')->id(),
                'updated_by' => auth('admin')->id(),
            ]);

            $prescription->prescription_no = prefixed_serial('opd_prescription_prefix', 'RX', $prescription->id, 6);
            $prescription->save();

            $this->syncItems($prescription, $medicineItems, $testItems);

            $this->storeAdminWorkLog($prescription->id, 'prescriptions', 'Prescription created successfully.');
            DB::commit();

            return redirect()
                ->route('backend.prescription.edit', $prescription->id)
                ->with('successMessage', 'Prescription saved successfully.');
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PrescriptionController', 'store', substr($err->getMessage(), 0, 1000));

            return redirect()->back()->with('errorMessage', 'Failed to save prescription.')->withInput();
        }
    }

    public function edit($id)
    {
        $prescription = Prescription::with(['medicines', 'tests', 'patient', 'doctor'])->find($id);
        if (!$prescription) {
            return redirect()->route('backend.prescription.index')->with('errorMessage', 'Prescription not found.');
        }

        return Inertia::render('Backend/Prescription/Form', [
            'pageTitle' => fn () => 'Prescription Edit',
            'prescription' => fn () => $prescription,
            'id' => fn () => $prescription->id,
            'patients' => fn () => $this->activePatients($prescription->patient),
            'doctors' => fn () => $this->adminService->activeDoctors(),
            'hasPharmacyModule' => fn () => $this->actorHasModule('pharmacy'),
            'prescriptionSettings' => fn () => PrescriptionSetting::query()->first(),
            'canCreate' => fn () => $this->actorCan('prescription-add') || $this->actorCan('prescription-list-create'),
        ]);
    }

    public function update(PrescriptionRequest $request, $id)
    {
        $prescription = Prescription::find($id);
        if (!$prescription) {
            return redirect()->route('backend.prescription.index')->with('errorMessage', 'Prescription not found.');
        }

        $validated = $request->validated();
        $medicineItems = $this->normalizedMedicines($validated['medicines'] ?? []);
        $testItems = $this->normalizedTests($validated['tests'] ?? []);

        if ($medicineItems->isEmpty() && $testItems->isEmpty()) {
            return redirect()
                ->back()
                ->withErrors(['medicines' => 'At least one medicine or one test is required.'])
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $prescription->patient_id = $validated['patient_id'];
            $prescription->doctor_id = $validated['doctor_id'] ?? $prescription->doctor_id;
            if (array_key_exists('doctor_designation', $validated)) {
                $prescription->doctor_designation = trim((string) $validated['doctor_designation']) ?: null;
            }
            $prescription->nibp = trim((string) ($validated['nibp'] ?? '')) ?: null;
            $prescription->weight = trim((string) ($validated['weight'] ?? '')) ?: null;
            $prescription->pulse = trim((string) ($validated['pulse'] ?? '')) ?: null;
            $prescription->temperature = trim((string) ($validated['temperature'] ?? '')) ?: null;
            $prescription->complaints = $validated['complaints'] ?? null;
            $prescription->examinations = $validated['examinations'] ?? null;
            $prescription->diagnosis = $validated['diagnosis'] ?? null;
            $prescription->advice = $validated['advice'] ?? null;
            $prescription->follow_up_date = $validated['follow_up_date'] ?? null;
            $prescription->updated_by = auth('admin')->id();

            if ($request->hasFile('doctor_signature')) {
                $this->replaceUploadedFile($prescription, 'doctor_signature_path', $request->file('doctor_signature'), 'prescription-signatures');
            }
            if ($request->hasFile('doctor_seal')) {
                $this->replaceUploadedFile($prescription, 'doctor_seal_path', $request->file('doctor_seal'), 'prescription-seals');
            }

            if (empty($prescription->prescription_no)) {
                $prescription->prescription_no = prefixed_serial('opd_prescription_prefix', 'RX', $prescription->id, 6);
            }

            $prescription->save();
            $this->syncItems($prescription, $medicineItems, $testItems);

            $this->storeAdminWorkLog($prescription->id, 'prescriptions', 'Prescription updated successfully.');
            DB::commit();

            return redirect()->back()->with('successMessage', 'Prescription updated successfully.');
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PrescriptionController', 'update', substr($err->getMessage(), 0, 1000));

            return redirect()->back()->with('errorMessage', 'Failed to update prescription.')->withInput();
        }
    }

    public function destroy($id)
    {
        $prescription = Prescription::find($id);
        if (!$prescription) {
            return redirect()->route('backend.prescription.index')->with('errorMessage', 'Prescription not found.');
        }

        DB::beginTransaction();
        try {
            $this->deletePublicFile($prescription->getRawOriginal('doctor_signature_path'));
            $this->deletePublicFile($prescription->getRawOriginal('doctor_seal_path'));
            $prescription->delete();

            $this->storeAdminWorkLog($id, 'prescriptions', 'Prescription deleted successfully.');
            DB::commit();

            return redirect()->route('backend.prescription.index')->with('successMessage', 'Prescription deleted successfully.');
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PrescriptionController', 'destroy', substr($err->getMessage(), 0, 1000));

            return redirect()->back()->with('errorMessage', 'Failed to delete prescription.');
        }
    }

    public function print($id)
    {
        $printData = $this->buildPrintData($id);
        if ($printData instanceof \Illuminate\Http\RedirectResponse) {
            return $printData;
        }

        return view('backend.prescription.print', array_merge($printData, ['forPdf' => false]));
    }

    public function pdf($id)
    {
        $printData = $this->buildPrintData($id);
        if ($printData instanceof \Illuminate\Http\RedirectResponse) {
            return $printData;
        }

        $fileName = 'prescription_' . ($printData['prescriptionCode'] ?? 'rx') . '.pdf';
        $pdf = Pdf::loadView('backend.prescription.print', array_merge($printData, ['forPdf' => true]))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'dejavu sans',
                'dpi' => 96,
            ]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    public function suggestMedicines(Request $request)
    {
        $query = trim((string) $request->get('q', ''));
        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $results = collect();

        if (Schema::hasTable((new MedicineInventory())->getTable())) {
            $baseQuery = MedicineInventory::query()
                ->where('status', 'Active')
                ->whereNull('deleted_at')
                ->select([
                    'id',
                    'medicine_name',
                    'generic_name',
                    'standard_dose',
                    'standard_frequency',
                    'standard_duration',
                    'standard_instructions',
                    'standard_prescription_verified',
                ]);
            $escapedPrefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
            $prefixResults = (clone $baseQuery)
                ->whereRaw("medicine_name LIKE ? ESCAPE '!'", [$escapedPrefix])
                ->orderBy('medicine_name')
                ->limit(12)
                ->get();
            $inventory = $prefixResults;

            if ($inventory->count() < 12) {
                $containsQuery = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);
                $containsResults = (clone $baseQuery)
                    ->whereRaw("medicine_name LIKE ? ESCAPE '!'", ['%' . $containsQuery . '%'])
                    ->when($prefixResults->isNotEmpty(), fn ($search) => $search->whereNotIn('id', $prefixResults->modelKeys()))
                    ->orderBy('medicine_name')
                    ->limit(12 - $inventory->count())
                    ->get();
                $inventory = $inventory->concat($containsResults);
            }

            foreach ($inventory as $row) {
                $isStandard = (bool) $row->standard_prescription_verified;
                $results->push([
                    'name' => $row->medicine_name,
                    'generic_name' => (string) ($row->generic_name ?? ''),
                    'dose' => $isStandard ? (string) ($row->standard_dose ?? '') : '',
                    'frequency' => $isStandard ? (string) ($row->standard_frequency ?? '') : '',
                    'duration' => $isStandard ? (string) ($row->standard_duration ?? '') : '',
                    'instructions' => $isStandard ? (string) ($row->standard_instructions ?? '') : '',
                    'dose_options' => $isStandard ? array_filter([(string) ($row->standard_dose ?? '')]) : [],
                    'frequency_options' => $isStandard ? array_filter([(string) ($row->standard_frequency ?? '')]) : [],
                    'duration_options' => $isStandard ? array_filter([(string) ($row->standard_duration ?? '')]) : [],
                    'instructions_options' => $isStandard ? array_filter([(string) ($row->standard_instructions ?? '')]) : [],
                    'is_standard' => $isStandard,
                ]);
            }
        }

        $historyTables = [
            [PrescriptionMedicine::class, 'medicine_name'],
            [IpdPrescriptionMedicine::class, 'medicine_name'],
            [OpdPrescriptionItem::class, 'medicine_name'],
        ];

        foreach ($historyTables as [$model, $column]) {
            try {
                $rows = $model::query()
                    ->where($column, 'like', '%' . $query . '%')
                    ->orderByDesc('id')
                    ->limit(12)
                    ->get();

                foreach ($rows as $row) {
                    $name = trim((string) ($row->medicine_name ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $results->push([
                        'name' => $name,
                        'generic_name' => (string) ($row->generic_name ?? ''),
                        'dose' => (string) ($row->dose ?? ''),
                        'frequency' => (string) ($row->frequency ?? ''),
                        'duration' => (string) ($row->duration ?? ''),
                        'instructions' => (string) ($row->instructions ?? ''),
                        'dose_options' => array_filter([(string) ($row->dose ?? '')]),
                        'frequency_options' => array_filter([(string) ($row->frequency ?? '')]),
                        'duration_options' => array_filter([(string) ($row->duration ?? '')]),
                        'instructions_options' => array_filter([(string) ($row->instructions ?? '')]),
                        'history_created_at' => $row->created_at?->getTimestamp() ?? 0,
                        'is_standard' => false,
                    ]);
                }
            } catch (Exception $e) {
                // ignore missing tables
            }
        }

        $unique = $results
            ->groupBy(function ($item) {
                return strtolower(trim((string) ($item['name'] ?? '')));
            })
            ->map(function ($items) {
                $result = $items->first();
                $history = $items->where('is_standard', false)->sortByDesc('history_created_at');

                foreach (['dose', 'frequency', 'duration', 'instructions'] as $field) {
                    $previousValue = $history->first(function ($item) use ($field) {
                        return trim((string) ($item[$field] ?? '')) !== '';
                    });
                    if ($previousValue) {
                        $result[$field] = $previousValue[$field];
                    }

                    $result[$field . '_options'] = $items
                        ->flatMap(function ($item) use ($field) {
                            return array_merge(
                                $item[$field . '_options'] ?? [],
                                [trim((string) ($item[$field] ?? ''))]
                            );
                        })
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                }

                unset($result['history_created_at']);
                return $result;
            })
            ->take(15)
            ->values();

        return response()->json(['results' => $unique]);
    }

    public function suggestTests(Request $request)
    {
        $query = trim((string) $request->get('q', ''));
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = collect();

        if (Schema::hasTable('tests')) {
            $tests = Test::query()
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', 'Active');
                })
                ->where('test_name', 'like', '%' . $query . '%')
                ->limit(15)
                ->get();

            foreach ($tests as $test) {
                $name = trim((string) ($test->test_name ?? $test->name ?? ''));
                if ($name === '') {
                    continue;
                }
                $results->push([
                    'name' => $name,
                    'test_name' => $name,
                    'category_type' => $test->category_type ?? 'Test',
                    'amount' => $test->amount ?? $test->standard_charge ?? null,
                ]);
            }
        }

        return response()->json($results->unique('name')->values());
    }

    private function getDatas()
    {
        $query = Prescription::query()->with(['patient', 'doctor', 'medicines']);

        if (request()->filled('prescription_no')) {
            $search = trim((string) request('prescription_no'));
            $query->where('prescription_no', 'like', '%' . $search . '%');
        }

        if (request()->filled('patient_name')) {
            $search = trim((string) request('patient_name'));
            $query->whereHas('patient', function ($q) use ($search) {
                if (Schema::hasColumn('patients', 'name')) {
                    $q->where('name', 'like', '%' . $search . '%');
                    return;
                }
                $q->where(function ($nameQuery) use ($search) {
                    $nameQuery->orWhere('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%');
                });
            });
        }

        if (request()->filled('date_from')) {
            $query->whereDate('created_at', '>=', request('date_from'));
        }

        if (request()->filled('date_to')) {
            $query->whereDate('created_at', '<=', request('date_to'));
        }

        $datas = $query->orderByDesc('id')->paginate(request()->numOfData ?? 10)->withQueryString();
        $canEdit = $this->actorCan('prescription-edit') || $this->actorCan('prescription-list-edit');
        $canDelete = $this->actorCan('prescription-delete') || $this->actorCan('prescription-list-delete');
        $canPrint = $this->actorCan('prescription-print') || $this->actorCan('prescription-list') || $canEdit;
        $hasPharmacy = $this->actorHasModule('pharmacy') && $this->actorCan('pharmacy-bill-create');

        $formatedDatas = $datas->map(function ($data, $index) use ($canEdit, $canDelete, $canPrint, $hasPharmacy) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->prescription_no = $data->prescription_no ?: prefixed_serial('opd_prescription_prefix', 'RX', $data->id, 6);
            $customData->patient_name = $data->patient_name ?: 'N/A';
            $customData->doctor_name = $data->doctor_name ?: 'N/A';
            $customData->medicine_count = $data->medicines->count();
            $customData->date = optional($data->created_at)->format('d-M-Y');
            $customData->hasLink = true;
            $customData->links = [];

            if ($canEdit) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.prescription.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null),
                ];
            }

            if ($canPrint) {
                $customData->links[] = [
                    'linkClass' => 'bg-emerald-600 text-white semi-bold',
                    'link' => route('backend.prescription.print', $data->id),
                    'linkLabel' => getLinkLabel('Print', null, null),
                    'target' => '_blank',
                ];
            }

            if ($hasPharmacy) {
                $customData->links[] = [
                    'linkClass' => 'bg-blue-600 text-white semi-bold',
                    'link' => route('backend.pharmacybill.create', ['prescription_id' => $data->id]),
                    'linkLabel' => getLinkLabel('Pharmacy', null, null),
                ];
            }

            if ($canDelete) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.prescription.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null),
                ];
            }

            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    private function dataFields(): array
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'prescription_no', 'class' => 'text-center'],
            ['fieldName' => 'patient_name', 'class' => 'text-center'],
            ['fieldName' => 'doctor_name', 'class' => 'text-center'],
            ['fieldName' => 'medicine_count', 'class' => 'text-center'],
            ['fieldName' => 'date', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders(): array
    {
        return [
            'Sl/No',
            'Prescription No',
            'Patient',
            'Doctor',
            'Medicines',
            'Date',
            'Action',
        ];
    }

    private function activePatients($currentPatient = null)
    {
        $patients = Patient::query()
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', 'Active');
            })
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        if ($currentPatient && !$patients->contains('id', $currentPatient->id)) {
            $patients->prepend($currentPatient);
        }

        return $patients->unique('id')->values();
    }

    private function normalizedMedicines(array $medicines)
    {
        return collect($medicines)
            ->filter(fn ($item) => trim((string) ($item['medicine_name'] ?? '')) !== '')
            ->values();
    }

    private function normalizedTests(array $tests)
    {
        return collect($tests)
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->values();
    }

    private function syncItems(Prescription $prescription, $medicineItems, $testItems): void
    {
        PrescriptionMedicine::where('prescription_id', $prescription->id)->delete();
        PrescriptionTest::where('prescription_id', $prescription->id)->delete();

        foreach ($medicineItems as $item) {
            PrescriptionMedicine::create([
                'prescription_id' => $prescription->id,
                'medicine_name' => trim((string) ($item['medicine_name'] ?? '')),
                'generic_name' => trim((string) ($item['generic_name'] ?? '')) ?: null,
                'dose' => trim((string) ($item['dose'] ?? '')) ?: null,
                'frequency' => trim((string) ($item['frequency'] ?? '')) ?: null,
                'duration' => trim((string) ($item['duration'] ?? '')) ?: null,
                'instructions' => trim((string) ($item['instructions'] ?? '')) ?: null,
            ]);
        }

        foreach ($testItems as $testName) {
            PrescriptionTest::create([
                'prescription_id' => $prescription->id,
                'test_name' => $testName,
            ]);
        }
    }

    private function replaceUploadedFile(Prescription $prescription, string $column, $file, string $folder): void
    {
        $oldPath = trim((string) ($prescription->getRawOriginal($column) ?? ''));
        $this->deletePublicFile($oldPath);
        $prescription->{$column} = $this->imageUpload($file, $folder);
    }

    private function deletePublicFile(?string $path): void
    {
        $path = trim((string) $path);
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function actorCan(string $permission): bool
    {
        $user = auth('admin')->user();
        return $user && method_exists($user, 'can') && $user->can($permission);
    }

    private function actorHasModule(string $slug): bool
    {
        return function_exists('user_has_module') && user_has_module(auth('admin')->user(), $slug);
    }

    private function buildPrintData($id)
    {
        $prescription = Prescription::with(['medicines', 'tests', 'patient', 'doctor.details.designation'])->find($id);
        if (!$prescription) {
            return redirect()->route('backend.prescription.index')->with('errorMessage', 'Prescription not found.');
        }

        $websetting = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();
        $safeText = function ($value, string $default = 'N/A'): string {
            $text = trim((string) ($value ?? ''));
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

        $doctor = $prescription->doctor;
        $prescriptionSettings = PrescriptionSetting::query()->first();
        $prescriptionCode = $prescription->prescription_no
            ?: prefixed_serial('opd_prescription_prefix', 'RX', $prescription->id, 6);
        $patientCode = 'PAT-' . str_pad((string) $prescription->patient_id, 6, '0', STR_PAD_LEFT);
        $doctorDesignation = trim((string) ($prescription->doctor_designation ?? ''));
        if ($doctorDesignation === '') {
            $doctorDesignation = trim((string) ($prescriptionSettings?->doctor_designation ?? ''));
        }
        if ($doctorDesignation === '') {
            $designation = $doctor?->details?->designation;
            $doctorDesignation = is_object($designation) ? ($designation->name ?? '') : (string) $designation;
        }
        $barcode = new DNS1D();
        $patientBarcodeImage = 'data:image/png;base64,' . $barcode->getBarcodePNG($patientCode, 'C128', 2.2, 52);
        $rxBarcodeImage = 'data:image/png;base64,' . $barcode->getBarcodePNG($prescriptionCode, 'C128', 2.2, 52);
        $medicines = $prescription->medicines;
        $medicinesWithoutGeneric = $medicines
            ->filter(fn ($medicine) => trim((string) ($medicine->generic_name ?? '')) === '')
            ->pluck('medicine_name')
            ->filter()
            ->unique()
            ->values();
        if ($medicinesWithoutGeneric->isNotEmpty()) {
            $genericNames = MedicineInventory::query()
                ->whereIn('medicine_name', $medicinesWithoutGeneric)
                ->whereNotNull('generic_name')
                ->orderByDesc('id')
                ->get(['medicine_name', 'generic_name'])
                ->unique(fn ($medicine) => mb_strtolower(trim((string) $medicine->medicine_name)))
                ->keyBy(fn ($medicine) => mb_strtolower(trim((string) $medicine->medicine_name)));

            foreach ($medicines as $medicine) {
                if (trim((string) ($medicine->generic_name ?? '')) !== '') {
                    continue;
                }
                $genericName = $genericNames->get(mb_strtolower(trim((string) $medicine->medicine_name)))?->generic_name;
                if ($genericName) {
                    $medicine->generic_name = $genericName;
                }
            }
        }

        return [
            'websetting' => $websetting,
            'prescription' => $prescription,
            'prescriptionCode' => $prescriptionCode,
            'prescriptionDate' => $safeDate($prescription->created_at),
            'followUpDate' => $safeDate($prescription->follow_up_date),
            'patientName' => $safeText($prescription->patient_name),
            'patientAge' => $safeText($prescription->patient?->age),
            'patientGender' => $safeText($prescription->patient?->gender),
            'patientPhone' => $safeText($prescription->patient?->phone),
            'patientCode' => $patientCode,
            'patientBarcodeImage' => $patientBarcodeImage,
            'rxBarcodeImage' => $rxBarcodeImage,
            'doctorName' => $safeText($prescription->doctor_name),
            'doctorDegree' => $safeText($doctor?->details?->qualification),
            'doctorDesignation' => $safeText($doctorDesignation, 'Consultant'),
            'doctorSignatureUrl' => $prescription->doctor_signature_url
                ?? publicStorageUrl($prescriptionSettings?->doctor_signature_path),
            'doctorSealUrl' => $prescription->doctor_seal_url
                ?? publicStorageUrl($prescriptionSettings?->doctor_seal_path),
            'doctorPadUrl' => $prescriptionSettings?->prescription_header_url,
            'institutionPadUrl' => $prescriptionSettings?->institution_pad_url,
            'institutionPadContentOffset' => $prescriptionSettings?->institution_pad_content_offset ?? 0,
            'canCreate' => $this->actorCan('prescription-add') || $this->actorCan('prescription-list-create'),
            'nibp' => $prescription->nibp,
            'weight' => $prescription->weight,
            'pulse' => $prescription->pulse,
            'temperature' => $prescription->temperature,
            'complaints' => $prescription->complaints,
            'examinations' => $prescription->examinations,
            'diagnosis' => $prescription->diagnosis,
            'advice' => $prescription->advice,
            'medicines' => $medicines,
            'investigationItems' => $prescription->tests->pluck('test_name')->filter()->values()->all(),
            'showHeaderFooter' => true,
        ];
    }
}
