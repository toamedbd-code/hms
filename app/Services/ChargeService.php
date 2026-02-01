<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Test;

class ChargeService
{
    protected $chargeModel;

    public function __construct(Charge $chargeModel)
    {
        $this->chargeModel = $chargeModel;
    }

    public function list()
    {
        return  $this->chargeModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->chargeModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->chargeModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->chargeModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->chargeModel->findOrFail($id);

        $tests = Test::where('charge_name', $data['name'])->get();

        $dataInfo->update($data);

        foreach ($tests as $test) {
            $standardCharge = $data['standard_charge'] ?? 0;
            $tax = $data['tax'] ?? 0;

            $amount = $tax > 0
                ? $standardCharge + ($standardCharge * $tax / 100)
                : $standardCharge;

            $test->update([
                'charge_name' => $data['name'],
                'tax' => $tax,
                'standard_charge' => $standardCharge,
                'amount' => $amount,
            ]);
        }

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->chargeModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->chargeModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->chargeModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return  $this->chargeModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }
}
