<?php
namespace App\Services;
use App\Models\Pharmacy;

class PharmacyService
{
    protected $PharmacyModel;

    public function __construct(Pharmacy $pharmacyModel)
    {
        $this->pharmacyModel = $pharmacyModel;
    }

    public function list()
    {
        return  $this->pharmacyModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->pharmacyModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->pharmacyModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->pharmacyModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->pharmacyModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->pharmacyModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->pharmacyModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->pharmacyModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->pharmacyModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

