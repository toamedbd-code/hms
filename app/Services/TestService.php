<?php

namespace App\Services;
use App\Models\Test;

class TestService
{
    protected $pathologytestModel;

    public function __construct(Test $pathologytestModel)
    {
        $this->pathologytestModel = $pathologytestModel;
    }

    public function list()
    {
        return $this->pathologytestModel
            ->whereNull('tests.deleted_at')
            ->leftJoin('testcategories as tc', 'tests.test_category_id', '=', 'tc.id')
            ->select('tests.*', 'tc.name as category_name')
            ->orderByRaw('LOWER(COALESCE(tests.test_name, "")) ASC')
            ->orderByRaw('LOWER(COALESCE(tc.name, "")) ASC')
            ->orderBy('tests.id', 'asc');
    }

    public function all()
    {
        return $this->pathologytestModel
            ->whereNull('tests.deleted_at')
            ->leftJoin('testcategories as tc', 'tests.test_category_id', '=', 'tc.id')
            ->select('tests.*', 'tc.name as category_name')
            ->orderByRaw('LOWER(COALESCE(tests.test_name, "")) ASC')
            ->orderByRaw('LOWER(COALESCE(tc.name, "")) ASC')
            ->orderBy('tests.id', 'asc')
            ->get();
    }

    public function find($id)
    {
        return  $this->pathologytestModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->pathologytestModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->pathologytestModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->pathologytestModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->pathologytestModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->pathologytestModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return $this->pathologytestModel
            ->whereNull('tests.deleted_at')
            ->where('status', 'Active')
            ->leftJoin('testcategories as tc', 'tests.test_category_id', '=', 'tc.id')
            ->select('tests.*', 'tc.name as category_name')
            ->orderByRaw('LOWER(COALESCE(tests.test_name, "")) ASC')
            ->orderByRaw('LOWER(COALESCE(tc.name, "")) ASC')
            ->orderBy('tests.id', 'asc')
            ->get();
    }


}
