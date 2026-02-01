<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChargeTaxCategoryRequest;
use Illuminate\Support\Facades\DB;
use App\Services\ChargeTaxCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class ChargeTaxCategoryController extends Controller
{
    use SystemTrait;

    protected $chargetaxcategoryService;

    public function __construct(ChargeTaxCategoryService $chargetaxcategoryService)
    {
        $this->chargetaxcategoryService = $chargetaxcategoryService;

        $this->middleware('auth:admin');
        $this->middleware('permission:charge-tax-category-list');
        $this->middleware('permission:charge-tax-category-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:charge-tax-category-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:charge-tax-category-delete', ['only' => ['destroy']]);
        $this->middleware('permission:charge-tax-category-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/ChargeTaxCategory/Index',
            [
                'pageTitle' => fn() => 'Tax Category List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->chargetaxcategoryService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', '%' . request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->percentage = $data->percentage ?? '';

            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;
            $user = auth('admin')->user();

            $customData->links = [];

            if ($user->can('charge-tax-category-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.chargetaxcategory.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('charge-tax-category-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.chargetaxcategory.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('charge-tax-category-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.chargetaxcategory.destroy', $data->id),
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
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'percentage', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Name',
            'Percentage',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/ChargeTaxCategory/Form',
            [
                'pageTitle' => fn() => 'Tax Category Create'
            ]
        );
    }


    public function store(ChargeTaxCategoryRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->chargetaxcategoryService->create($data);

            if ($dataInfo) {
                $message = ' Tax Category created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'chargetaxcategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Tax Category.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeTaxCategoryController', 'store', substr($err->getMessage(), 0, 1000));
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
        $chargetaxcategory = $this->chargetaxcategoryService->find($id);

        return Inertia::render(
            'Backend/ChargeTaxCategory/Form',
            [
                'pageTitle' => fn() => 'Tax Category Edit',
                'chargetaxcategory' => fn() => $chargetaxcategory,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(ChargeTaxCategoryRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $chargetaxcategory = $this->chargetaxcategoryService->find($id);

            $dataInfo = $this->chargetaxcategoryService->update($data, $id);

            if ($dataInfo->save()) {
                $message = ' Tax Category updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'chargetaxcategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update tax categories.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeTaxCategoryController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->chargetaxcategoryService->delete($id)) {
                $message = ' Tax Category deleted successfully';
                $this->storeAdminWorkLog($id, 'chargetaxcategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Tax Category.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeTaxCategoryController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->chargetaxcategoryService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = ' Tax Category ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'chargetaxcategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . " Tax Category.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeTaxCategoryController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
