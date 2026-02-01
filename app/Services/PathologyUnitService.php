<?php
namespace App\Services;
use App\Models\PathologyUnit;

class PathologyUnitService
{
    protected $pathologyunitModel;

    public function __construct(PathologyUnit $pathologyunitModel)
    {
        $this->pathologyunitModel = $pathologyunitModel;
    }

    public function list()
    {
        return  $this->pathologyunitModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->pathologyunitModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->pathologyunitModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->pathologyunitModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->pathologyunitModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->pathologyunitModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->pathologyunitModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->pathologyunitModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->pathologyunitModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

