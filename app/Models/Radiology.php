<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Radiology extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'case_id',
        'bill_no',
        'radiology_no',
        'patient_id',
        'referral_doctor_id',
        'doctor_name',
        'note',
        'test_details',
        'total_amount',
        'tax_amount',
        'discount_amount',
        'discount_percentage',
        'net_amount',
        'payment_mode',
        'payment_amount',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'test_details' => 'array',
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /**
     * Get the patient that owns the radiology.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the referral doctor that owns the radiology.
     */
    public function referralDoctor()
    {
        return $this->belongsTo(Admin::class, 'referral_doctor_id');
    }

    /**
     * Get the radiology tests for the radiology.
     */
    public function radiologyTests()
    {
        return $this->hasMany(RadiologyTest::class);
    }

    /**
     * Get the billing record for the radiology.
     */
    public function billing()
    {
        return $this->hasOne(Billing::class, 'bill_number', 'bill_no');
    }

    /**
     * Scope a query to only include active radiologies.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Get the due amount.
     */
    public function getDueAmountAttribute()
    {
        return $this->net_amount - $this->payment_amount;
    }

    /**
     * Check if the radiology is fully paid.
     */
    public function getIsFullyPaidAttribute()
    {
        return $this->payment_amount >= $this->net_amount;
    }

    /**
     * Get the payment status.
     */
    public function getPaymentStatusAttribute()
    {
        if ($this->payment_amount <= 0) {
            return 'Pending';
        } elseif ($this->payment_amount >= $this->net_amount) {
            return 'Paid';
        } else {
            return 'Partial';
        }
    }
}