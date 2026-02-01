<?php

namespace App\Services;

use App\Models\MedicineInventory;

class MedicineInventoryService
{
    protected $medicineinventoryModel;

    public function __construct(MedicineInventory $medicineinventoryModel)
    {
        $this->medicineinventoryModel = $medicineinventoryModel;
    }

    public function list()
    {
        return  $this->medicineinventoryModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->medicineinventoryModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->medicineinventoryModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->medicineinventoryModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->medicineinventoryModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->medicineinventoryModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->medicineinventoryModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function activeList()
    {
        return  $this->medicineinventoryModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }
}
