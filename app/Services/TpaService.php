<?php
namespace App\Services;
use App\Models\Tpa;

class TpaService
{
    protected $tpaModel;

    public function __construct(Tpa $tpaModel)
    {
        $this->tpaModel = $tpaModel;
    }

    public function list()
    {
        return  $this->tpaModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->tpaModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->tpaModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->tpaModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->tpaModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->tpaModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->tpaModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->tpaModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->tpaModel->whereNull('deleted_at');
    }

}

