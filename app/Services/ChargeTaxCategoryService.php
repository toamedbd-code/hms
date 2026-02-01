<?php
namespace App\Services;
use App\Models\ChargeTaxCategory;

class ChargeTaxCategoryService
{
    protected $ChargeTaxCategoryModel;

    public function __construct(ChargeTaxCategory $chargetaxcategoryModel)
    {
        $this->chargetaxcategoryModel = $chargetaxcategoryModel;
    }

    public function list()
    {
        return  $this->chargetaxcategoryModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->chargetaxcategoryModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->chargetaxcategoryModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->chargetaxcategoryModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->chargetaxcategoryModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->chargetaxcategoryModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->chargetaxcategoryModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->chargetaxcategoryModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->chargetaxcategoryModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

