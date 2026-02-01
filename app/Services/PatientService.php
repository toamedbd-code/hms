<?php
namespace App\Services;
use App\Models\Patient;

class PatientService
{
    protected $patientModel;

    public function __construct(Patient $patientModel)
    {
        $this->patientModel = $patientModel;
    }

    public function list()
    {
        return  $this->patientModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->patientModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        $patientInfo = $this->patientModel->find($id);

        if($id) {
            return $patientInfo = $patientInfo;
        } else{
            return [];
        }
    }

    public function create(array $data)
    {
        return  $this->patientModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->patientModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->patientModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->patientModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->patientModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->patientModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

