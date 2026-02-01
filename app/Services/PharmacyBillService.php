<?php
namespace App\Services;
use App\Models\PharmacyBill;

class PharmacyBillService
{
    protected $PharmacyBillModel;

    public function __construct(PharmacyBill $pharmacybillModel)
    {
        $this->pharmacybillModel = $pharmacybillModel;
    }

    public function list()
    {
        return  $this->pharmacybillModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->pharmacybillModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->pharmacybillModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->pharmacybillModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->pharmacybillModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->pharmacybillModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->pharmacybillModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->pharmacybillModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->pharmacybillModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

