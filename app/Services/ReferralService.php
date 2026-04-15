<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Referral;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

    public function recordCommissionPayment($id, $paymentType, $amount = null)
    {
        $referral = $this->referralModel->findOrFail($id);

        $totalCommission = (float) $referral->total_commission_amount;
        $currentPaid = (float) $referral->paid_amount;
        $pending = max(0, $totalCommission - $currentPaid);

        if ($pending <= 0) {
            return $referral;
        }

        if ($paymentType === 'paid') {
            $appliedAmount = $pending;
        } else {
            $amount = max(0, (float) $amount);
            if ($amount <= 0) {
                return $referral;
            }
            $appliedAmount = min($amount, $pending);
        }
        $newPaid = $currentPaid + $appliedAmount;

        if ($newPaid >= $totalCommission) {
            $paidStatus = 'Paid';
        } elseif ($newPaid > 0) {
            $paidStatus = 'Partial Paid';
        } else {
            $paidStatus = 'Unpaid';
        }

        $referral->update([
            'paid_amount' => $newPaid,
            'paid_status' => $paidStatus,
            'last_paid_at' => Carbon::now()
        ]);

        $this->updateCommissionExpense($referral);

        return $referral;
    }

    public function recordCommissionPaymentByPayee($payeeId, $paymentType, $amount = null)
    {
        $referrals = $this->referralModel
            ->where('payee_id', $payeeId)
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->orderBy('date')
            ->get();

        if ($referrals->isEmpty()) {
            return null;
        }

        $totalCommission = (float) $referrals->sum('total_commission_amount');
        $totalPaid = (float) $referrals->sum('paid_amount');
        $pending = max(0, $totalCommission - $totalPaid);

        if ($pending <= 0) {
            return $referrals;
        }

        if ($paymentType === 'paid') {
            $amountToApply = $pending;
        } else {
            $amount = max(0, (float) $amount);
            if ($amount <= 0) {
                return $referrals;
            }
            $amountToApply = min($amount, $pending);
        }

        foreach ($referrals as $referral) {
            if ($amountToApply <= 0) {
                break;
            }

            $refPending = max(0, (float) $referral->total_commission_amount - (float) $referral->paid_amount);
            if ($refPending <= 0) {
                continue;
            }

            $apply = min($amountToApply, $refPending);
            $newPaid = (float) $referral->paid_amount + $apply;

            if ($newPaid >= (float) $referral->total_commission_amount) {
                $paidStatus = 'Paid';
            } elseif ($newPaid > 0) {
                $paidStatus = 'Partial Paid';
            } else {
                $paidStatus = 'Unpaid';
            }

            $referral->update([
                'paid_amount' => $newPaid,
                'paid_status' => $paidStatus,
                'last_paid_at' => Carbon::now()
            ]);

            $this->updateCommissionExpense($referral);

            $amountToApply -= $apply;
        }

        return $referrals;
    }

    private function updateCommissionExpense(Referral $referral)
    {
        $commissionAmount = (float) $referral->paid_amount;
        if ($commissionAmount <= 0) {
            Log::info('Referral commission expense skipped (zero amount)', [
                'referral_id' => $referral->id,
                'bill_number' => $referral->billing->bill_number ?? null,
            ]);
            return;
        }

        $expenseHeader = ExpenseHead::firstOrCreate(
            ['name' => 'Commission'],
            ['status' => 'Active']
        );

        $expenseData = [
            'expense_header_id' => $expenseHeader->id,
            'bill_number' => $referral->billing->bill_number ?? null,
            'case_id' => null,
            'name' => $referral->payee->name ?? '',
            'description' => 'Referral commission payment (Ref #' . $referral->id . ')',
            'amount' => $commissionAmount,
            'date' => Carbon::now()->toDateString(),
            'status' => 'Active',
            'updated_by' => auth('admin')->user()->id ?? null,
            'created_by' => auth('admin')->user()->id ?? null,
        ];

        if (!empty($expenseData['bill_number'])) {
            $expense = Expense::updateOrCreate(
                ['bill_number' => $expenseData['bill_number']],
                $expenseData
            );

            Log::info('Referral commission expense upserted', [
                'referral_id' => $referral->id,
                'bill_number' => $expenseData['bill_number'],
                'amount' => $commissionAmount,
                'expense_id' => $expense->id,
            ]);
        } else {
            Log::warning('Referral commission expense skipped (missing bill_number)', [
                'referral_id' => $referral->id,
                'amount' => $commissionAmount,
            ]);
        }
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