<?php

namespace App\Services;

use App\Models\MedicineDosage;

class MedicineDosageService
{
    protected $medicinedosageModel;

    public function __construct(MedicineDosage $medicinedosageModel)
    {
        $this->medicinedosageModel = $medicinedosageModel;
    }

    public function list()
    {
        return  $this->medicinedosageModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->medicinedosageModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->medicinedosageModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->medicinedosageModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->medicinedosageModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->medicinedosageModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->medicinedosageModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->medicinedosageModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return  $this->medicinedosageModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }
}
