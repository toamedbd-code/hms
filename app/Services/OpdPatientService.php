<?php

namespace App\Services;

use App\Models\ChargeType;
use App\Models\OpdPatient;

class OpdPatientService
{
    protected $opdpatientModel, $chargeTypeModel;

    public function __construct(OpdPatient $opdpatientModel, ChargeType $chargeTypeModel)
    {
        $this->opdpatientModel = $opdpatientModel;
        $this->chargeTypeModel = $chargeTypeModel;
    }

    public function list()
    {
        return  $this->opdpatientModel->with('patient', 'doctor')->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->opdpatientModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->opdpatientModel->with('chargeType', 'patient', 'doctor')->find($id);
    }

    public function create(array $data)
    {
        return  $this->opdpatientModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->opdpatientModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->opdpatientModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->opdpatientModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->opdpatientModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return  $this->opdpatientModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

    public function chargeTypes()
    {
        $chargeTypes = $this->chargeTypeModel->where('status', 'Active')->get();

                        $opdCharges = $chargeTypes->filter(function ($chargeType) {
            $rawModules = $chargeType->modules;

            // modules can be stored as JSON array (e.g. ["OPD","IPD"]) or as a single JSON string (e.g. "OPD")
            // Some installs also store it as CSV (e.g. OPD,IPD). Normalize all cases into an array.
            $modules = $rawModules;

            if (is_string($rawModules)) {
                $decoded = json_decode($rawModules, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $modules = $decoded;
                } else {
                    // fallback for non-JSON strings like: OPD,IPD
                    $modules = array_map('trim', explode(',', $rawModules));
                }
            }

            if (is_null($modules)) {
                $modules = [];
            } elseif (!is_array($modules)) {
                $modules = [$modules];
            }

            // ensure $modules is an array to avoid TypeError when stored value is malformed
            return in_array('OPD', (array) $modules, true);
        });

        return $opdCharges->values();
    }


}
