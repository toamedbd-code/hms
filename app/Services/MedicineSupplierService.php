<?php

namespace App\Services;

use App\Models\MedicineSupplier;

class MedicineSupplierService
{
    protected $medicinesupplierModel;

    public function __construct(MedicineSupplier $medicinesupplierModel)
    {
        $this->medicinesupplierModel = $medicinesupplierModel;
    }

    public function list()
    {
        return  $this->medicinesupplierModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->medicinesupplierModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->medicinesupplierModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->medicinesupplierModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->medicinesupplierModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->medicinesupplierModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->medicinesupplierModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->medicinesupplierModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return  $this->medicinesupplierModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }
}
