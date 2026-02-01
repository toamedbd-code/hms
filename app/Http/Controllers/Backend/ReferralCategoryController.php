<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReferralCategoryRequest;
use Illuminate\Support\Facades\DB;
use App\Services\ReferralCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class ReferralCategoryController extends Controller
{
    use SystemTrait;

    protected $referralcategoryService;

    public function __construct(ReferralCategoryService $referralcategoryService)
    {
        $this->referralcategoryService = $referralcategoryService;

        $this->middleware('auth:admin');
        $this->middleware('permission:referral-category-list');
        $this->middleware('permission:referral-category-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:referral-category-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:referral-category-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:referral-category-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/ReferralCategory/Index',
            [
                'pageTitle' => fn() => 'Referral Category List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->referralcategoryService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->status = getStatusText($data->status);
            
            $user = auth('admin')->user();

            $customData->hasLink = true;
            $customData->links = [];
            
            if ($user->can('referral-category-list-status-change')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.referralcategory.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('referral-category-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.referralcategory.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('referral-category-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.referralcategory.destroy', $data->id),
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
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Name',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/ReferralCategory/Form',
            [
                'pageTitle' => fn() => 'Referral Category Create',
            ]
        );
    }


    public function store(ReferralCategoryRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->referralcategoryService->create($data);

            if ($dataInfo) {
                $message = 'ReferralCategory created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'referralcategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create ReferralCategory.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralCategoryController', 'store', substr($err->getMessage(), 0, 1000));
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
        $referralcategory = $this->referralcategoryService->find($id);

        return Inertia::render(
            'Backend/ReferralCategory/Form',
            [
                'pageTitle' => fn() => 'ReferralCategory Edit',
                'referralcategory' => fn() => $referralcategory,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(ReferralCategoryRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $referralcategory = $this->referralcategoryService->find($id);

            $dataInfo = $this->referralcategoryService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'ReferralCategory updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'referralcategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update referralcategories.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralCategoryController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->referralcategoryService->delete($id)) {
                $message = 'ReferralCategory deleted successfully';
                $this->storeAdminWorkLog($id, 'referralcategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete ReferralCategory.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralCategoryController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->referralcategoryService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'ReferralCategory ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'referralcategories', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "ReferralCategory.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ReferralCategoryController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
