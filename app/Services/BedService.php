<?php
namespace App\Services;
use App\Models\Bed;

class BedService
{
    protected $bedModel;

    public function __construct(Bed $bedModel)
    {
        $this->bedModel = $bedModel;
    }

    public function list()
    {
        return  $this->bedModel->with('bedGroup', 'bedType')->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->bedModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->bedModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->bedModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->bedModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->bedModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->bedModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->bedModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->bedModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

