<?php
namespace App\Services;
use App\Models\PathologyParameter;

class PathologyParameterService
{
    protected $PathologyParameterModel;

    public function __construct(PathologyParameter $pathologyparameterModel)
    {
        $this->pathologyparameterModel = $pathologyparameterModel;
    }

    public function list()
    {
        return  $this->pathologyparameterModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->pathologyparameterModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->pathologyparameterModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->pathologyparameterModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->pathologyparameterModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->pathologyparameterModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->pathologyparameterModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->pathologyparameterModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->pathologyparameterModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

