<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyLeaveRequest;
use App\Services\AdminService;
use App\Services\LeaveTypeService;
use Illuminate\Support\Facades\DB;
use App\Services\ApplyLeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class ApplyLeaveController extends Controller
{
    use SystemTrait;

    protected $applyleaveService, $LeaveTypeService, $AdminService;

    public function __construct(ApplyLeaveService $applyleaveService, LeaveTypeService $LeaveTypeService, AdminService $AdminService)
    {
        $this->applyleaveService = $applyleaveService;
        $this->LeaveTypeService = $LeaveTypeService;
        $this->AdminService = $AdminService;

        $this->middleware('auth:admin');
        $this->middleware('permission:apply-leave-list');
    }

    public function index()
    {
        return Inertia::render(
            'Backend/ApplyLeave/Index',
            [
                'pageTitle' => fn() => 'Apply Leave List',
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
            ['fieldName' => 'apply_date', 'class' => 'text-center'],
            ['fieldName' => 'employee', 'class' => 'text-center'],
            ['fieldName' => 'leave_type', 'class' => 'text-center'],
            ['fieldName' => 'from', 'class' => 'text-center'],
            ['fieldName' => 'to', 'class' => 'text-center'],
            ['fieldName' => 'reason', 'class' => 'text-center'],
            ['fieldName' => 'attachment', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Apply Date',
            'Employee Name',
            'Leave Type',
            'From',
            'To',
            'Reason',
            'Attachment',
            'Status',
            'Action'
        ];
    }

    private function getDatas()
    {
        $query = $this->applyleaveService->applyList();

        if (request()->filled('apply_date'))
            $query->where('apply_date', 'like', request()->apply_date . '%');

        if (request()->filled('leave_type')) {
            $query->whereHas('leaveType', function ($q) {
                $q->where('type_name', 'like', '%' . request()->leave_type . '%');
            });
        }

        if (request()->filled('employee_name')) {
            $query->whereHas('employee', function ($q) {
                $q->where(function ($query) {
                    $query->where('first_name', 'like', '%' . request()->employee_name . '%')
                        ->orWhere('last_name', 'like', '%' . request()->employee_name . '%');
                });
            });
        }


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;

            $customData->apply_date = $data->apply_date;
            $customData->employee = $data->employee->first_name . '' . $data->employee->last_name;
            $customData->leave_type = $data->LeaveType->type_name;
            $customData->from = $data->from;
            $customData->to = $data->to;
            $customData->reason = $data->reason;
            $customData->attachment = "<a href='" . $data->attachment . "' target= '_blank' class='inline-block px-4 py-1 bg-blue-500 text-white rounded-lg shadow-md hover:bg-blue-700'>View Doc</a>";


            $customData->status = getStatusText($data->status);
            $customData->hasLink = true;
            $customData->links = [
                // [
                //     'linkClass' => 'bg-yellow-400 text-black semi-bold',
                //     'link' => route('backend.pending.leave', $data->id),
                //     'linkLabel' => getLinkLabel('Pending', null, null)
                // ],
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

    public function create()
    {
        $leaveTypes = $this->LeaveTypeService->activeList();
        $employeeDetails = $this->AdminService->activeList();
        return Inertia::render(
            'Backend/ApplyLeave/Form',
            [
                'pageTitle' => fn() => 'Apply Leave Create',
                'leaveTypes' => $leaveTypes,
                'employeeDetails' => $employeeDetails,
            ]
        );
    }


    public function store(ApplyLeaveRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('attachment')) {
                $data['attachment'] = $this->fileUpload($request->attachment, 'applyLeaveAttachment');
            }

            $dataInfo = $this->applyleaveService->create($data);

            if ($dataInfo) {
                $message = 'ApplyLeave created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'apply_leaves', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create ApplyLeave.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {

            DB::rollBack();
            $this->storeSystemError('Backend', 'ApplyLeaveController', 'store', substr($err->getMessage(), 0, 1000));

            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";

            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $applyleave = $this->applyleaveService->find($id);
        $leaveTypes = $this->LeaveTypeService->activeList();
        $employeeDetails = $this->AdminService->activeList();

        return Inertia::render(
            'Backend/ApplyLeave/Form',
            [
                'pageTitle' => fn() => 'ApplyLeave Edit',
                'applyleave' => fn() => $applyleave,
                'leaveTypes' => fn() => $leaveTypes,
                'employeeDetails' => fn() => $employeeDetails,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(ApplyLeaveRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $ApplyLeave = $this->applyleaveService->find($id);

            if ($request->hasFile('attachment')) {
                $data['attachment'] = $this->fileUpload($request->attachment, 'applyLeaveAttachment');
            } else {
                $data['attachment'] = strstr($ApplyLeave->attachment ?? '', 'applyLeaveAttachment/');
            }

            $dataInfo = $this->applyleaveService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'ApplyLeave updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'apply_leaves', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update ApplyLeave.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ApplyLeavecontroller', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->applyleaveService->delete($id)) {
                $message = 'ApplyLeave deleted successfully';
                $this->storeAdminWorkLog($id, 'apply_leaves', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete ApplyLeave.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ApplyLeavecontroller', 'destroy', substr($err->getMessage(), 0, 1000));
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
            $dataInfo = $this->applyleaveService->changeStatus(request());

            if ($dataInfo->wasChanged()) {
                $message = 'ApplyLeave ' . request()->status . ' Successfully';
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
            $this->storeSystemError('Backend', 'ApplyLeaveController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->withErrors(['error' => $message]);
        }
    }
}
