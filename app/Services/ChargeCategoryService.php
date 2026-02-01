<?php
namespace App\Services;
use App\Models\ChargeCategory;

class ChargeCategoryService
{
    protected $chargecategoryModel;

    public function __construct(ChargeCategory $chargecategoryModel)
    {
        $this->chargecategoryModel = $chargecategoryModel;
    }

    public function list()
    {
        return  $this->chargecategoryModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->chargecategoryModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->chargecategoryModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->chargecategoryModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->chargecategoryModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->chargecategoryModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->chargecategoryModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->chargecategoryModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->chargecategoryModel->with('charge')->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

