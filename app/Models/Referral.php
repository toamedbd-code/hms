<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'billing_id',
        'payee_id',
        'total_commission_amount',
        'paid_amount',
        'paid_status',
        'last_paid_at',
        'category_commissions',
        'date',
        'total_bill_amount',
        'status',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
        'total_commission_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'last_paid_at' => 'datetime',
        'total_bill_amount' => 'decimal:2',
        'category_commissions' => 'array',
    ];

    public function payee()
    {
        return $this->belongsTo(ReferralPerson::class, 'payee_id');
    }

    public function billing()
    {
        return $this->belongsTo(Billing::class, 'billing_id');
    }

    /**
     * Calculate commission based on bill items and referrer commission rates
     */
    // Add these methods to the Referral model

/**
 * Calculate commission based on bill items and referrer commission rates
 */
public function calculateCommission()
{
    $billing = $this->billing;
    $payee = $this->payee;
    
    if (!$billing || !$payee) {
        return [
            'total_commission' => 0,
            'category_breakdown' => [],
            'total_bill_amount' => 0
        ];
    }

    $billItems = $billing->billItems()->get();
    $categoryBreakdown = [];
    $totalCommission = 0;
    $totalBillAmount = 0;

    foreach ($billItems as $item) {
        $category = strtolower($item->category);
        $itemAmount = $item->net_amount;
        $totalBillAmount += $itemAmount;

        if (!isset($categoryBreakdown[$category])) {
            $categoryBreakdown[$category] = [
                'amount' => 0,
                'commission_rate' => 0,
                'commission_amount' => 0
            ];
        }

        $categoryBreakdown[$category]['amount'] += $itemAmount;
        $commissionRate = $this->getCommissionRateByCategory($payee, $category);
        $categoryBreakdown[$category]['commission_rate'] = $commissionRate;
        
        $itemCommission = ($itemAmount * $commissionRate) / 100;
        $categoryBreakdown[$category]['commission_amount'] += $itemCommission;
        $totalCommission += $itemCommission;
    }

    return [
        'total_commission' => round($totalCommission, 2),
        'category_breakdown' => $categoryBreakdown,
        'total_bill_amount' => round($totalBillAmount, 2)
    ];
}

private function getCommissionRateByCategory($payee, $category)
{
    switch ($category) {
        case 'pathology':
            return $payee->pathology_commission ?? 0;
        case 'radiology':
            return $payee->radiology_commission ?? 0;
        case 'medicine':
            return $payee->pharmacy_commission ?? 0;
        default:
            return $payee->standard_commission ?? 0;
    }
}

/**
 * Update commission calculation
 */
public function updateCommission()
{
    $commissionData = $this->calculateCommission();
    
    $this->update([
        'total_commission_amount' => $commissionData['total_commission'],
        'category_commissions' => $commissionData['category_breakdown'],
        'total_bill_amount' => $commissionData['total_bill_amount']
    ]);

    return $this;
}
}