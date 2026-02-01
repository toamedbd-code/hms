<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChargeCategoryRequest;
use Illuminate\Support\Facades\DB;
use App\Services\ChargeCategoryService;
use App\Services\ChargeTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class ChargeCategoryController extends Controller
{
    use SystemTrait;

    protected $chargecategoryService, $chargeTypeService;

    public function __construct(ChargeCategoryService $chargecategoryService, ChargeTypeService $chargeTypeService)
    {
        $this->chargecategoryService = $chargecategoryService;
        $this->chargeTypeService = $chargeTypeService;

        $this->middleware('auth:admin');
        $this->middleware('permission:charge-category-list');
        $this->middleware('permission:charge-category-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:charge-category-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:charge-category-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:charge-category-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/ChargeCategory/Index',
            [
                'pageTitle' => fn() => 'Charge Category List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->chargecategoryService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->charge_type_id = $data?->chargeType?->name ?? '';
            $customData->name = $data->name;
            $customData->description = $data->description;

            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;
            $user = auth('admin')->user();
            
            $customData->links = [];

            if ($user->can('charge-category-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.chargecategory.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('charge-category-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.chargecategory.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('charge-category-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.chargecategory.destroy', $data->id),
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
            ['fieldName' => 'charge_type_id', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'description', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Charge Type',
            'Name',
            'Description',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/ChargeCategory/Form',
            [
                'pageTitle' => fn() => 'Charge Category Create',
                'chargeTypes' => fn() => $this->chargeTypeService->activeList()
            ]
        );
    }


    public function store(ChargeCategoryRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->chargecategoryService->create($data);

            if ($dataInfo) {
                $message = 'Charge Category created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'chargecategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Charge Category.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeCategoryController', 'store', substr($err->getMessage(), 0, 1000));
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
        $chargecategory = $this->chargecategoryService->find($id);

        return Inertia::render(
            'Backend/ChargeCategory/Form',
            [
                'pageTitle' => fn() => 'Charge Category Edit',
                'chargecategory' => fn() => $chargecategory,
                'id' => fn() => $id,
                 'chargeTypes' => fn() => $this->chargeTypeService->activeList()
            ]
        );
    }

    public function update(ChargeCategoryRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $chargecategory = $this->chargecategoryService->find($id);

            $dataInfo = $this->chargecategoryService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Charge Category updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'chargecategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update chargecategories.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeCategoryController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->chargecategoryService->delete($id)) {
                $message = 'Charge Category deleted successfully';
                $this->storeAdminWorkLog($id, 'chargecategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Charge Category.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeCategoryController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->chargecategoryService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Charge Category ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'chargecategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Charge Category.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeCategoryController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
