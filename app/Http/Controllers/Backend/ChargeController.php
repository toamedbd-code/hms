<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChargeRequest;
use App\Services\ChargeCategoryService;
use Illuminate\Support\Facades\DB;
use App\Services\ChargeService;
use App\Services\ChargeTaxCategoryService;
use App\Services\ChargeTypeService;
use App\Services\ChargeUnitTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class ChargeController extends Controller
{
    use SystemTrait;

    protected $chargeService, $chargeTypeService, $chargeCategoryService, $chargeUnitService, $taxCategoryService;

    public function __construct(ChargeService $chargeService, ChargeTypeService $chargeTypeService, ChargeCategoryService $chargeCategoryService, ChargeUnitTypeService $chargeUnitService, ChargeTaxCategoryService $taxCategoryService)
    {
        $this->chargeService = $chargeService;
        $this->chargeTypeService = $chargeTypeService;
        $this->chargeCategoryService = $chargeCategoryService;
        $this->chargeUnitService = $chargeUnitService;
        $this->taxCategoryService = $taxCategoryService;


        $this->middleware('auth:admin');
        $this->middleware('permission:charge-list');
        $this->middleware('permission:charge-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:charge-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:charge-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:charge-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/Charge/Index',
            [
                'pageTitle' => fn() => 'Charge List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->chargeService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->charge_name = $data?->name ?? '';
            $customData->charge_type_id = $data?->chargeType?->name ?? '';
            $customData->charge_category_id = $data?->chargeCategory?->name ?? '';
            $customData->unit_type_id = $data?->chargeUnitType?->name ?? '';
            $customData->tax_category_id = $data?->chargeTaxCategory?->name ?? '';
            $customData->tax = $data->tax;
            $customData->standard_charge = $data->standard_charge;
            $customData->description = $data->description;

            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;
            $user = auth('admin')->user();
            
            $customData->links = [];

            if ($user->can('charge-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.hospitalcharge.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('charge-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.hospitalcharge.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('charge-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.hospitalcharge.destroy', $data->id),
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
            ['fieldName' => 'charge_name', 'class' => 'text-center'],
            ['fieldName' => 'charge_type_id', 'class' => 'text-center'],
            ['fieldName' => 'charge_category_id', 'class' => 'text-center'],
            ['fieldName' => 'unit_type_id', 'class' => 'text-center'],
            ['fieldName' => 'tax_category_id', 'class' => 'text-center'],
            ['fieldName' => 'tax', 'class' => 'text-center'],
            ['fieldName' => 'standard_charge', 'class' => 'text-center'],
            ['fieldName' => 'description', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Charge Name',
            'Charge Type',
            'Charge Category',
            'Unit Type',
            'Tax Category',
            'Tax',
            'Standard Charge',
            'Description',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/Charge/Form',
            [
                'pageTitle' => fn() => 'Charge Create',
                'chargeTypes' => fn() => $this->chargeTypeService->activeList(),
                'chargeCategories' => fn() => $this->chargeCategoryService->activeList(),
                'chargeUnits' => fn() => $this->chargeUnitService->activeList(),
                'taxCategories' => fn() => $this->taxCategoryService->activeList()
            ]
        );
    }


    public function store(ChargeRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->chargeService->create($data);

            if ($dataInfo) {
                $message = 'Charge created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'charges', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Charge.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeController', 'store', substr($err->getMessage(), 0, 1000));
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
        $charge = $this->chargeService->find($id);

        return Inertia::render(
            'Backend/Charge/Form',
            [
                'pageTitle' => fn() => 'Charge Edit',
                'charge' => fn() => $charge,
                'id' => fn() => $id,
                'chargeTypes' => fn() => $this->chargeTypeService->activeList(),
                'chargeCategories' => fn() => $this->chargeCategoryService->activeList(),
                'chargeUnits' => fn() => $this->chargeUnitService->activeList(),
                'taxCategories' => fn() => $this->taxCategoryService->activeList()
            ]
        );
    }

    public function update(ChargeRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $charge = $this->chargeService->find($id);

            $dataInfo = $this->chargeService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Charge updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'charges', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update charges.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->chargeService->delete($id)) {
                $message = 'Charge deleted successfully';
                $this->storeAdminWorkLog($id, 'charges', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Charge.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->chargeService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Charge ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'charges', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Charge.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
