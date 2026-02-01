<?php
namespace App\Services;
use App\Models\Report;
use App\Models\Reports;

class ReportsService
{
    protected $reportsModel;

    public function __construct(Report $reportsModel)
    {
        $this->reportsModel = $reportsModel;
    }

    public function list()
    {
        return  $this->reportsModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->reportsModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->reportsModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->reportsModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->reportsModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->reportsModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->reportsModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->reportsModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->reportsModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

