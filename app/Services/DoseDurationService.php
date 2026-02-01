<?php

namespace App\Services;

use App\Models\DoseDuration;

class DoseDurationService
{
    protected $dosedurationModel;

    public function __construct(DoseDuration $dosedurationModel)
    {
        $this->dosedurationModel = $dosedurationModel;
    }

    public function list()
    {
        return  $this->dosedurationModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->dosedurationModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->dosedurationModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->dosedurationModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->dosedurationModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->dosedurationModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->dosedurationModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->dosedurationModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return  $this->dosedurationModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }
}
