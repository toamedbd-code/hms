<?php
namespace App\Services;
use App\Models\ChargeType;

class ChargeTypeService
{
    protected $chargetypeModel;

    public function __construct(ChargeType $chargetypeModel)
    {
        $this->chargetypeModel = $chargetypeModel;
    }

    public function list()
    {
        return  $this->chargetypeModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->chargetypeModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->chargetypeModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->chargetypeModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->chargetypeModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->chargetypeModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->chargetypeModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->chargetypeModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->chargetypeModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

