<?php
namespace App\Services;
use App\Models\Ambulance;

class AmbulanceService
{
    protected $AmbulanceModel;

    public function __construct(Ambulance $ambulanceModel)
    {
        $this->ambulanceModel = $ambulanceModel;
    }

    public function list()
    {
        return  $this->ambulanceModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->ambulanceModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->ambulanceModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->ambulanceModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->ambulanceModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->ambulanceModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->ambulanceModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->ambulanceModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->ambulanceModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

