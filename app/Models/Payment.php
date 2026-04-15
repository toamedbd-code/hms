<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ipd_patient_id',
        'opd_patient_id',
        'billing_id',
        'amount',
        'payment_method',
        'transaction_id',
        'notes',
        'received_by',
        'payment_status',
        'status',
        'provider',
        'provider_payment_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:2',
    ];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    // public function receivedBy()
    // {
    //     return $this->belongsTo(User::class, 'received_by');
    // }
}
