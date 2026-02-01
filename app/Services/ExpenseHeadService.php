<?php

namespace App\Services;

use App\Models\ExpenseHead;

class ExpenseHeadService
{
    protected $expenseheadModel;

    public function __construct(ExpenseHead $expenseheadModel)
    {
        $this->expenseheadModel = $expenseheadModel;
    }

    public function list()
    {
        return  $this->expenseheadModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->expenseheadModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->expenseheadModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->expenseheadModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->expenseheadModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->expenseheadModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->expenseheadModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->expenseheadModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return  $this->expenseheadModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }
}
