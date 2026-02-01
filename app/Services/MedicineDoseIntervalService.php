<?php

namespace App\Services;

use App\Models\MedicineDoseInterval;

class MedicineDoseIntervalService
{
    protected $medicinedoseintervalModel;

    public function __construct(MedicineDoseInterval $medicinedoseintervalModel)
    {
        $this->medicinedoseintervalModel = $medicinedoseintervalModel;
    }

    public function list()
    {
        return  $this->medicinedoseintervalModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->medicinedoseintervalModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->medicinedoseintervalModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->medicinedoseintervalModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->medicinedoseintervalModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->medicinedoseintervalModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->medicinedoseintervalModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->medicinedoseintervalModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return  $this->medicinedoseintervalModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }
}
