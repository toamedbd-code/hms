<?php

namespace App\Services;

use App\Models\ApplyLeave;

class ApplyLeaveService
{
    protected $applyleaveModel;

    public function __construct(ApplyLeave $applyleaveModel)
    {
        $this->applyleaveModel = $applyleaveModel;
    }

    public function list()
    {
        return $this->applyleaveModel->with('employee', 'LeaveType')->whereNull('deleted_at');
    }

    public function all()
    {
        return $this->applyleaveModel->with('LeaveType')->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return $this->applyleaveModel->with('LeaveType')->find($id);
    }

    public function create(array $data)
    {
        return $this->applyleaveModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo = $this->applyleaveModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo = $this->applyleaveModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($request)
    {
        $dataInfo = $this->applyleaveModel->findOrFail($request->id);
        // dd($dataInfo);

        $dataInfo->update(['status' => 'Approved']);

        return $dataInfo;
    }

    public function approvedLeave($request)
    {
        $dataInfo = $this->applyleaveModel->findOrFail($request->id);

        $dataInfo->update(['status' => 'Approved']);

        return $dataInfo;
    }

    public function pendingLeaveStatusChange($request)
    {
        $dataInfo = $this->applyleaveModel->findOrFail($request->id);

        $dataInfo->update(['status' => 'Pending']);

        return $dataInfo;
    }

    public function rejectLeaveStatusChange($request)
    {
        $dataInfo = $this->applyleaveModel->findOrFail($request->id);

        $dataInfo->update(['status' => 'Rejected']);

        return $dataInfo;
    }

    public function activeList()
    {
        return $this->applyleaveModel->with('LeaveType')->whereNull('deleted_at')->where('status', 'Active')->get();
    }

    public function approveList()
    {
        return $this->applyleaveModel->with('LeaveType')->where('status','Approval Req')->orWhere('status', 'Approved')->whereNull('deleted_at');
    }

    public function pendingList()
    {
        return $this->applyleaveModel->with('LeaveType')->where('status','Pending')->whereNull('deleted_at');
    }

    public function applyList()
    {
        return $this->applyleaveModel->with('LeaveType')->whereNull('deleted_at');
    }

}
