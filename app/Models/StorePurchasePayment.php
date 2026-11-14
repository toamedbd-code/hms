<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorePurchasePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_purchase_order_id',
        'payment_account_id',
        'amount',
        'payment_date',
        'reference_no',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(StorePurchaseOrder::class, 'store_purchase_order_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }
}
