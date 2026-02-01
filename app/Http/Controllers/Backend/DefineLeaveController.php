<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\DefineLeaveRequest;
use App\Services\LeaveTypeService;
use Illuminate\Support\Facades\DB;
use App\Services\DefineLeaveService;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class DefineLeaveController extends Controller
{
    use SystemTrait;

    protected $defineleaveService, $LeaveTypeService, $roleService;

    public function __construct(DefineLeaveService $defineleaveService, LeaveTypeService $LeaveTypeService, RoleService $roleService)
    {
        $this->defineleaveService = $defineleaveService;
        $this->LeaveTypeService = $LeaveTypeService;
        $this->roleService = $roleService;

        $this->middleware('auth:admin');
        $this->middleware('permission:define-leave-list');
        $this->middleware('permission:define-leave-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:define-leave-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:define-leave-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:define-leave-list-status', ['only' => ['changeStatus']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/DefineLeave/Index',
            [
                'pageTitle' => fn() => 'Define Leave List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->getDataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'role', 'class' => 'text-center'],
            ['fieldName' => 'type', 'class' => 'text-center'],
            ['fieldName' => 'days', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Role',
            'Type',
            'Days',
            'Status',
            'Action'
        ];
    }

    private function getDatas()
    {
        $query = $this->defineleaveService->list();

        if (request()->filled('role'))
            $query->where('role', 'like', request()->role . '%');

        if (request()->filled('type')) {
            $query->join('leave_types.id', '=', 'define_leaves.type_id')
                ->where('leave_types.type_name', 'like', '%' . request()->type . '%');
        }

        if (request()->filled('days'))
            $query->where('days', 'like', request()->days . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->role = $data->role->name;
            $customData->type = $data->LeaveType->type_name;
            $customData->days = $data->days;
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user->can('define-leave-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.defineleave.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('define-leave-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.defineleave.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('define-leave-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.defineleave.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ];
            }

            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    public function create()
    {
        $leaveDetails = $this->LeaveTypeService->activeList();
        $roleDetails = $this->roleService->all();
        return Inertia::render(
            'Backend/DefineLeave/Form',
            [
                'pageTitle' => fn() => 'Define Leave Create',
                'leaveDetails' => $leaveDetails,
                'roleDetails' => $roleDetails,
            ]
        );
    }


    public function store(DefineLeaveRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->defineleaveService->create($data);

            if ($dataInfo) {
                $message = 'DefineLeave created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'define_leaves', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create DefineLeave.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {

            DB::rollBack();
            $this->storeSystemError('Backend', 'DefineLeaveController', 'store', substr($err->getMessage(), 0, 1000));

            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";

            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $defineleave = $this->defineleaveService->find($id);
        $roleDetails = $this->roleService->all();
        $leaveDetails = $this->LeaveTypeService->activeList();

        return Inertia::render(
            'Backend/DefineLeave/Form',
            [
                'pageTitle' => fn() => 'DefineLeave Edit',
                'defineleave' => fn() => $defineleave,
                'id' => fn() => $id,
                'roleDetails' => fn() => $roleDetails,
                'leaveDetails' => fn() => $leaveDetails,
            ]
        );
    }

    public function update(DefineLeaveRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $DefineLeave = $this->defineleaveService->find($id);


            $dataInfo = $this->defineleaveService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'DefineLeave updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'define_leaves', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update DefineLeave.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'DefineLeavecontroller', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->defineleaveService->delete($id)) {
                $message = 'DefineLeave deleted successfully';
                $this->storeAdminWorkLog($id, 'define_leaves', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete DefineLeave.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'DefineLeavecontroller', 'destroy', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function changeStatus()
    {
        DB::beginTransaction();

        try {
            $dataInfo = $this->defineleaveService->changeStatus(request());

            if ($dataInfo->wasChanged()) {
                $message = 'DefineLeave ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'define_leaves', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . " DefineLeave.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'DefineLeaveController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->withErrors(['error' => $message]);
        }
    }
}
