<?php
namespace App\Services;
use App\Models\BirthDeathRecord;

class BirthDeathRecordService
{
    protected $BirthDeathRecordModel;

    public function __construct(BirthDeathRecord $birthdeathrecordModel)
    {
        $this->birthdeathrecordModel = $birthdeathrecordModel;
    }

    public function list()
    {
        return  $this->birthdeathrecordModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->birthdeathrecordModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->birthdeathrecordModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->birthdeathrecordModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->birthdeathrecordModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->birthdeathrecordModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->birthdeathrecordModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->birthdeathrecordModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->birthdeathrecordModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

