<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'billing_id',
        'amount',
        'payment_method',
        'transaction_id',
        'notes',
        'received_by',
        'payment_status',
        'status',
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
