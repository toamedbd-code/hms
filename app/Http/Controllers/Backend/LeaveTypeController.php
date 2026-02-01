<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveTypeRequest;
use App\Models\LeaveType;
use App\Services\ApplyLeaveService;
use Illuminate\Support\Facades\DB;
use App\Services\LeaveTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class LeaveTypeController extends Controller
{
    use SystemTrait;

    protected $LeavetypeService, $ApplyLeaveService;

    public function __construct(LeaveTypeService $LeavetypeService, ApplyLeaveService $ApplyLeaveService)
    {
        $this->LeavetypeService = $LeavetypeService;
        $this->ApplyLeaveService = $ApplyLeaveService;

        $this->middleware('auth:admin');
        $this->middleware('permission:leave-type-list');
        $this->middleware('permission:applied-leave-list');
        $this->middleware('permission:leave-type-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:leave-type-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:leave-type-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:leave-type-list-status', ['only' => ['changeStatus']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/LeaveType/Index',
            [
                'pageTitle' => fn() => 'Leave Type List',
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
            ['fieldName' => 'type_name', 'class' => 'text-center'],
            ['fieldName' => 'days', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Type Name',
            'Days',
            'Status',
            'Action'
        ];
    }

    private function getDatas()
    {
        $query = $this->LeavetypeService->list();

        if (request()->filled('type_name'))
            $query->where('type_name', 'like', '%' . request()->type_name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->type_name = $data->type_name;
            $customData->days = $data->days;
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user->can('leave-type-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.Leavetype.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('leave-type-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.Leavetype.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('leave-type-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.Leavetype.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ];
            }

            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    public function create()
    {
        return Inertia::render(
            'Backend/LeaveType/Form',
            [
                'pageTitle' => fn() => 'Leave Type Create',
            ]
        );
    }


    public function store(LeaveTypeRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->LeavetypeService->create($data);

            if ($dataInfo) {
                $message = 'LeaveType created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'leave_types', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create LeaveType.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {

            DB::rollBack();
            $this->storeSystemError('Backend', 'LeaveTypeController', 'store', substr($err->getMessage(), 0, 1000));

            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";

            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function storeLeaveType(Request $request)
    {
        $validated = $request->validate([
            'type_name' => 'required|string|max:255',
            'days' => 'required|integer|min:1',
        ]);

        $leaveType = LeaveType::create([
            'type_name' => $validated['type_name'],
            'days' => $validated['days'],
        ]);

        return response()->json($leaveType, 201);
    }


    public function edit($id)
    {
        $Leavetype = $this->LeavetypeService->find($id);

        return Inertia::render(
            'Backend/LeaveType/Form',
            [
                'pageTitle' => fn() => 'LeaveType Edit',
                'Leavetype' => fn() => $Leavetype,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(LeaveTypeRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $LeaveType = $this->LeavetypeService->find($id);


            $dataInfo = $this->LeavetypeService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'LeaveType updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'leave_types', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update LeaveType.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'LeaveTypecontroller', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->LeavetypeService->delete($id)) {
                $message = 'LeaveType deleted successfully';
                $this->storeAdminWorkLog($id, 'leave_types', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete LeaveType.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'LeaveTypecontroller', 'destroy', substr($err->getMessage(), 0, 1000));
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
            $dataInfo = $this->LeavetypeService->changeStatus(request());

            if ($dataInfo->wasChanged()) {
                $message = 'LeaveType ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'leave_types', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . " LeaveType.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'LeaveTypeController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->withErrors(['error' => $message]);
        }
    }

    public function approvalRequest()
    {
        return Inertia::render(
            'Backend/LeaveType/Approval',
            [
                'pageTitle' => fn() => 'Approved Leave Request',
                'tableHeaders' => fn() => $this->getApprovalTableHeaders(),
                'dataFields' => fn() => $this->getApprovalDataFields(),
                'datas' => fn() => $this->getApprovalDatas(),
            ]
        );
    }

    private function getApprovalDataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'employee_name', 'class' => 'text-center'],
            ['fieldName' => 'type_name', 'class' => 'text-center'],
            ['fieldName' => 'from', 'class' => 'text-center'],
            ['fieldName' => 'to', 'class' => 'text-center'],
            ['fieldName' => 'apply_date', 'class' => 'text-center'],
            ['fieldName' => 'reason', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getApprovalTableHeaders()
    {
        return [
            'Sl/No',
            'Employee Name',
            'Leave Type Name',
            'From',
            'TO',
            'Apply Date',
            'Reason',
            'Status',
            'Action',
        ];
    }

    private function getApprovalDatas()
    {
        $query = $this->ApplyLeaveService->approveList();

        if (request()->filled('employee_name')) {
            $query->whereHas('employee', function ($q) {
                $q->where(function ($query) {
                    $query->where('first_name', 'like', '%' . request()->employee_name . '%')
                        ->orWhere('last_name', 'like', '%' . request()->employee_name . '%');
                });
            });
        }

        if (request()->filled('type_name')) {
            $query->whereHas('LeaveType', function ($item) {
                $item->where('type_name', 'like', '%' . request()->type_name . '%');
            });
        }

        if (request()->filled('apply_date'))
            $query->where('apply_date', 'like', '%' . request()->apply_date . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;

            $customData->employee_name = $data->employee->first_name . '' . $data->employee->last_name;
            $customData->type_name = $data->LeaveType->type_name;
            $customData->from = $data->from;
            $customData->to = $data->to;
            $customData->apply_date = $data->apply_date;
            $customData->reason = $data->reason;

            $customData->status = getStatusText($data->status);
            $customData->hasLink = true;
            $customData->links = [

                [
                    'linkClass' => 'bg-green-400 text-black semi-bold',
                    'link' => route('backend.approved.leave.confirm', $data->id),
                    'linkLabel' => getLinkLabel('Approve', null, null)
                ],
                [
                    'linkClass' => 'bg-red-400 text-black semi-bold',
                    'link' => route('backend.reject.leave', $data->id),
                    'linkLabel' => getLinkLabel('Reject', null, null)
                ]
            ];
            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    public function pendingRequest()
    {
        return Inertia::render(
            'Backend/LeaveType/Pending',
            [
                'pageTitle' => fn() => 'Pending Leave Request',
                'tableHeaders' => fn() => $this->getPendingTableHeaders(),
                'dataFields' => fn() => $this->getPendingDataFields(),
                'datas' => fn() => $this->getPendingDatas(),
            ]
        );
    }

    private function getPendingDataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'employee_name', 'class' => 'text-center'],
            ['fieldName' => 'type_name', 'class' => 'text-center'],
            ['fieldName' => 'from', 'class' => 'text-center'],
            ['fieldName' => 'to', 'class' => 'text-center'],
            ['fieldName' => 'apply_date', 'class' => 'text-center'],
            ['fieldName' => 'reason', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getPendingTableHeaders()
    {
        return [
            'Sl/No',
            'Employee Name',
            'Name',
            'From',
            'TO',
            'Apply Date',
            'Reason',
            'Status',
            'Action',
        ];
    }


    private function getPendingDatas()
    {
        $query = $this->ApplyLeaveService->pendingList();

        if (request()->filled('employee_name')) {
            $query->whereHas('employee', function ($q) {
                $q->where(function ($query) {
                    $query->where('first_name', 'like', '%' . request()->employee_name . '%')
                        ->orWhere('last_name', 'like', '%' . request()->employee_name . '%');
                });
            });
        }

        if (request()->filled('type_name'))
            $query->where('type_name', 'like', '%' . request()->type_name . '%');

        if (request()->filled('apply_date'))
            $query->where('apply_date', 'like', '%' . request()->apply_date . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;

            $customData->employee_name = $data->employee->first_name . '' . $data->employee->last_name;
            $customData->type_name = $data->LeaveType->type_name;
            $customData->from = $data->from;
            $customData->to = $data->to;
            $customData->apply_date = $data->apply_date;
            $customData->reason = $data->reason;

            $customData->status = getStatusText($data->status);
            $customData->hasLink = true;
            $customData->links = [
                [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.approve.leave', $data->id),
                    'linkLabel' => getLinkLabel('Approve', null, null)
                ],
                [
                    'linkClass' => 'bg-red-500 text-white semi-bold',
                    'link' => route('backend.reject.leave', $data->id),
                    'linkLabel' => getLinkLabel('Reject', null, null)
                ]
            ];
            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    public function applyList()
    {
        return Inertia::render(
            'Backend/LeaveType/Applylist',
            [
                'pageTitle' => fn() => 'All Leave List',
                'tableHeaders' => fn() => $this->getApplyTableHeaders(),
                'dataFields' => fn() => $this->getApplyDataFields(),
                'datas' => fn() => $this->getApplyListDatas(),
            ]
        );
    }

    private function getApplyDataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'employee_name', 'class' => 'text-center'],
            ['fieldName' => 'type_name', 'class' => 'text-center'],
            ['fieldName' => 'from', 'class' => 'text-center'],
            ['fieldName' => 'to', 'class' => 'text-center'],
            ['fieldName' => 'apply_date', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getApplyTableHeaders()
    {
        return [
            'Sl/No',
            'Employee Name',
            'Type Name',
            'From',
            'To',
            'Apply Date',
            'Status',
            'Action'
        ];
    }

    private function getApplyListDatas()
    {
        $query = $this->ApplyLeaveService->applyList();

        if (request()->filled('leave_type')) {
            $query->whereHas('LeaveType', function ($item) {
                $item->where('type_name', 'like', '%' . request()->leave_type . '%');
            });
        }

        if (request()->filled('apply_date')) {
            $query->where('apply_date', 'like', '%' . request()->apply_date . '%');
        }

        if (request()->filled('status')) {
            $query->where('status', request()->status);
        }

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;

            $customData->employee_name = $data->employee->first_name . '' . $data->employee->last_name;
            $customData->type_name = $data->LeaveType->type_name;
            $customData->from = $data->from;
            $customData->to = $data->to;
            $customData->apply_date = $data->apply_date;


            $customData->status = getStatusText($data->status);
            $customData->hasLink = true;
            $customData->links = [

                [
                    'linkClass' => 'bg-green-400 text-black semi-bold',
                    'link' => route('backend.approve.leave', $data->id),
                    'linkLabel' => getLinkLabel('Approve', null, null)
                ],
                [
                    'linkClass' => 'bg-red-400 text-black semi-bold',
                    'link' => route('backend.reject.leave', $data->id),
                    'linkLabel' => getLinkLabel('Reject', null, null)
                ],
            ];
            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    public function approveLeave()
    {
        DB::beginTransaction();

        try {
            $dataInfo = $this->ApplyLeaveService->changeStatus(request());

            if ($dataInfo->wasChanged()) {
                $message = 'ApplyLeave' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'apply_leaves', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . " ApplyLeave.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'LeaveTypeController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->withErrors(['error' => $message]);
        }
    }

    public function confirmApproval()
    {
        DB::beginTransaction();

        try {
            $dataInfo = $this->ApplyLeaveService->approvedLeave(request());

            if ($dataInfo->wasChanged()) {
                $message = 'ApplyLeave Confirm' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'apply_leaves', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . " ApplyLeave.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'LeaveTypeController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->withErrors(['error' => $message]);
        }
    }

    public function pendingLeave()
    {
        DB::beginTransaction();

        try {
            $dataInfo = $this->ApplyLeaveService->pendingLeaveStatusChange(request());

            if ($dataInfo->wasChanged()) {
                $message = 'Pending Leave' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'apply_leaves', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . " ApplyLeave.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'LeaveTypeController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->withErrors(['error' => $message]);
        }
    }

    public function rejectLeave()
    {

        DB::beginTransaction();

        try {
            $dataInfo = $this->ApplyLeaveService->rejectLeaveStatusChange(request());

            if ($dataInfo->wasChanged()) {
                $message = 'ApplyLeave' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'apply_leaves', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . " ApplyLeave.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'LeaveTypeController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->withErrors(['error' => $message]);
        }
    }

    public function leaveListView($id)
    {
        $leaveDetails = $this->ApplyLeaveService->find($id);
        return Inertia::render(
            'Backend/LeaveType/LeaveSingleView',
            [
                'pageTitle' => fn() => 'Approval Request',
                'tableHeaders' => fn() => $this->getApprovalTableHeaders(),
                'dataFields' => fn() => $this->getApprovalDataFields(),
                'datas' => fn() => $this->getApprovalDatas(),
                'leaveDetails' => fn() => $leaveDetails,
            ]
        );
    }
}
