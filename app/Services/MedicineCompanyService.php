<?php

namespace App\Services;

use App\Models\MedicineCompany;

class MedicineCompanyService
{
    protected $medicinecompanyModel;

    public function __construct(MedicineCompany $medicinecompanyModel)
    {
        $this->medicinecompanyModel = $medicinecompanyModel;
    }

    public function list()
    {
        return  $this->medicinecompanyModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->medicinecompanyModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->medicinecompanyModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->medicinecompanyModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->medicinecompanyModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->medicinecompanyModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->medicinecompanyModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->medicinecompanyModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return  $this->medicinecompanyModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }
}
