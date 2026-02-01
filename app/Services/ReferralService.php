<?php

namespace App\Services;

use App\Models\Referral;

class ReferralService
{
    protected $referralModel;

    public function __construct(Referral $referralModel)
    {
        $this->referralModel = $referralModel;
    }

    public function list()
    {
        return $this->referralModel->whereNull('deleted_at')
            ->with(['payee:id,name,phone', 'billing:id,bill_number,invoice_number,patient_mobile']);
    }

    public function all()
    {
        return $this->referralModel->whereNull('deleted_at')->get();
    }

    public function find($id)
    {
        return $this->referralModel->with(['payee', 'billing.billItems'])->find($id);
    }

   public function create(array $data)
{
    // Create referral record
    $referral = $this->referralModel->create([
        'billing_id' => $data['billing_id'],
        'payee_id' => $data['payee_id'],
        'date' => $data['date'],
        'status' => $data['status'] ?? 'Active',
        'remarks' => $data['remarks'] ?? null,
    ]);

    // Calculate and update commission
    if ($referral) {
        $referral->updateCommission();
    }

    return $referral;
}

public function update(array $data, $id)
{
    $referral = $this->referralModel->findOrFail($id);

    $referral->update([
        'billing_id' => $data['billing_id'],
        'payee_id' => $data['payee_id'],
        'date' => $data['date'],
        'status' => $data['status'] ?? $referral->status,
        'remarks' => $data['remarks'] ?? $referral->remarks,
    ]);

    // Recalculate commission if billing or payee changed
    if ($referral->wasChanged(['billing_id', 'payee_id'])) {
        $referral->updateCommission();
    }

    return $referral;
}

    public function delete($id)
    {
        $referral = $this->referralModel->find($id);

        if (!empty($referral)) {
            $referral->deleted_at = date('Y-m-d H:i:s');
            $referral->status = 'Deleted';
            return $referral->save();
        }
        
        return false;
    }

    public function changeStatus($id, $status)
    {
        $referral = $this->referralModel->findOrFail($id);
        $referral->status = $status;
        $referral->update();

        return $referral;
    }

    public function activeList()
{
    return $this->referralModel->whereNull('deleted_at')
        ->where('status', 'Active');
}

    public function activeListCollection()
    {
        return $this->activeList()->get();
    }

    /**
     * Get commission breakdown for a referral
     */
    public function getCommissionBreakdown($id)
    {
        $referral = $this->find($id);
        
        if (!$referral) {
            return null;
        }

        return [
            'referral' => $referral,
            'payee' => $referral->payee,
            'billing' => $referral->billing,
            'total_commission' => $referral->total_commission_amount,
            'category_breakdown' => $referral->category_commissions,
            'total_bill_amount' => $referral->total_bill_amount,
        ];
    }

    /**
     * Check if a bill already has a referral for a specific payee
     */
    public function billingHasReferral($billingId, $payeeId, $excludeId = null)
    {
        $query = $this->referralModel->where('billing_id', $billingId)
            ->where('payee_id', $payeeId)
            ->whereNull('deleted_at');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}