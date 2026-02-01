<?php

namespace App\Services;

use App\Models\MedicineGroup;

class MedicineGroupService
{
    protected $medicinegroupModel;

    public function __construct(MedicineGroup $medicinegroupModel)
    {
        $this->medicinegroupModel = $medicinegroupModel;
    }

    public function list()
    {
        return  $this->medicinegroupModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->medicinegroupModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->medicinegroupModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->medicinegroupModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->medicinegroupModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->medicinegroupModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->medicinegroupModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->medicinegroupModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return  $this->medicinegroupModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }
}
