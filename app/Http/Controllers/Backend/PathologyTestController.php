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
                'charge_category_id' => $data['charge_id'] ?? null,
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
                'charge_category_id' => $data['charge_id'] ?? null,
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
