<?php

namespace App\Services;

use App\Models\MedicineCategory;

class MedicineCategoryService
{
    protected $medicinecategoryModel;

    public function __construct(MedicineCategory $medicinecategoryModel)
    {
        $this->medicinecategoryModel = $medicinecategoryModel;
    }

    public function list()
    {
        return  $this->medicinecategoryModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->medicinecategoryModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->medicinecategoryModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->medicinecategoryModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->medicinecategoryModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->medicinecategoryModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->medicinecategoryModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->medicinecategoryModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return  $this->medicinecategoryModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }
}
