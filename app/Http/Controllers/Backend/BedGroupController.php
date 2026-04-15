<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BedGroupRequest;
use Illuminate\Support\Facades\DB;
use App\Services\BedGroupService;
use App\Services\FloorService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class BedGroupController extends Controller
{
    use SystemTrait;

    protected $bedgroupService, $floorService;

    public function __construct(BedGroupService $bedgroupService, FloorService $floorService)
    {
        $this->bedgroupService = $bedgroupService;
        $this->floorService = $floorService;

        $this->middleware('auth:admin');
        $this->middleware('permission:bed-group-list');
        // PermissionSeeder generates permissions like: bed-group-list-create/edit/delete/status
        $this->middleware('permission:bed-group-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:bed-group-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:bed-group-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:bed-group-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/BedGroup/Index',
            [
                'pageTitle' => fn() => 'Bed Group List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->bedgroupService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->floor_id = $data?->floor?->name ?? '';

            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;
            $customData->links = [];
            $user = auth()->guard('admin')->user();

            if ($user->can('bed-group-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.bedgroup.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('bed-group-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.bedgroup.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('bed-group-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.bedgroup.destroy', $data->id),
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
            ['fieldName' => 'floor_id', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Name',
            'Floor',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/BedGroup/Form',
            [
                'pageTitle' => fn() => 'Bed Group Create',
                'floors' => fn() => $this->floorService->activeList()
            ]
        );
    }


    public function store(BedGroupRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->bedgroupService->create($data);

            if ($dataInfo) {
                $message = 'BedGroup created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bedgroups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create BedGroup.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedGroupController', 'store', substr($err->getMessage(), 0, 1000));
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
        $bedgroup = $this->bedgroupService->find($id);

        return Inertia::render(
            'Backend/BedGroup/Form',
            [
                'pageTitle' => fn() => 'Bed Group Edit',
                'bedgroup' => fn() => $bedgroup,
                'id' => fn() => $id,
                'floors' => fn() => $this->floorService->activeList()
            ]
        );
    }

    public function update(BedGroupRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $bedgroup = $this->bedgroupService->find($id);

            $dataInfo = $this->bedgroupService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'BedGroup updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bedgroups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update bedgroups.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedGroupController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->bedgroupService->delete($id)) {
                $message = 'BedGroup deleted successfully';
                $this->storeAdminWorkLog($id, 'bedgroups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete BedGroup.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedGroupController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->bedgroupService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'BedGroup ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bedgroups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "BedGroup.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedGroupController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
