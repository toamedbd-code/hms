<?php
namespace App\Services;
use App\Models\Inventory;

class InventoryService
{
    protected $InventoryModel;

    public function __construct(Inventory $inventoryModel)
    {
        $this->inventoryModel = $inventoryModel;
    }

    public function list()
    {
        return  $this->inventoryModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->inventoryModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->inventoryModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->inventoryModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->inventoryModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->inventoryModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->inventoryModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->inventoryModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->inventoryModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

