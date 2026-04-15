<?php
namespace App\Services;
use App\Models\Pathology;

class PathologyService
{
    protected $pathologyModel;

    public function __construct(Pathology $pathologyModel)
    {
        $this->pathologyModel = $pathologyModel;
    }

    public function list()
    {
        return  $this->pathologyModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->pathologyModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->pathologyModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->pathologyModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->pathologyModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->pathologyModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->pathologyModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->pathologyModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->pathologyModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

    public function generatePathologyBillNumber()
    {
        $latest = $this->pathologyModel->withTrashed()
            ->orderBy('id', 'DESC')
            ->first();

        $prefix = web_setting_prefix('pathology_bill_prefix', 'Bill');
        $lastBillNo = (string) ($latest?->bill_no ?? '');
        $numberPart = str_replace($prefix, '', $lastBillNo);
        $number = is_numeric($numberPart) ? ((int) $numberPart + 1) : 1;

        return $prefix . $number;
    }

}

