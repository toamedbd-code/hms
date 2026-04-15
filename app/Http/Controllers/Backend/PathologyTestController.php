<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PathologyTestRequest;
use App\Services\ChargeCategoryService;
use App\Services\ChargeService;
use App\Services\ChargeTaxCategoryService;
use App\Services\ChargeTypeService;
use App\Services\TestCategoryService;
use App\Services\PathologyParameterService;
use App\Services\PathologyUnitService;
use App\Services\TestService;
use App\Models\Test;
use App\Models\TestCategory;
use App\Models\Charge;
use App\Models\PathologyUnit;
use App\Models\PathologyParameter;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class PathologyTestController extends Controller
{
    use SystemTrait;

    protected $testService, $testCategoryService, $testParameterService, $chargeService, $pathologyUnitService, $chargeTypeService, $chargeCategoryService, $chargeUnitService, $taxCategoryService;

    public function __construct(
        TestService $testService,
        TestCategoryService $testCategoryService,
        PathologyParameterService $testParameterService,
        ChargeService $chargeService,
        PathologyUnitService $pathologyUnitService,
        ChargeTypeService $chargeTypeService,
        ChargeCategoryService $chargeCategoryService,
        PathologyUnitService $chargeUnitService,
        ChargeTaxCategoryService $taxCategoryService,
    ) {
        $this->testService = $testService;
        $this->testCategoryService = $testCategoryService;
        $this->testParameterService = $testParameterService;
        $this->chargeService = $chargeService;
        $this->pathologyUnitService = $pathologyUnitService;
        $this->chargeTypeService = $chargeTypeService;
        $this->chargeCategoryService = $chargeCategoryService;
        $this->chargeUnitService = $chargeUnitService;
        $this->taxCategoryService = $taxCategoryService;

        $this->middleware('auth:admin');
        $this->middleware('permission:test-list');
        $this->middleware('permission:test-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:test-list-create', ['only' => ['importCsv']]);
        $this->middleware('permission:test-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:test-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:test-list-status', ['only' => ['changeStatus']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/PathologyTest/Index',
            [
                'pageTitle' => fn() => 'Test List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->get('q', ''));

        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $results = Test::query()
            ->where('status', 'Active')
            ->where('test_name', 'like', '%' . $query . '%')
            ->orderBy('test_name')
            ->limit(15)
            ->pluck('test_name')
            ->values();

        return response()->json(['results' => $results]);
    }

    public function downloadSampleCsv()
    {
        $content = "category_type,test_name,test_category,test_short_name,test_sub_category,method,report_days,tax,standard_charge,amount,reference_from,reference_to,unit\n"
            . "Pathology,Complete Blood Count,Hematology,CBC,,Automated,1,0,500,500,12.0,16.0,g/dL\n"
            . "Radiology,Chest X-Ray,Radiology,ChestXR,,X-Ray,0,0,800,800,,,\n";

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, 'test-sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ], [
            'csv_file.uploaded' => 'CSV upload failed. Check file size or server upload limits.',
            'csv_file.max' => 'CSV file must be 5 MB or smaller.',
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();

        if (!$path || !file_exists($path)) {
            return redirect()
                ->back()
                ->with('errorMessage', 'Invalid CSV file.');
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return redirect()
                ->back()
                ->with('errorMessage', 'Unable to read CSV file.');
        }

        DB::beginTransaction();
        try {
            $header = fgetcsv($handle);
            if (!$header) {
                fclose($handle);
                return redirect()
                    ->back()
                    ->with('errorMessage', 'CSV file is empty.');
            }

            $header = array_map(function ($value) {
                return strtolower(trim((string) $value));
            }, $header);

            $imported = 0;
            $skipped = 0;
            $duplicates = 0;

            while (($row = fgetcsv($handle)) !== false) {
                if (!array_filter($row, fn($cell) => trim((string) $cell) !== '')) {
                    continue;
                }

                $rowData = [];
                foreach ($header as $index => $key) {
                    $rowData[$key] = isset($row[$index]) ? trim((string) $row[$index]) : null;
                }

                $categoryType = strtolower((string)($rowData['category_type'] ?? ''));
                if (!in_array($categoryType, ['pathology', 'radiology'], true)) {
                    $skipped++;
                    continue;
                }

                $testName = $rowData['test_name'] ?? null;
                $categoryName = $rowData['test_category'] ?? $rowData['main_category'] ?? null;

                if (!$testName || !$categoryName) {
                    $skipped++;
                    continue;
                }

                $exists = Test::query()
                    ->where('test_name', $testName)
                    ->exists();

                if ($exists) {
                    $duplicates++;
                    continue;
                }

                $category = TestCategory::firstOrCreate([
                    'name' => $categoryName,
                ], [
                    'status' => 'Active',
                ]);

                $subCategoryId = null;
                $subCategoryName = $rowData['test_sub_category'] ?? $rowData['sub_category'] ?? null;
                if ($subCategoryName) {
                    $subCategory = TestCategory::firstOrCreate([
                        'name' => $subCategoryName,
                        'parent_id' => $category->id,
                    ], [
                        'status' => 'Active',
                    ]);
                    $subCategoryId = (string) $subCategory->id;
                }

                $shortName = $rowData['test_short_name'] ?? null;
                if (!$shortName) {
                    $words = preg_split('/\s+/', trim($testName));
                    $shortName = implode('', array_map(fn($w) => strtoupper(substr($w, 0, 1)), $words));
                }

                $testType = $rowData['test_type'] ?? $testName;

                $normalizeNullable = function ($value) {
                    $trimmed = trim((string) $value);
                    return $trimmed === '' ? null : $trimmed;
                };

                $normalizeNullableNumber = function ($value) {
                    $trimmed = trim((string) $value);
                    if ($trimmed === '' || !is_numeric($trimmed)) {
                        return null;
                    }

                    return $trimmed;
                };

                $reportDays = $normalizeNullableNumber($rowData['report_days'] ?? null);
                $normalRange = $normalizeNullable($rowData['normal_range'] ?? null);
                $chargeCategoryId = $normalizeNullableNumber($rowData['charge_category_id'] ?? null);
                $chargeName = $normalizeNullable($rowData['charge_name'] ?? null);
                $tax = $normalizeNullable($rowData['tax'] ?? null);
                $standardCharge = $normalizeNullableNumber($rowData['standard_charge'] ?? null);
                $amount = $normalizeNullableNumber($rowData['amount'] ?? null);

                // If upload only provides test_name, use it for charge_name by default.
                $chargeName = $chargeName ?? $testName;

                if ($chargeCategoryId === null && $chargeName !== null) {
                    $matchedCharge = Charge::query()
                        ->whereRaw('LOWER(name) = ?', [strtolower($chargeName)])
                        ->first();

                    if ($matchedCharge) {
                        $chargeCategoryId = $matchedCharge->id;
                        $tax = $tax ?? $matchedCharge->tax;
                        $standardCharge = $standardCharge ?? $matchedCharge->standard_charge;

                        if ($amount === null && $standardCharge !== null) {
                            $standardChargeFloat = (float) $standardCharge;
                            $taxFloat = (float) ($tax ?? 0);
                            $amount = (string) ($standardChargeFloat + (($standardChargeFloat * $taxFloat) / 100));
                        }
                    }
                }

                $splitList = function (?string $value) {
                    if ($value === null) {
                        return [];
                    }

                    $parts = array_map('trim', explode('|', $value));
                    return array_values(array_filter($parts, fn($part) => $part !== ''));
                };

                $parameterNames = $splitList($normalizeNullable($rowData['parameter_name'] ?? $rowData['test_parameter_name'] ?? null));
                $referenceFroms = $splitList($normalizeNullable($rowData['reference_from'] ?? $rowData['referance_from'] ?? null));
                $referenceTos = $splitList($normalizeNullable($rowData['reference_to'] ?? $rowData['referance_to'] ?? null));
                $unitNames = $splitList($normalizeNullable($rowData['unit'] ?? $rowData['unit_name'] ?? null));

                // If parameter_name is missing, keep it same as test_name.
                if (empty($parameterNames)) {
                    $parameterNames = [$testName];
                }

                $maxParameterRows = max(count($parameterNames), count($referenceFroms), count($referenceTos), count($unitNames));
                $parameterPayloads = [];

                for ($index = 0; $index < $maxParameterRows; $index++) {
                    $parameterName = $parameterNames[$index] ?? ($maxParameterRows === 1 ? ($parameterNames[0] ?? null) : null);
                    $referenceFrom = $referenceFroms[$index] ?? ($maxParameterRows === 1 ? ($referenceFroms[0] ?? null) : null);
                    $referenceTo = $referenceTos[$index] ?? ($maxParameterRows === 1 ? ($referenceTos[0] ?? null) : null);
                    $unitName = $unitNames[$index] ?? ($maxParameterRows === 1 ? ($unitNames[0] ?? null) : null);

                    if ($parameterName === null && $referenceFrom === null && $referenceTo === null && $unitName === null) {
                        continue;
                    }

                    $unitId = null;
                    if ($unitName !== null) {
                        $unit = PathologyUnit::query()->firstOrCreate([
                            'name' => $unitName,
                        ]);

                        $unitId = $unit->id;
                    }

                    $testParameterId = null;
                    if ($parameterName !== null) {
                        $matchedParameter = PathologyParameter::query()
                            ->whereRaw('LOWER(name) = ?', [strtolower($parameterName)])
                            ->first();

                        if (!$matchedParameter) {
                            if ($unitId === null) {
                                $fallbackUnit = PathologyUnit::query()->firstOrCreate([
                                    'name' => 'N/A',
                                ]);
                                $unitId = $fallbackUnit->id;
                            }

                            $matchedParameter = PathologyParameter::query()->create([
                                'name' => $parameterName,
                                'referance_from' => $referenceFrom ?? '',
                                'referance_to' => $referenceTo ?? '',
                                'pathology_unit_id' => $unitId,
                                'description' => null,
                                'status' => 'Active',
                            ]);
                        }

                        $testParameterId = $matchedParameter->id;
                        $parameterName = $matchedParameter->name;
                        $unitId = $unitId ?? $matchedParameter->pathology_unit_id;
                        $referenceFrom = $referenceFrom ?? $matchedParameter->referance_from;
                        $referenceTo = $referenceTo ?? $matchedParameter->referance_to;
                    }

                    $parameterPayloads[] = [
                        'test_parameter_id' => $testParameterId,
                        'name' => $parameterName,
                        'reference_from' => $referenceFrom,
                        'reference_to' => $referenceTo,
                        'pathology_unit_id' => $unitId,
                    ];
                }

                if (empty($parameterPayloads) && $normalRange !== null) {
                    $parameterPayloads[] = [
                        'test_parameter_id' => null,
                        'name' => 'Normal Range',
                        'reference_from' => $normalRange,
                        'reference_to' => null,
                        'pathology_unit_id' => null,
                    ];
                }

                $importedParameters = null;
                if (!empty($parameterPayloads)) {
                    $importedParameters = json_encode(array_map(function ($parameter) {
                        return [
                            'test_parameter_id' => $parameter['test_parameter_id'],
                            'name' => $parameter['name'],
                            'referance_from' => $parameter['reference_from'],
                            'referance_to' => $parameter['reference_to'],
                            'pathology_unit_id' => $parameter['pathology_unit_id'],
                        ];
                    }, $parameterPayloads));
                }

                $test = Test::create([
                    'category_type' => ucfirst($categoryType),
                    'test_name' => $testName,
                    'test_short_name' => $shortName,
                    'test_type' => $testType,
                    'test_category_id' => $category->id,
                    'test_sub_category_id' => $subCategoryId,
                    'method' => $rowData['method'] ?? null,
                    'report_days' => $reportDays,
                    'charge_category_id' => $chargeCategoryId,
                    'charge_name' => $chargeName,
                    'tax' => $tax,
                    'standard_charge' => $standardCharge,
                    'amount' => $amount,
                    'test_parameters' => $importedParameters,
                    'status' => 'Active',
                ]);

                foreach ($parameterPayloads as $parameterPayload) {
                    DB::table('pathology_test_parameters')->insert([
                        'pathology_test_id' => $test->id,
                        'test_parameter_id' => $parameterPayload['test_parameter_id'],
                        'name' => $parameterPayload['name'],
                        'reference_from' => $parameterPayload['reference_from'],
                        'reference_to' => $parameterPayload['reference_to'],
                        'pathology_unit_id' => $parameterPayload['pathology_unit_id'],
                    ]);
                }

                $imported++;
            }

            fclose($handle);

            $message = 'Tests imported: ' . $imported . '. Skipped: ' . $skipped . '. Duplicates: ' . $duplicates . '.';
            $this->storeAdminWorkLog(0, 'tests', $message);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message);
        } catch (Exception $err) {
            fclose($handle);
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyTestController', 'importCsv', substr($err->getMessage(), 0, 1000));
            DB::commit();

            return redirect()
                ->back()
                ->with('errorMessage', 'Server Errors Occur. Please Try Again.');
        }
    }

    private function getDatas()
    {
        $query = $this->testService->list();

        if (request()->filled('test_name'))
            $query->where('test_name', 'like', '%' . request()->test_name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->category_type = $data->category_type;
            $customData->test_name = $data->test_name;
            $customData->test_short_name = $data->test_short_name;
            $customData->test_type = $data->test_type;
            $customData->category = $data->pathologyCategory->name ?? 'N/A';
            $customData->amount = $data->amount ? '৳' . number_format($data->amount, 2) : 'N/A';
            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;
            $user = auth('admin')->user();
            $customData->links = [];

            if ($user->can('test-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.testpathology.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('test-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.testpathology.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('test-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.testpathology.destroy', $data->id),
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
            ['fieldName' => 'category_type', 'class' => 'text-left'],
            ['fieldName' => 'test_name', 'class' => 'text-left'],
            ['fieldName' => 'test_short_name', 'class' => 'text-center'],
            ['fieldName' => 'test_type', 'class' => 'text-center'],
            ['fieldName' => 'category', 'class' => 'text-center'],
            ['fieldName' => 'amount', 'class' => 'text-right'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Category',
            'Test Name',
            'Short Name',
            'Test Type',
            'Category',
            'Amount',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/PathologyTest/Form',
            [
                'pageTitle' => fn() => 'Test Create',
                'testCategories' => fn() => $this->testCategoryService->activeList(),
                'charges' => fn() => $this->chargeService->activeList(),
                'pathologyUnits' => fn() => $this->pathologyUnitService->activeList(),
                'testParameters' => fn() => $this->testParameterService->activeList(),
                'chargeTypes' => fn() => $this->chargeTypeService->activeList(),
                'chargeCategories' => fn() => $this->chargeCategoryService->activeList(),
                'chargeUnits' => fn() => $this->chargeUnitService->activeList(),
                'taxCategories' => fn() => $this->taxCategoryService->activeList()
            ]
        );
    }

    public function store(PathologyTestRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            $pathologyTestData = [
                'category_type' => $data['category_type'],
                'test_name' => $data['test_name'],
                'test_short_name' => $data['test_short_name'] ?? null,
                'test_type' => $data['test_type'] ?? null,
                'test_category_id' => $data['test_category_id'],
                'test_sub_category_id' => $data['test_sub_category_id'] ?? null,
                'method' => $data['method'] ?? null,
                'report_days' => $data['report_days'] ?? null,
                'charge_category_id' => $data['charge_id'] ?? $data['charge_category_id'] ?? null,
                'charge_name' => $data['charge_name'] ?? null,
                'tax' => $data['tax'] ?? null,
                'standard_charge' => $data['standard_charge'] ?? null,
                'amount' => $data['amount'] ?? null,
                'test_parameters' => json_encode($data['parameters'] ?? []),
                'status' => 'Active'
            ];

            $pathologyTestParameterData = [];

            $pathologyTest  = $this->testService->create($pathologyTestData);

            if ($pathologyTest) {

                if (isset($data['parameters']) && is_array($data['parameters'])) {
                    foreach ($data['parameters'] as $parameter) {
                        if (!empty($parameter['test_parameter_id']) || !empty($parameter['name'])) {

                            $pathologyTestParameterData = [
                                'pathology_test_id' => $pathologyTest->id,
                                'test_parameter_id' => $parameter['test_parameter_id'] ?? null,
                                'name' => $parameter['name'] ?? null,
                                'reference_from' => $parameter['referance_from'] ?? null,
                                'reference_to' => $parameter['referance_to'] ?? null,
                                'pathology_unit_id' => $parameter['pathology_unit_id'] ?? null,
                            ];

                            DB::table('pathology_test_parameters')->insert($pathologyTestParameterData);
                        }
                    }
                }
                $message = 'Test created successfully';
                $this->storeAdminWorkLog($pathologyTest->id, 'tests', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To create Test.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyTestController', 'store', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $pathologytest = $this->testService->find($id);

        $formattedTest = $pathologytest->toArray();
        $formattedTest['parameters'] = [];

        $testParameters = DB::table('pathology_test_parameters')
            ->where('pathology_test_id', $id)
            ->get();

        if ($testParameters && $testParameters->count() > 0) {
            foreach ($testParameters as $param) {
                $formattedTest['parameters'][] = [
                    'test_parameter_id' => $param->test_parameter_id,
                    'name' => $param->name,
                    'referance_from' => $param->reference_from,
                    'referance_to' => $param->reference_to,
                    'pathology_unit_id' => $param->pathology_unit_id,
                ];
            }
        } else {
            $jsonParameters = json_decode($pathologytest->test_parameters, true);
            if ($jsonParameters && is_array($jsonParameters)) {
                $formattedTest['parameters'] = $jsonParameters;
            } else {

                $formattedTest['parameters'] = [[
                    'test_parameter_id' => '',
                    'referance_from' => '',
                    'referance_to' => '',
                    'pathology_unit_id' => '',
                    'name' => ''
                ]];
            }
        }

        return Inertia::render(
            'Backend/PathologyTest/Form',
            [
                'pageTitle' => fn() => 'Test Edit',
                'pathologytest' => fn() => $formattedTest,
                'id' => fn() => $id,
                'testCategories' => fn() => $this->testCategoryService->activeList(),
                'charges' => fn() => $this->chargeService->activeList(),
                'pathologyUnits' => fn() => $this->pathologyUnitService->activeList(),
                'testParameters' => fn() => $this->testParameterService->activeList(),
                'chargeTypes' => fn() => $this->chargeTypeService->activeList(),
                'chargeCategories' => fn() => $this->chargeCategoryService->activeList(),
                'chargeUnits' => fn() => $this->chargeUnitService->activeList(),
                'taxCategories' => fn() => $this->taxCategoryService->activeList()
            ]
        );
    }

    public function update(PathologyTestRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            $pathologyTestData = [
                'test_name' => $data['test_name'],
                'test_short_name' => $data['test_short_name'] ?? null,
                'test_type' => $data['test_type'] ?? null,
                'test_category_id' => $data['test_category_id'],
                'test_sub_category_id' => $data['test_sub_category_id'] ?? null,
                'method' => $data['method'] ?? null,
                'report_days' => $data['report_days'] ?? null,
                'charge_category_id' => $data['charge_id'] ?? $data['charge_category_id'] ?? null,
                'charge_name' => $data['charge_name'] ?? null,
                'tax' => $data['tax'] ?? null,
                'standard_charge' => $data['standard_charge'] ?? null,
                'amount' => $data['amount'] ?? null,
                'test_parameters' => json_encode($data['parameters'] ?? []),
            ];

            $pathologyTest = $this->testService->update($pathologyTestData, $id);

            if ($pathologyTest) {

                DB::table('pathology_test_parameters')
                    ->where('pathology_test_id', $id)
                    ->delete();

                if (isset($data['parameters']) && is_array($data['parameters'])) {
                    foreach ($data['parameters'] as $parameter) {

                        if (!empty($parameter['test_parameter_id']) || !empty($parameter['name'])) {
                            $pathologyTestParameterData = [
                                'pathology_test_id' => $id,
                                'test_parameter_id' => $parameter['test_parameter_id'] ?? null,
                                'name' => $parameter['name'] ?? null,
                                'reference_from' => $parameter['referance_from'] ?? null,
                                'reference_to' => $parameter['referance_to'] ?? null,
                                'pathology_unit_id' => $parameter['pathology_unit_id'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            DB::table('pathology_test_parameters')->insert($pathologyTestParameterData);
                        }
                    }
                }

                $message = 'Test updated successfully';
                $this->storeAdminWorkLog($id, 'tests', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To update tests.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyTestController', 'update', substr($err->getMessage(), 0, 1000));
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
            if ($this->testService->delete($id)) {
                $message = 'Test deleted successfully';
                $this->storeAdminWorkLog($id, 'tests', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To Delete Test.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyTestController', 'destroy', substr($err->getMessage(), 0, 1000));
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
            $dataInfo = $this->testService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Test ' . $status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'tests', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . $status . " Test.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyTestController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
