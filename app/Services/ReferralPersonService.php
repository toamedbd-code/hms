<?php
namespace App\Services;
use App\Models\ReferralPerson;

class ReferralPersonService
{
    protected $referralpersonModel;

    public function __construct(ReferralPerson $referralpersonModel)
    {
        $this->referralpersonModel = $referralpersonModel;
    }

    public function list()
    {
        return  $this->referralpersonModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->referralpersonModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->referralpersonModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->referralpersonModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->referralpersonModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->referralpersonModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->referralpersonModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function activeList()
    {
        return  $this->referralpersonModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

