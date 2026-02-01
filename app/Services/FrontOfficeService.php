<?php
namespace App\Services;
use App\Models\FrontOffice;

class FrontOfficeService
{
    protected $FrontOfficeModel;

    public function __construct(FrontOffice $frontofficeModel)
    {
        $this->frontofficeModel = $frontofficeModel;
    }

    public function list()
    {
        return  $this->frontofficeModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->frontofficeModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->frontofficeModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->frontofficeModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->frontofficeModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->frontofficeModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->frontofficeModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->frontofficeModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->frontofficeModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

