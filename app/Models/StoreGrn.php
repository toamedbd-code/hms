<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreGrn extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_no',
        'store_purchase_order_id',
        'supplier_name',
        'invoice_no',
        'receive_date',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'receive_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(StoreGrnItem::class, 'store_grn_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(StorePurchaseOrder::class, 'store_purchase_order_id');
    }
}
