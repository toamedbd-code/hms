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

    $billingCommission = (float) ($billing->commission_total ?? 0);
    if ($billingCommission > 0) {
        return [
            'total_commission' => round($billingCommission, 2),
            'category_breakdown' => $this->category_commissions ?? [],
            'total_bill_amount' => round((float) ($billing->payable_amount ?? $billing->total ?? 0), 2),
        ];
    }

    $billItems = $billing->billItems;
    $items = $billItems->map(function ($item) {
        $category = strtolower((string) ($item->category ?? ''));
        $itemName = $item->getRawOriginal('item_name') ?: 'N/A';
        $name = strtolower($itemName);

        if (preg_match('/\b(usg|ultrasound|ultrasonogram|ultrasonography|sonography|sono)\b/', $category . ' ' . $name) === 1) {
            $category = 'ultrasound';
        }

        return [
            'id' => $item->item_id,
            'name' => $itemName,
            'category' => $category,
            'net_amount' => $item->net_amount ?? $item->amount ?? 0,
            'total_amount' => $item->total_amount ?? $item->amount ?? 0,
            'commissionable' => $item->commissionable,
            'commission_rate' => $item->commission_rate !== null
                ? (float) $item->commission_rate
                : null,
        ];
    })->all();

    $result = app(\App\Services\ReferralCommissionCalculator::class)->calculate(
        $items,
        (float) ($billing->payable_amount ?? $billing->total ?? 0),
        function ($item) use ($payee) {
            $commissionable = $item['commissionable'];
            $rate = $item['commission_rate'] ?? $this->getCommissionRateByCategory($payee, $item['category'], $item['name'] ?? '');

            return [
                'category' => $item['category'],
                'rate' => in_array($commissionable, [false, 0, '0'], true) ? 0 : $rate,
            ];
        }
    );

    $categoryBreakdown = [];
    foreach ($result['categories'] as $category => $commission) {
        $categoryBreakdown[$category] = [
            'amount' => $commission['base_amount'],
            'commission_rate' => $commission['rate'],
            'commission_amount' => $commission['amount'],
        ];
    }

    return [
        'total_commission' => $result['total'],
        'category_breakdown' => $categoryBreakdown,
        'total_bill_amount' => round((float) ($billing->payable_amount ?? $billing->total ?? 0), 2)
    ];
}

private function getCommissionRateByCategory($payee, $category, $itemName = '')
{
    $normalized = strtolower(trim((string) ($category ?? '')));
    $itemNameLower = strtolower(trim((string) $itemName));
    $combined = trim($normalized . ' ' . $itemNameLower);

    if (preg_match('/\b(usg|ultrasound|ultrasonogram|ultrasonography|sonography|sono)\b/', $combined) === 1) {
        $normalized = 'ultrasound';
    }

    $fallbackRadiologyRate = (float) ($payee->radiology_commission ?? 0);
    $fallbackUltrasoundRate = (float) ($payee->ultrasound_commission ?? $fallbackRadiologyRate);

    switch ($normalized) {
        case 'pathology':
            return (float) ($payee->pathology_commission ?? 0);
        case 'radiology':
            return (float) ($payee->radiology_commission ?? 0);
        case 'ecg':
        case 'ekg':
            return (float) ($payee->ecg_commission ?? $fallbackRadiologyRate);
        case 'ultrasound':
        case 'ultrasonogram':
        case 'ultrasonography':
        case 'usg':
        case 'sono':
        case 'sonography':
            return $fallbackUltrasoundRate;
        case 'medicine':
        case 'pharmacy':
            return (float) ($payee->pharmacy_commission ?? 0);
        case 'opd':
        case 'appointment':
            return (float) ($payee->opd_commission ?? 0);
        case 'ipd':
            return (float) ($payee->ipd_commission ?? 0);
        default:
            return (float) ($payee->standard_commission ?? 0);
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