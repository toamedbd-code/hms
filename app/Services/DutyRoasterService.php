<?php
namespace App\Services;
use App\Models\DutyRoaster;

class DutyRoasterService
{
    protected $DutyRoasterModel;

    public function __construct(DutyRoaster $dutyroasterModel)
    {
        $this->dutyroasterModel = $dutyroasterModel;
    }

    public function list()
    {
        return  $this->dutyroasterModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->dutyroasterModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->dutyroasterModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->dutyroasterModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->dutyroasterModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->dutyroasterModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->dutyroasterModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->dutyroasterModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->dutyroasterModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

