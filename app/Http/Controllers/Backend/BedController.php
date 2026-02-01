<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BedRequest;
use App\Services\BedGroupService;
use Illuminate\Support\Facades\DB;
use App\Services\BedService;
use App\Services\BedTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class BedController extends Controller
{
    use SystemTrait;

    protected $bedService, $bedGroupService, $bedTypeService;

    public function __construct(BedService $bedService,BedGroupService $bedGroupService, BedTypeService $bedTypeService)
    {
        $this->bedService = $bedService;
        $this->bedGroupService = $bedGroupService;
        $this->bedTypeService = $bedTypeService;

        $this->middleware('auth:admin');
        $this->middleware('permission:bed-list');
        $this->middleware('permission:bed-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:bed-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:bed-delete', ['only' => ['destroy']]);
        $this->middleware('permission:bed-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/Bed/Index',
            [
                'pageTitle' => fn() => 'Bed List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->bedService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', '%' . request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->bed_type_id = $data?->bedType?->name ?? '';
            $customData->bed_group_id = $data?->bedGroup?->name ?? '';

            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;
            
            $user = auth('admin')->user();
            
            $customData->links = [];

            if ($user->can('bed-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.bed.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('bed-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.bed.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('bed-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.bed.destroy', $data->id),
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
            ['fieldName' => 'bed_type_id', 'class' => 'text-center'],
            ['fieldName' => 'bed_group_id', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Name',
            'Bed Type',
            'Bed Group',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/Bed/Form',
            [
                'pageTitle' => fn() => 'Bed Create',
                'bedTypes' => fn() => $this->bedTypeService->activeList(),
                'bedGroups' => fn() => $this->bedGroupService->activeList()
            ]
        );
    }


    public function store(BedRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->bedService->create($data);

            if ($dataInfo) {
                $message = 'Bed created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'beds', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Bed.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedController', 'store', substr($err->getMessage(), 0, 1000));
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
        $bed = $this->bedService->find($id);

        return Inertia::render(
            'Backend/Bed/Form',
            [
                'pageTitle' => fn() => 'Bed Edit',
                'bed' => fn() => $bed,
                'id' => fn() => $id,
                'bedTypes' => fn() => $this->bedTypeService->activeList(),
                'bedGroups' => fn() => $this->bedGroupService->activeList()
            ]
        );
    }

    public function update(BedRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $bed = $this->bedService->find($id);

            $dataInfo = $this->bedService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Bed updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'beds', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update beds.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->bedService->delete($id)) {
                $message = 'Bed deleted successfully';
                $this->storeAdminWorkLog($id, 'beds', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Bed.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->bedService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Bed ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'beds', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Bed.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
