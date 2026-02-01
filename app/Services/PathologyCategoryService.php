<?php
namespace App\Services;
use App\Models\PathologyCategory;

class PathologyCategoryService
{
    protected $PathologyCategoryModel;

    public function __construct(PathologyCategory $pathologycategoryModel)
    {
        $this->pathologycategoryModel = $pathologycategoryModel;
    }

    public function list()
    {
        return  $this->pathologycategoryModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->pathologycategoryModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->pathologycategoryModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->pathologycategoryModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->pathologycategoryModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->pathologycategoryModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->pathologycategoryModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->pathologycategoryModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->pathologycategoryModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

