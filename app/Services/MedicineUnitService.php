<?php
namespace App\Services;
use App\Models\MedicineUnit;

class MedicineUnitService
{
    protected $MedicineUnitModel;

    public function __construct(MedicineUnit $medicineunitModel)
    {
        $this->medicineunitModel = $medicineunitModel;
    }

    public function list()
    {
        return  $this->medicineunitModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->medicineunitModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->medicineunitModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->medicineunitModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->medicineunitModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->medicineunitModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->medicineunitModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->medicineunitModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->medicineunitModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

