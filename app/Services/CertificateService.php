<?php
namespace App\Services;
use App\Models\Certificate;

class CertificateService
{
    protected $CertificateModel;

    public function __construct(Certificate $certificateModel)
    {
        $this->certificateModel = $certificateModel;
    }

    public function list()
    {
        return  $this->certificateModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->certificateModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->certificateModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->certificateModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->certificateModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->certificateModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->certificateModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->certificateModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->certificateModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

