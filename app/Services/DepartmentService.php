<?php
namespace App\Services;
use App\Models\Department;

class DepartmentService
{
    protected $DepartmentModel;

    public function __construct(Department $departmentModel)
    {
        $this->departmentModel = $departmentModel;
    }

    public function list()
    {
        return  $this->departmentModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->departmentModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->departmentModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->departmentModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->departmentModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->departmentModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->departmentModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->departmentModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->departmentModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

