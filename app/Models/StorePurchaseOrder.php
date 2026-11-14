<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorePurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_no',
        'store_supplier_id',
        'order_date',
        'expected_delivery_date',
        'total_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(StoreSupplier::class, 'store_supplier_id');
    }

    public function items()
    {
        return $this->hasMany(StorePurchaseOrderItem::class);
    }

    public function grns()
    {
        return $this->hasMany(StoreGrn::class);
    }

    public function payments()
    {
        return $this->hasMany(StorePurchasePayment::class);
    }
}
