<?php
namespace App\Services;
use App\Models\Finance;

class FinanceService
{
    protected $FinanceModel;

    public function __construct(Finance $financeModel)
    {
        $this->financeModel = $financeModel;
    }

    public function list()
    {
        return  $this->financeModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->financeModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->financeModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->financeModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->financeModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->financeModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->financeModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->financeModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->financeModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

