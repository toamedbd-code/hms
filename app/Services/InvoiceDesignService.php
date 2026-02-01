<?php

namespace App\Services;

use App\Models\InvoiceDesign;

class InvoiceDesignService
{
    protected $invoicedesignModel;

    public function __construct(InvoiceDesign $invoicedesignModel)
    {
        $this->invoicedesignModel = $invoicedesignModel;
    }

    public function list()
    {
        return  $this->invoicedesignModel->whereNull('deleted_at')->orderBy('id', 'desc');
    }

    public function all()
    {
        return  $this->invoicedesignModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->invoicedesignModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->invoicedesignModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->invoicedesignModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->invoicedesignModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo = $this->invoicedesignModel->findOrFail($id);

        if ($dataInfo->status !== $status) {
            $dataInfo->status = $status;
            $dataInfo->save();
        }

        return $dataInfo;
    }


    public function activeList()
    {
        return  $this->invoicedesignModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

    public function deactivateAllExcept($id)
    {
        return $this->invoicedesignModel->where('id', '!=', $id)
            ->where('status', 'Active')
            ->update(['status' => 'Inactive']);
    }
}
