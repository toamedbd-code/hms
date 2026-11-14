<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorePurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_purchase_order_id',
        'store_item_id',
        'quantity',
        'unit_cost',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(StorePurchaseOrder::class, 'store_purchase_order_id');
    }

    public function storeItem()
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }

    public function grnItems()
    {
        return $this->hasMany(StoreGrnItem::class, 'store_purchase_order_item_id');
    }
}
