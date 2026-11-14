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
use App\Models\ChargeCategory;
use App\Models\PathologyUnit;
use App\Models\MainCategory;
use App\Models\PathologyParameter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use App\Support\DefaultDeveloperManager;
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
        $this->middleware('permission:itemcharge-list');
        $this->middleware('permission:itemcharge-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:itemcharge-list-create', ['only' => ['importCsv']]);
        $this->middleware('permission:itemcharge-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:itemcharge-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:itemcharge-list-status', ['only' => ['changeStatus']]);
    }

    public function index(Request $request)
    {
        $requestedPerPage = $request->get('numOfData', 10);
        $perPage = strtolower((string) $requestedPerPage) === 'all'
            ? 'all'
            : max(1, (int) $requestedPerPage);

        return Inertia::render(
            'Backend/PathologyTest/Index',
            [
                'pageTitle' => fn() => 'Item List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas($perPage),
                'filters' => fn() => [
                    'test_name' => $request->get('test_name', ''),
                    'numOfData' => $perPage,
                ],
                'isDeveloper' => fn() => DefaultDeveloperManager::isDeveloper(auth('admin')->user()),
            ]
        );
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->get('q', ''));

        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $tests = Test::query()
            ->where('status', 'Active')
            ->where('test_name', 'like', '%' . $query . '%')
            ->orderBy('test_name')
            ->limit(30)
            ->get([
                'id',
                'test_name',
                'category_type',
                'charge_name',
                'standard_charge',
                'amount',
                'tax',
                'charge_category_id',
            ]);

        $results = $tests->map(function ($t) {
            $charge = null;
            if (!empty($t->charge_name)) {
                $charge = Charge::query()->whereRaw('LOWER(name) = ?', [strtolower($t->charge_name)])->first();
            }

            return [
                'id' => $t->id,
                'test_name' => $t->test_name,
                'category_type' => $t->category_type,
                'charge_id' => $charge?->id ?? null,
                'charge_name' => $t->charge_name,
                'amount' => $t->amount !== null ? (float) $t->amount : (($t->standard_charge !== null) ? (float) $t->standard_charge : null),
                'standard_charge' => $t->standard_charge !== null ? (float) $t->standard_charge : null,
                'tax' => $t->tax !== null ? (float) $t->tax : 0,
                'charge_category_id' => $t->charge_category_id ?? null,
            ];
        })->values();

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

    public function exportCsv(Request $request)
    {
        abort_unless(DefaultDeveloperManager::isDeveloper($request->user('admin')), 403);

        $headers = [
            'category_type',
            'module',
            'test_name',
            'test_category',
            'main_category',
            'test_short_name',
            'test_sub_category',
            'test_type',
            'method',
            'report_days',
            'tax',
            'standard_charge',
            'amount',
            'charge_name',
            'charge_category_id',
            'referral_percentage',
            'status',
            'parameter_name',
            'reference_from',
            'reference_to',
            'unit',
        ];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $headers);

            Test::query()
                ->with(['pathologyCategory', 'mainCategory', 'testSubCategory', 'testParameters.pathologyUnit'])
                ->orderBy('id')
                ->chunk(500, function ($tests) use ($output) {
                    foreach ($tests as $test) {
                        $parameters = $test->testParameters;
                        $parameterValues = [
                            'parameter_name' => $parameters->pluck('name')->all(),
                            'reference_from' => $parameters->pluck('reference_from')->all(),
                            'reference_to' => $parameters->pluck('reference_to')->all(),
                            'unit' => $parameters->map(fn($parameter) => $parameter->pathologyUnit?->name)->all(),
                        ];

                        fputcsv($output, [
                            $test->category_type,
                            in_array(strtolower((string) $test->category_type), ['pathology', 'radiology'], true)
                                ? $test->category_type
                                : 'Pathology',
                            $test->test_name,
                            $test->pathologyCategory?->name,
                            $test->mainCategory?->name,
                            $test->test_short_name,
                            $test->testSubCategory?->name ?? $test->pathology_sub_category,
                            $test->test_type,
                            $test->method,
                            $test->report_days,
                            $test->tax,
                            $test->standard_charge,
                            $test->amount,
                            $test->charge_name,
                            $test->charge_category_id,
                            $test->referral_percentage,
                            $test->status,
                            implode('|', array_map(fn($value) => (string) ($value ?? ''), $parameterValues['parameter_name'])),
                            implode('|', array_map(fn($value) => (string) ($value ?? ''), $parameterValues['reference_from'])),
                            implode('|', array_map(fn($value) => (string) ($value ?? ''), $parameterValues['reference_to'])),
                            implode('|', array_map(fn($value) => (string) ($value ?? ''), $parameterValues['unit'])),
                        ]);
                    }
                });

            fclose($output);
        }, 'item-list.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
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

            $normalizeKeyPart = static function ($value): string {
                $value = preg_replace('/\s+/', ' ', trim((string) $value));
                return Str::lower($value ?? '');
            };

            // Build an in-memory key map once so repeated imports do not create duplicates.
            $existingRowKeys = Test::withTrashed()
                ->select(['category_type', 'test_name'])
                ->get()
                ->mapWithKeys(function ($test) use ($normalizeKeyPart) {
                    $key = $normalizeKeyPart($test->category_type) . '|' . $normalizeKeyPart($test->test_name);
                    return [$key => true];
                })
                ->all();

            $seenInCurrentCsv = [];

            while (($row = fgetcsv($handle)) !== false) {
                if (!array_filter($row, fn($cell) => trim((string) $cell) !== '')) {
                    continue;
                }

                $rowData = [];
                foreach ($header as $index => $key) {
                    $rowData[$key] = isset($row[$index]) ? trim((string) $row[$index]) : null;
                }

                $rawCategoryType = trim((string)($rowData['category_type'] ?? ''));
                $categoryTypeMain = MainCategory::query()
                    ->where('status', 'Active')
                    ->whereRaw('LOWER(name) = ?', [strtolower($rawCategoryType)])
                    ->first();
                $categoryType = strtolower($rawCategoryType);
                if (!in_array($categoryType, ['pathology', 'radiology'], true)) {
                    $module = strtolower(trim((string) ($rowData['module'] ?? '')));
                    $categoryType = $module !== '' ? $module : ($categoryTypeMain ? 'pathology' : '');
                }
                if (!in_array($categoryType, ['pathology', 'radiology'], true)) {
                    $skipped++;
                    continue;
                }

                $testName = $rowData['test_name'] ?? null;
                $categoryName = trim((string) ($rowData['test_category'] ?? ''));
                if ($categoryName === '') {
                    $categoryName = trim((string) ($rowData['main_category'] ?? $categoryTypeMain?->name ?? ''));
                }

                if (!$testName || !$categoryName) {
                    $skipped++;
                    continue;
                }

                $mainCategoryName = trim((string) ($rowData['main_category'] ?? ''));
                if ($mainCategoryName === '') {
                    $mainCategoryName = $categoryTypeMain && !in_array(strtolower($rawCategoryType), ['pathology', 'radiology'], true)
                        ? $categoryTypeMain->name
                        : $categoryName;
                }

                $mainCategory = MainCategory::query()
                    ->where('status', 'Active')
                    ->whereRaw('LOWER(name) = ?', [strtolower(trim($mainCategoryName))])
                    ->first();

                $normalizedCategoryType = ucfirst($categoryType);
                $rowKey = $normalizeKeyPart($normalizedCategoryType) . '|' . $normalizeKeyPart($testName);
                $sourceRowKey = $normalizeKeyPart($rawCategoryType) . '|' . $normalizeKeyPart($testName);

                if (isset($seenInCurrentCsv[$rowKey])) {
                    $duplicates++;
                    continue;
                }

                $seenInCurrentCsv[$rowKey] = true;

                if (isset($existingRowKeys[$rowKey]) || isset($existingRowKeys[$sourceRowKey])) {
                    $duplicates++;
                    continue;
                }

                $category = TestCategory::query()
                    ->whereRaw('LOWER(name) = ?', [strtolower(trim($categoryName))])
                    ->first();

                if (!$category) {
                    $category = TestCategory::create([
                        'name' => $categoryName,
                        'status' => 'Active',
                    ]);
                }

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
                $status = strtolower(trim((string) ($rowData['status'] ?? 'active')));
                $status = in_array($status, ['active', 'inactive'], true) ? ucfirst($status) : 'Active';

                // If upload only provides test_name, use it for charge_name by default.
                $chargeName = $chargeName ?? $testName;

                if ($chargeCategoryId === null && $chargeName !== null) {
                    $matchedCharge = Charge::query()
                        ->whereRaw('LOWER(name) = ?', [strtolower($chargeName)])
                        ->first();

                    if ($matchedCharge) {
                        // matchedCharge->id is the charge id; we need the charge's category id
                        $chargeCategoryId = $matchedCharge->charge_category_id ?? $chargeCategoryId;
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

                    $parts = array_map(static function ($part) {
                        $part = trim($part);
                        return $part === '' ? null : $part;
                    }, explode('|', $value));

                    return count(array_filter($parts, fn($part) => $part !== null)) === 0 ? [] : $parts;
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
                    'category_type' => $normalizedCategoryType,
                    'main_category_id' => $mainCategory?->id,
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
                    'referral_percentage' => $normalizeNullableNumber($rowData['referral_percentage'] ?? null),
                    'test_parameters' => $importedParameters,
                    'status' => $status,
                ]);

                $existingRowKeys[$rowKey] = true;

                // Ensure a corresponding Charge exists so this test appears in IPD hospital charges
                try {
                    $resolvedChargeName = trim((string)($chargeName ?? $testName));
                    if ($resolvedChargeName !== '') {
                        // ensure we have a valid charge_category_id (create one if needed)
                        $targetChargeCategoryId = $chargeCategoryId;
                        if (empty($targetChargeCategoryId)) {
                            $preferredName = $categoryType ? ucfirst($categoryType) : ($chargeName ?? $testName);
                            $foundCategory = ChargeCategory::query()->whereRaw('LOWER(name) = ?', [strtolower($preferredName)])->first();
                            if ($foundCategory) {
                                $targetChargeCategoryId = $foundCategory->id;
                            } else {
                                $chargeTypeId = \App\Models\ChargeType::query()->value('id') ?? 1;
                                $newCategory = ChargeCategory::create([
                                    'charge_type_id' => $chargeTypeId,
                                    'name' => $preferredName,
                                    'description' => $preferredName,
                                    'status' => 'Active',
                                ]);
                                $targetChargeCategoryId = $newCategory->id;
                            }
                        }

                        // include required foreign keys with sensible defaults
                        $chargeTypeId = \App\Models\ChargeType::query()->value('id') ?? 1;
                        $unitTypeId = \App\Models\ChargeUnitType::query()->value('id') ?? 1;
                        $taxCategoryId = \App\Models\ChargeTaxCategory::query()->value('id') ?? 1;

                        $chargePayload = [
                            'name' => $resolvedChargeName,
                            'charge_type_id' => $chargeTypeId,
                            'charge_category_id' => $targetChargeCategoryId,
                            'unit_type_id' => $unitTypeId,
                            'tax_category_id' => $taxCategoryId,
                            'tax' => $tax ?? null,
                            'standard_charge' => $standardCharge ?? null,
                            'status' => 'Active',
                        ];

                        // Only create or update Charge records for IPD search
                        // when the environment flag is enabled. Default: disabled.
                        if (env('AUTO_CREATE_IPD_CHARGE_FOR_TESTS', false)) {
                            $existingCharge = Charge::query()->whereRaw('LOWER(name) = ?', [strtolower($resolvedChargeName)])->first();
                            if ($existingCharge) {
                                $existingCharge->update($chargePayload);
                            } else {
                                Charge::create($chargePayload);
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->storeSystemError('Backend', 'PathologyTestController', 'importCsv_create_charge', substr($e->getMessage(), 0, 1000));
                }

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

            $message = 'Items imported: ' . $imported . '. Skipped: ' . $skipped . '. Duplicates: ' . $duplicates . '.';
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

    private function getDatas(int|string $perPage)
    {
        $query = $this->testService->list()->with(['pathologyCategory', 'mainCategory']);

        if (request()->filled('test_name')) {
            $query->where('tests.test_name', 'like', '%' . request()->test_name . '%');
        }

        $pageSize = $perPage === 'all'
            ? max(1, (clone $query)->count())
            : max(1, $perPage);
        $datas = $query->paginate($pageSize)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->category_type = $data->category_type;
            $customData->test_name = $data->test_name;
            $customData->test_short_name = $data->test_short_name;
            $customData->category = $data->mainCategory?->name ?? $data->pathologyCategory?->name ?? 'N/A';
            $customData->amount = $data->amount ? '৳' . number_format($data->amount, 2) : 'N/A';
            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;
            $user = auth('admin')->user();
            $customData->links = [];

            if ($user->can('itemcharge-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.itemcharge.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('itemcharge-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.itemcharge.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('itemcharge-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.itemcharge.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ];
            }

            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    private function calculateTotalAmount($standardCharge, $tax): ?float
    {
        $standard = is_numeric($standardCharge) ? (float) $standardCharge : 0.0;
        $taxValue = (float) preg_replace('/[^0-9.\-]/', '', (string) ($tax ?? ''));

        if ($standard <= 0) {
            return null;
        }

        return round($standard + (($standard * $taxValue) / 100), 2);
    }

    private function resolveItemDiscountPercentage(array $data): float
    {
        if ($this->isItemDiscountOff($data)) {
            return 0.0;
        }

        return min(100, max(0, (float) ($data['discount_percentage'] ?? 0)));
    }

    private function resolveItemDiscountRateLocked(array $data): bool
    {
        return $this->isItemDiscountOff($data);
    }

    private function isItemDiscountOff(array $data): bool
    {
        if (array_key_exists('discount_off', $data)) {
            return filter_var($data['discount_off'], FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) ($data['discount_rate_locked'] ?? false)
            && (float) ($data['discount_percentage'] ?? 0) <= 0;
    }

    private function bumpBillingTestsCache(): void
    {
        try {
            if (!Cache::has('billing:tests:cache_version')) {
                Cache::forever('billing:tests:cache_version', 2);

                return;
            }

            Cache::increment('billing:tests:cache_version');
        } catch (\Throwable $e) {
            // Billing will refresh from DB after the short cache TTL.
        }
    }

    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'category_type', 'class' => 'text-left'],
            ['fieldName' => 'test_name', 'class' => 'text-left'],
            ['fieldName' => 'test_short_name', 'class' => 'text-center'],
            ['fieldName' => 'category', 'class' => 'text-center'],
            ['fieldName' => 'amount', 'class' => 'text-right'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Category Type',
            'Item Name',
            'Short Name',
            'Main Category',
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
                'pageTitle' => fn() => 'Item Create',
                'testCategories' => fn() => $this->testCategoryService->activeList(),
                'categoryTypeOptions' => fn() => $this->testCategoryService->activeNames(),
                'mainCategories' => fn() => MainCategory::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
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
                'main_category_id' => $data['main_category_id'] ?? null,
                'test_name' => $data['test_name'],
                'report_function' => $this->resolveReportFunction($data['test_name']),
                'test_short_name' => $data['test_short_name'] ?? null,
                'test_type' => $data['test_type'] ?? null,
                'test_category_id' => $data['test_category_id'] ?? null,
                'test_sub_category_id' => $data['test_sub_category_id'] ?? null,
                'method' => $data['method'] ?? null,
                'room_no' => $data['room_no'] ?? null,
                'report_days' => $data['report_days'] ?? null,
                'charge_category_id' => $data['charge_id'] ?? $data['charge_category_id'] ?? null,
                'charge_name' => $data['charge_name'] ?? null,
                'tax' => $data['tax'] ?? null,
                'standard_charge' => $data['standard_charge'] ?? null,
                'amount' => $this->calculateTotalAmount($data['standard_charge'] ?? null, $data['tax'] ?? null),
                'referral_percentage' => $data['referral_percentage'] ?? null,
                'discount_percentage' => $this->resolveItemDiscountPercentage($data),
                'discount_rate_locked' => $this->resolveItemDiscountRateLocked($data),
                'test_parameters' => json_encode($data['parameters'] ?? []),
                'status' => 'Active'
            ];

            $pathologyTestParameterData = [];

            $pathologyTest  = $this->testService->create($pathologyTestData);

            if ($pathologyTest) {

                $manualParameters = array_filter($data['parameters'] ?? [], fn($p) => !empty($p['test_parameter_id']) || !empty($p['name']));

                if (!empty($manualParameters)) {
                    // Use manually submitted parameters
                    foreach ($manualParameters as $parameter) {
                        DB::table('pathology_test_parameters')->insert([
                            'pathology_test_id' => $pathologyTest->id,
                            'test_parameter_id' => $parameter['test_parameter_id'] ?? null,
                            'name' => $parameter['name'] ?? null,
                            'reference_from' => $parameter['referance_from'] ?? null,
                            'reference_to' => $parameter['referance_to'] ?? null,
                            'pathology_unit_id' => $parameter['pathology_unit_id'] ?? null,
                        ]);
                    }
                } else {
                    // Auto-add default parameters based on test name
                    $this->autoAddDefaultParameters($pathologyTest->id, $data['test_name'] ?? '');
                }
                // Create or update a Charge record so the test shows up in IPD charges search
                try {
                    $resolvedChargeName = trim((string)($pathologyTestData['charge_name'] ?? $pathologyTestData['test_name']));
                    if ($resolvedChargeName !== '') {
                        // Resolve or create a valid charge_category_id to satisfy DB constraints
                        $incomingCategoryId = $pathologyTestData['charge_category_id'] ?? ($data['charge_category_id'] ?? null);
                        $targetChargeCategoryId = $incomingCategoryId;
                        if (empty($targetChargeCategoryId)) {
                            $preferredName = $pathologyTestData['category_type'] ?? ($pathologyTestData['test_category_id'] ? null : ($pathologyTestData['test_name'] ?? 'Service'));
                            if (!$preferredName) $preferredName = 'Service';
                            $foundCategory = ChargeCategory::query()->whereRaw('LOWER(name) = ?', [strtolower($preferredName)])->first();
                            if ($foundCategory) {
                                $targetChargeCategoryId = $foundCategory->id;
                            } else {
                                $chargeTypeId = \App\Models\ChargeType::query()->value('id') ?? 1;
                                $newCategory = ChargeCategory::create([
                                    'charge_type_id' => $chargeTypeId,
                                    'name' => $preferredName,
                                    'description' => $preferredName,
                                    'status' => 'Active',
                                ]);
                                $targetChargeCategoryId = $newCategory->id;
                            }
                        }


                        $chargeTypeId = \App\Models\ChargeType::query()->value('id') ?? 1;
                        $unitTypeId = \App\Models\ChargeUnitType::query()->value('id') ?? 1;
                        $taxCategoryId = \App\Models\ChargeTaxCategory::query()->value('id') ?? 1;

                        $chargePayload = [
                            'name' => $resolvedChargeName,
                            'charge_type_id' => $chargeTypeId,
                            'charge_category_id' => $targetChargeCategoryId,
                            'unit_type_id' => $unitTypeId,
                            'tax_category_id' => $taxCategoryId,
                            'tax' => $pathologyTestData['tax'] ?? ($data['tax'] ?? null),
                            'standard_charge' => $pathologyTestData['standard_charge'] ?? ($data['standard_charge'] ?? null),
                            // determine module(s): prefer category_type, but if name
                            // indicates a disposable item, mark module as Disposable
                            'module' => json_encode((function() use ($pathologyTestData, $resolvedChargeName) {
                                $m = [];
                                $preferred = trim((string)($pathologyTestData['category_type'] ?? ''));
                                if ($preferred) $m[] = $preferred;
                                // disposable heuristics
                                if (preg_match('/\b(tube|v\.?\s*tube|syringe|needle|glove|gauze|dispos)\b/i', $resolvedChargeName)) {
                                    array_unshift($m, 'Disposable');
                                }
                                if (empty($m)) $m[] = 'Service';
                                return array_values(array_unique($m));
                            })()),
                            'status' => 'Active',
                        ];

                        if (env('AUTO_CREATE_IPD_CHARGE_FOR_TESTS', false)) {
                            $existingCharge = Charge::query()->whereRaw('LOWER(name) = ?', [strtolower($resolvedChargeName)])->first();
                            if ($existingCharge) {
                                $existingCharge->update($chargePayload);
                            } else {
                                Charge::create($chargePayload);
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->storeSystemError('Backend', 'PathologyTestController', 'store_create_charge', substr($e->getMessage(), 0, 1000));
                }
                $message = 'Item created successfully';
                $this->storeAdminWorkLog($pathologyTest->id, 'tests', $message);

                DB::commit();
                $this->bumpBillingTestsCache();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To create Item.";
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

    public function edit(Request $request, $id)
    {
        $pathologytest = $this->testService->find($id);

        $formattedTest = $pathologytest->toArray();
        $formattedTest['main_category_name'] = $pathologytest->mainCategory?->name
            ?? $pathologytest->pathologyCategory?->name
            ?? '';
        if (empty($formattedTest['main_category_id']) && $pathologytest->pathologyCategory?->name) {
            $formattedTest['main_category_id'] = MainCategory::query()
                ->where('status', 'Active')
                ->whereRaw('LOWER(name) = ?', [strtolower(trim($pathologytest->pathologyCategory->name))])
                ->value('id');
        }
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
                'pageTitle' => fn() => 'Item Edit',
                'pathologytest' => fn() => $formattedTest,
                'id' => fn() => $id,
                'testCategories' => fn() => $this->testCategoryService->activeList(),
                'categoryTypeOptions' => fn() => $this->testCategoryService->activeNames(),
                'mainCategories' => fn() => MainCategory::query()->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
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
                'category_type' => $data['category_type'] ?? null,
                'main_category_id' => $data['main_category_id'] ?? null,
                'test_name' => $data['test_name'],
                'report_function' => $this->resolveReportFunction($data['test_name']),
                'test_short_name' => $data['test_short_name'] ?? null,
                'test_type' => $data['test_type'] ?? null,
                'test_category_id' => $data['test_category_id'],
                'test_sub_category_id' => $data['test_sub_category_id'] ?? null,
                'method' => $data['method'] ?? null,
                'room_no' => $data['room_no'] ?? null,
                'report_days' => $data['report_days'] ?? null,
                'charge_category_id' => $data['charge_id'] ?? $data['charge_category_id'] ?? null,
                'charge_name' => $data['charge_name'] ?? null,
                'tax' => $data['tax'] ?? null,
                'standard_charge' => $data['standard_charge'] ?? null,
                'amount' => $this->calculateTotalAmount($data['standard_charge'] ?? null, $data['tax'] ?? null),
                'referral_percentage' => $data['referral_percentage'] ?? null,
                'discount_percentage' => $this->resolveItemDiscountPercentage($data),
                'discount_rate_locked' => $this->resolveItemDiscountRateLocked($data),
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

                // Ensure an associated Charge exists/updated for this item as well
                try {
                    $resolvedChargeName = trim((string)($pathologyTestData['charge_name'] ?? $pathologyTestData['test_name']));
                    if ($resolvedChargeName !== '') {
                        $incomingCategoryId = $pathologyTestData['charge_category_id'] ?? ($data['charge_category_id'] ?? null);
                        $targetChargeCategoryId = $incomingCategoryId;
                        if (empty($targetChargeCategoryId)) {
                            $preferredName = $pathologyTestData['category_type'] ?? ($pathologyTestData['test_category_id'] ? null : ($pathologyTestData['test_name'] ?? 'Service'));
                            if (!$preferredName) $preferredName = 'Service';
                            $foundCategory = ChargeCategory::query()->whereRaw('LOWER(name) = ?', [strtolower($preferredName)])->first();
                            if ($foundCategory) {
                                $targetChargeCategoryId = $foundCategory->id;
                            } else {
                                $chargeTypeId = \App\Models\ChargeType::query()->value('id') ?? 1;
                                $newCategory = ChargeCategory::create([
                                    'charge_type_id' => $chargeTypeId,
                                    'name' => $preferredName,
                                    'description' => $preferredName,
                                    'status' => 'Active',
                                ]);
                                $targetChargeCategoryId = $newCategory->id;
                            }
                        }

                        $chargeTypeId = \App\Models\ChargeType::query()->value('id') ?? 1;
                        $unitTypeId = \App\Models\ChargeUnitType::query()->value('id') ?? 1;
                        $taxCategoryId = \App\Models\ChargeTaxCategory::query()->value('id') ?? 1;

                        $moduleArr = [];
                        $preferred = trim((string)($pathologyTestData['category_type'] ?? ''));
                        if ($preferred) $moduleArr[] = $preferred;
                        if (preg_match('/\b(tube|v\.?\s*tube|syringe|needle|glove|gauze|dispos)\b/i', $resolvedChargeName)) {
                            array_unshift($moduleArr, 'Disposable');
                        }
                        if (empty($moduleArr)) $moduleArr[] = 'Service';

                        $chargePayload = [
                            'name' => $resolvedChargeName,
                            'charge_type_id' => $chargeTypeId,
                            'charge_category_id' => $targetChargeCategoryId,
                            'unit_type_id' => $unitTypeId,
                            'tax_category_id' => $taxCategoryId,
                            'tax' => $pathologyTestData['tax'] ?? ($data['tax'] ?? null),
                            'standard_charge' => $pathologyTestData['standard_charge'] ?? ($data['standard_charge'] ?? null),
                            'module' => json_encode(array_values(array_unique($moduleArr))),
                            'status' => 'Active',
                        ];

                        if (env('AUTO_CREATE_IPD_CHARGE_FOR_TESTS', false)) {
                            $existingCharge = Charge::query()->whereRaw('LOWER(name) = ?', [strtolower($resolvedChargeName)])->first();
                            if ($existingCharge) {
                                $existingCharge->update($chargePayload);
                            } else {
                                Charge::create($chargePayload);
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->storeSystemError('Backend', 'PathologyTestController', 'update_create_charge', substr($e->getMessage(), 0, 1000));
                }

                $message = 'Item updated successfully';
                $this->storeAdminWorkLog($id, 'tests', $message);

                DB::commit();
                $this->bumpBillingTestsCache();

                return redirect()
                    ->route('backend.itemcharge.index')
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To update items.";
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
                $message = 'Item deleted successfully';
                $this->storeAdminWorkLog($id, 'tests', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();
                $message = "Failed To Delete Item.";
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
                $message = 'Item ' . $status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'tests', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . $status . " Item.";
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

    /**
     * Resolve or create a PathologyUnit by name and return its ID.
     */
    private function resolveUnitId(string $unitName): ?int
    {
        $unitName = trim($unitName);
        if ($unitName === '') return null;

        $unit = PathologyUnit::query()->whereRaw('LOWER(name) = ?', [strtolower($unitName)])->first();
        if (!$unit) {
            $unit = PathologyUnit::create(['name' => $unitName]);
        }
        return $unit->id;
    }

    /**
     * Insert a single PathologyTestParameter row.
     */
    private function insertParam(int $testId, string $name, string $from, string $to, string $unit): void
    {
        DB::table('pathology_test_parameters')->insert([
            'pathology_test_id' => $testId,
            'test_parameter_id' => null,
            'name'              => $name,
            'reference_from'    => $from !== '' ? $from : null,
            'reference_to'      => $to   !== '' ? $to   : null,
            'pathology_unit_id' => $unit !== '' ? $this->resolveUnitId($unit) : null,
        ]);
    }

    /**
     * Auto-add default parameters for a new test based on its name.
     * Covers the most common laboratory tests used in Bangladesh clinical practice.
     */
    private function autoAddDefaultParameters(int $testId, string $testName): void
    {
        $n = strtolower(trim($testName));

        // ── CBC / Complete Blood Count ────────────────────────────────────────
        if (preg_match('/\bcbc\b|complete blood count|blood count/', $n)) {
            foreach ([
                ['Hemoglobin',           '12.0', '17.0', 'g/dL'],
                ['ESR',                  '0',    '20',   'mm/hr'],
                ['Total WBC Count',      '4000', '11000','/uL'],
                ['Neutrophils',          '40',   '70',   '%'],
                ['Lymphocytes',          '20',   '40',   '%'],
                ['Monocytes',            '2',    '8',    '%'],
                ['Eosinophils',          '1',    '6',    '%'],
                ['Basophils',            '0',    '1',    '%'],
                ['Total Cir. Eosinophils','0',   '440',  '/uL'],
                ['Total RBC Count',      '4.5',  '5.9',  'M/uL'],
                ['HCT/PCV',              '36',   '50',   '%'],
                ['MCV',                  '80',   '100',  'fL'],
                ['MCH',                  '27',   '33',   'pg'],
                ['MCHC',                 '31',   '37',   'g/dL'],
                ['RDW-CV',               '11.5', '14.5', '%'],
                ['RDW-SD',               '35',   '56',   'fL'],
                ['Platelet Count',       '150000','450000','/uL'],
                ['MPV',                  '7.5',  '12.5', 'fL'],
                ['PDW',                  '9.0',  '17.0', 'fL'],
                ['P-LCR',               '13',   '43',   '%'],
                ['PCT',                  '0.10', '0.28', '%'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Widal Test ────────────────────────────────────────────────────────
        if (preg_match('/widal/', $n)) {
            foreach ([
                ['S. Typhi O',       '', '1:20', 'Titre'],
                ['S. Typhi H',       '', '1:20', 'Titre'],
                ['S. Paratyphi AH',  '', '1:20', 'Titre'],
                ['S. Paratyphi BH',  '', '1:20', 'Titre'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Well-Felix / Weil-Felix (Serological) ────────────────────────────
        if (preg_match('/weil.?felix|well.?felix|serolog/i', $n)) {
            foreach ([
                ['OX K',  '', 'Up to 1:80', ''],
                ['OX 2',  '', 'Up to 1:80', ''],
                ['OX 19', '', 'Up to 1:80', ''],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Urine R/E or M/E ─────────────────────────────────────────────────
        if (preg_match('/urine|urinalysis|urin/', $n)) {
            foreach ([
                ['Color',            'Yellow',  '',        ''],
                ['Appearance',       'Clear',   '',        ''],
                ['Reaction (pH)',     '5.0',     '7.5',    ''],
                ['Specific Gravity', '1.005',   '1.030',  ''],
                ['Albumin',          'Nil',     '',        ''],
                ['Sugar',            'Nil',     '',        ''],
                ['Bile Salt',        'Nil',     '',        ''],
                ['Bile Pigment',     'Nil',     '',        ''],
                ['Pus Cells',        '0',       '5',      '/hpf'],
                ['RBC',              '0',       '2',      '/hpf'],
                ['Epithelial Cells', '0',       '5',      '/hpf'],
                ['Cast',             'Nil',     '',        ''],
                ['Bacteria',         'Nil',     '',        ''],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Stool R/E or M/E ─────────────────────────────────────────────────
        if (preg_match('/stool|faec|fec/', $n)) {
            foreach ([
                ['Consistency',   'Formed',    '',   ''],
                ['Color',         'Brown',     '',   ''],
                ['Mucus',         'Nil',       '',   ''],
                ['Pus Cells',     'Nil',       '',   ''],
                ['RBC',           'Nil',       '',   ''],
                ['Ova / Cysts',   'Not Found', '',   ''],
                ['Bacteria',      'Nil',       '',   ''],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── LFT / Liver Function Test ─────────────────────────────────────────
        if (preg_match('/\blft\b|liver function/', $n)) {
            foreach ([
                ['SGPT (ALT)',      '7',   '56',  'U/L'],
                ['SGOT (AST)',      '10',  '40',  'U/L'],
                ['ALP',             '44',  '147', 'U/L'],
                ['GGT',             '8',   '61',  'U/L'],
                ['Total Bilirubin', '0.2', '1.2', 'mg/dL'],
                ['Direct Bilirubin','0',   '0.3', 'mg/dL'],
                ['Indirect Bilirubin','0.1','0.8','mg/dL'],
                ['Total Protein',   '6.0', '8.3', 'g/dL'],
                ['Albumin',         '3.5', '5.0', 'g/dL'],
                ['Globulin',        '2.3', '3.5', 'g/dL'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── RFT / KFT / Renal/Kidney Function ───────────────────────────────
        if (preg_match('/\b(rft|kft)\b|renal function|kidney function/', $n)) {
            foreach ([
                ['Serum Creatinine', '0.6', '1.2', 'mg/dL'],
                ['Blood Urea',       '15',  '40',  'mg/dL'],
                ['Uric Acid',        '3.5', '7.2', 'mg/dL'],
                ['eGFR',             '60',  '',    'mL/min/1.73m²'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Lipid Profile ─────────────────────────────────────────────────────
        if (preg_match('/lipid profile|lipid panel/', $n)) {
            foreach ([
                ['Total Cholesterol', '0',  '200', 'mg/dL'],
                ['Triglycerides',     '0',  '150', 'mg/dL'],
                ['HDL Cholesterol',   '40', '',    'mg/dL'],
                ['LDL Cholesterol',   '0',  '100', 'mg/dL'],
                ['VLDL Cholesterol',  '0',  '30',  'mg/dL'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Thyroid Function Test (TFT) ───────────────────────────────────────
        if (preg_match('/\btft\b|thyroid function/', $n)) {
            foreach ([
                ['TSH',  '0.27', '4.20', 'μIU/mL'],
                ['T3',   '0.8',  '2.0',  'ng/mL'],
                ['T4',   '5.0',  '12.0', 'μg/dL'],
                ['FT3',  '2.3',  '4.2',  'pg/mL'],
                ['FT4',  '0.93', '1.70', 'ng/dL'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Electrolytes ──────────────────────────────────────────────────────
        if (preg_match('/electrolyte/', $n)) {
            foreach ([
                ['Sodium',      '135', '145', 'mEq/L'],
                ['Potassium',   '3.5', '5.0', 'mEq/L'],
                ['Chloride',    '98',  '106', 'mEq/L'],
                ['Bicarbonate', '22',  '29',  'mEq/L'],
                ['Calcium',     '8.5', '10.5','mg/dL'],
                ['Phosphorus',  '2.5', '4.5', 'mg/dL'],
                ['Magnesium',   '1.7', '2.2', 'mEq/L'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Coagulation Profile ───────────────────────────────────────────────
        if (preg_match('/coagulation|coagulation profile|pt\/aptt|prothrombin time/', $n)) {
            foreach ([
                ['Prothrombin Time (PT)', '11', '14', 'seconds'],
                ['APTT',                  '25', '35', 'seconds'],
                ['INR',                   '0.8','1.2',''],
                ['Bleeding Time',         '2',  '4',  'minutes'],
                ['Clotting Time',         '4',  '10', 'minutes'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Iron Studies ──────────────────────────────────────────────────────
        if (preg_match('/iron stud|iron profile|tibc/', $n)) {
            foreach ([
                ['Serum Iron',         '60',  '170', 'μg/dL'],
                ['TIBC',               '250', '370', 'μg/dL'],
                ['Transferrin Saturation','20','50', '%'],
                ['Serum Ferritin',     '12',  '300', 'ng/mL'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Cardiac Enzymes / Markers ─────────────────────────────────────────
        if (preg_match('/cardiac enzyme|cardiac marker|cardiac profile/', $n)) {
            foreach ([
                ['Troponin I', '0',   '0.04', 'ng/mL'],
                ['CK-MB',     '0',   '25',   'U/L'],
                ['CPK',       '22',  '198',  'U/L'],
                ['LDH',       '135', '225',  'U/L'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Hormone Profile / Fertility ───────────────────────────────────────
        if (preg_match('/hormone profile|fertility profile|reproductive/', $n)) {
            foreach ([
                ['FSH',          '1.5',  '12.4',  'mIU/mL'],
                ['LH',           '1.7',  '15.0',  'mIU/mL'],
                ['Prolactin',    '4.0',  '23.0',  'ng/mL'],
                ['Estradiol',    '30',   '400',   'pg/mL'],
                ['Progesterone', '0.3',  '1.2',   'ng/mL'],
                ['Testosterone', '300',  '1000',  'ng/dL'],
                ['AMH',          '1.0',  '3.5',   'ng/mL'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Semen Analysis ────────────────────────────────────────────────────
        if (preg_match('/semen|sperm|seminogram/', $n)) {
            foreach ([
                ['Volume',              '1.5',  '5.0',  'mL'],
                ['Color',               'Whitish-grey', '', ''],
                ['Liquefaction Time',   '15',   '30',   'minutes'],
                ['pH',                  '7.2',  '8.0',  ''],
                ['Sperm Count',         '15',   '',     'million/mL'],
                ['Total Motility',      '40',   '',     '%'],
                ['Progressive Motility','32',   '',     '%'],
                ['Normal Morphology',   '4',    '',     '%'],
                ['WBC',                 '0',    '1',    'million/mL'],
            ] as [$pName, $from, $to, $unit]) {
                $this->insertParam($testId, $pName, $from, $to, $unit);
            }
            return;
        }

        // ── Individual single-parameter tests (no group needed) ──────────────
        // These are handled by the normal range field; no auto-parameter is added.
        // e.g., SGPT, TSH, Creatinine, HbA1c, etc. (single value tests)
    }

    private function resolveReportFunction(?string $testName): string
    {
        $name = strtolower(trim((string) $testName));
        $rules = [
            'Hematology' => ['cbc', 'complete blood count', 'hematology', 'haematology', 'blood count'],
            'Lipid Profile' => ['lipid', 'cholesterol', 'triglyceride', 'dyslipid'],
            'Electrolytes' => ['electrolyte', 's.electrolyte', 'sodium', 'potassium', 'chloride', 'bi-carbonate', 'bicarbonate'],
            'Thyroid Function' => ['tsh', 'thyroid', 'thyroxine', 'free t3', 'free t4', 'ft3', 'ft4'],
            'Glucose' => ['rbs', 'fbs', 'blood sugar', 'glucose', 'hba1c', 'sugar'],
            'Liver Function' => ['lft', 'liver', 'sgpt', 'sgot', 'alt', 'ast', 'bilirubin', 'alkaline phosphatase', 'alp', 'ggt'],
            'Renal Function' => ['rft', 'renal', 'kidney', 'creatinine', 'urea', 'bun', 'uric acid'],
            'Cardiac Function' => ['troponin', 'cardiac', 'ck-mb', 'ckmb', 'myoglobin'],
            'Urinalysis' => ['urine', 'urinalysis', 'r/e', 'm/e'],
            'Coagulation' => ['pt', 'inr', 'aptt', 'bleeding time', 'clotting time', 'coagulation'],
            'Immunology' => ['ige', 'igg', 'igm', 'iga', 'antibody', 'antigen', 'hiv', 'hbsag', 'vdrl', 'crp'],
            'Fertility Function' => ['fsh', 'lh', 'prolactin', 'estrogen', 'progesterone', 'testosterone'],
        ];

        foreach ($rules as $function => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($name, $keyword)) {
                    return $function;
                }
            }
        }

        return 'Other Tests';
    }
}
