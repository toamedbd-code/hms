<?php
namespace App\Services;
use App\Models\Setup;

class SetupService
{
    protected $SetupModel;

    public function __construct(Setup $setupModel)
    {
        $this->setupModel = $setupModel;
    }

    public function list()
    {
        return  $this->setupModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->setupModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->setupModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->setupModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->setupModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->setupModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->setupModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->setupModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->setupModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

