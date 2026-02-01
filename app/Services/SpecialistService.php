<?php
namespace App\Services;
use App\Models\Specialist;

class SpecialistService
{
    protected $SpecialistModel;

    public function __construct(Specialist $specialistModel)
    {
        $this->specialistModel = $specialistModel;
    }

    public function list()
    {
        return  $this->specialistModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->specialistModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->specialistModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->specialistModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->specialistModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->specialistModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->specialistModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->specialistModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->specialistModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

