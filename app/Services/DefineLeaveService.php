<?php

namespace App\Services;

use App\Models\DefineLeave;

class DefineLeaveService
{
    protected $defineleaveModel;

    public function __construct(DefineLeave $defineleaveModel)
    {
        $this->defineleaveModel = $defineleaveModel;
    }

    public function list()
    {
        return $this->defineleaveModel->with('role','LeaveType')->whereNull('deleted_at');
    }

    public function all()
    {
        return $this->defineleaveModel->with('role','LeaveType')->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return $this->defineleaveModel->with('role','LeaveType')->find($id);
    }

    public function create(array $data)
    {
        return $this->defineleaveModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo = $this->defineleaveModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo = $this->defineleaveModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($request)
    {
        $dataInfo = $this->defineleaveModel->findOrFail($request->id);

        $dataInfo->update(['status' => $request->status]);

        return $dataInfo;
    }

    public function activeList()
    {
        return $this->defineleaveModel->with('role','LeaveType')->where('status', 'Active')->get();
    }

    public function get()
    {
        return $this->defineleaveModel->with('role')->where('status', 'Active')->get();
    }

}
