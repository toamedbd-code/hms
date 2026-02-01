<?php
namespace App\Services;
use App\Models\IpdPatient;

class IpdPatientService
{
    protected $ipdpatientModel;

    public function __construct(IpdPatient $ipdpatientModel)
    {
        $this->ipdpatientModel = $ipdpatientModel;
    }

    public function list()
    {
        return  $this->ipdpatientModel->with('patient', 'doctor', 'bed')->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->ipdpatientModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->ipdpatientModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->ipdpatientModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->ipdpatientModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->ipdpatientModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->ipdpatientModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->ipdpatientModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->ipdpatientModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

