<?php
namespace App\Services;
use App\Models\BedGroup;

class BedGroupService
{
    protected $bedgroupModel;

    public function __construct(BedGroup $bedgroupModel)
    {
        $this->bedgroupModel = $bedgroupModel;
    }

    public function list()
    {
        return  $this->bedgroupModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->bedgroupModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->bedgroupModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->bedgroupModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->bedgroupModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->bedgroupModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->bedgroupModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->bedgroupModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->bedgroupModel->with('floor')->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

