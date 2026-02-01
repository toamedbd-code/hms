<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PathologyCategoryRequest;
use Illuminate\Support\Facades\DB;
use App\Services\TestCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class PathologyCategoryController extends Controller
{
    use SystemTrait;

    protected $testCategoryService;

    public function __construct(TestCategoryService $testCategoryService)
    {
        $this->testCategoryService = $testCategoryService;

        $this->middleware('auth:admin');
        $this->middleware('permission:test-category-list');
        $this->middleware('permission:test-category-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:test-category-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:test-category-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:test-category-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/PathologyCategory/Index',
            [
                'pageTitle' => fn() => 'Test Category List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->testCategoryService->list();

        if (request()->filled('name')) {
            $search = request()->name;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('parent', function ($parent) use ($search) {
                        $parent->where('name', 'like', "%{$search}%");
                    });
            });
        }



        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->parent_id = $data?->parent?->name ?? '';
            $customData->name = $data->name;
            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;

            $user = auth('admin')->user();
            $customData->links = [];

            $customData->links = [];

            if ($user->can('test-category-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.pathologycategory.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('test-category-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.pathologycategory.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('test-category-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.pathologycategory.destroy', $data->id),
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
            ['fieldName' => 'parent_id', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Category',
            'Sub Category',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/PathologyCategory/Form',
            [
                'pageTitle' => fn() => 'Test Category Create',
                'categories' => fn() => $this->testCategoryService->activeList()
            ]
        );
    }


    public function store(PathologyCategoryRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->testCategoryService->create($data);

            if ($dataInfo) {
                $message = 'Test Category created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pathologycategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Test Category.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyCategoryController', 'store', substr($err->getMessage(), 0, 1000));
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
        $pathologycategory = $this->testCategoryService->find($id);

        return Inertia::render(
            'Backend/PathologyCategory/Form',
            [
                'pageTitle' => fn() => 'Test Category Edit',
                'pathologycategory' => fn() => $pathologycategory,
                'id' => fn() => $id,
                'categories' => fn() => $this->testCategoryService->activeList()
            ]
        );
    }

    public function update(PathologyCategoryRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $pathologycategory = $this->testCategoryService->find($id);

            $dataInfo = $this->testCategoryService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Test Category updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pathologycategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update pathology categories.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyCategoryController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->testCategoryService->delete($id)) {
                $message = 'Test Category deleted successfully';
                $this->storeAdminWorkLog($id, 'pathologycategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Test Category.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyCategoryController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->testCategoryService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Test Category ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pathologycategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Test Category.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyCategoryController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
