<?php
namespace App\Services;

use App\Models\IpdPatient;
use App\Models\Billing;


class IpdPatientService
{
    protected $ipdpatientModel;
    protected IpdDischargeBillingService $ipdDischargeBillingService;

    public function __construct(IpdPatient $ipdpatientModel, IpdDischargeBillingService $ipdDischargeBillingService)
    {
        $this->ipdpatientModel = $ipdpatientModel;
        $this->ipdDischargeBillingService = $ipdDischargeBillingService;
    }


    public function list()
    {
        return  $this->ipdpatientModel->with('patient', 'doctor', 'bed')->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->ipdpatientModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->ipdpatientModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->ipdpatientModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->ipdpatientModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->ipdpatientModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

                public function changeStatus($id, $status, ?int $actorId = null)
    {
        $dataInfo = $this->ipdpatientModel->findOrFail($id);

        $dataInfo->status = $status;

        // Track discharge datetime for certificate and reporting.
        if ($status === 'Inactive') {
            $dataInfo->discharged_at = now();

            // Discharge-time auto Billing + BillItem creation.
            // Only create once; next discharge toggles will keep the existing billing_id.
            if (empty($dataInfo->billing_id)) {
                $billing = $this->ipdDischargeBillingService->createOrGetForDischarge($dataInfo, $actorId);
                $dataInfo->billing_id = $billing->id;
            }
        }

        if ($status === 'Active') {
            $dataInfo->discharged_at = null;
        }

        $dataInfo->save();

        return $dataInfo;
    }

    public function regenerateDischargeBilling(int $ipdPatientId, ?int $actorId = null): Billing
    {
        $ipdpatient = $this->ipdpatientModel->findOrFail($ipdPatientId);

        if ($ipdpatient->status !== 'Inactive') {
            throw new \RuntimeException('Patient is not discharged yet, so discharge billing cannot be regenerated.');
        }

        $billing = $this->ipdDischargeBillingService->regenerateForDischarge($ipdpatient, $actorId);

        // Ensure link exists.
        if (empty($ipdpatient->billing_id)) {
            $ipdpatient->billing_id = $billing->id;
            $ipdpatient->save();
        }

        return $billing;
    }





    public function AdminExists($userName)
    {
        return  $this->ipdpatientModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->ipdpatientModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

