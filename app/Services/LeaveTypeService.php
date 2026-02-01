<?php

namespace App\Services;

use App\Models\LeaveType;

class LeaveTypeService
{
    protected $LeavetypeModel;

    public function __construct(LeaveType $LeavetypeModel)
    {
        $this->LeavetypeModel = $LeavetypeModel;
    }

    public function list()
    {
        return $this->LeavetypeModel->whereNull('deleted_at');
    }

    public function all()
    {
        return $this->LeavetypeModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return $this->LeavetypeModel->find($id);
    }

    public function create(array $data)
    {
        return $this->LeavetypeModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo = $this->LeavetypeModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo = $this->LeavetypeModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($request)
    {
        $dataInfo = $this->LeavetypeModel->findOrFail($request->id);

        $dataInfo->update(['status' => $request->status]);

        return $dataInfo;
    }

    public function activeList()
    {
        return $this->LeavetypeModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}
