<?php

namespace App\Services;

use App\Models\WebSetting;

class WebSettingService
{
    protected $websettingModel;

    public function __construct(WebSetting $websettingModel)
    {
        $this->websettingModel = $websettingModel;
    }

    public function list()
    {
        return $this->websettingModel->whereNull('deleted_at');
    }

    public function all()
    {
        return $this->websettingModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return $this->websettingModel->find($id);
    }

    public function first()
    {
        return $this->websettingModel->first();
    }

    public function create(array $data)
    {
        return $this->websettingModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo = $this->websettingModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo = $this->websettingModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($request)
    {
        $dataInfo = $this->websettingModel->findOrFail($request->id);

        $dataInfo->update(['status' => $request->status]);

        return $dataInfo;
    }

    public function activeList()
    {
        return $this->websettingModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}
