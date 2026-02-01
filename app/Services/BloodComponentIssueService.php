<?php
namespace App\Services;
use App\Models\BloodComponentIssue;

class BloodComponentIssueService
{
    protected $BloodComponentIssueModel;

    public function __construct(BloodComponentIssue $bloodcomponentissueModel)
    {
        $this->bloodcomponentissueModel = $bloodcomponentissueModel;
    }

    public function list()
    {
        return  $this->bloodcomponentissueModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->bloodcomponentissueModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->bloodcomponentissueModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->bloodcomponentissueModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->bloodcomponentissueModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->bloodcomponentissueModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->bloodcomponentissueModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->bloodcomponentissueModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->bloodcomponentissueModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

