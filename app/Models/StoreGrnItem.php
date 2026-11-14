<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreGrnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_grn_id',
        'store_purchase_order_item_id',
        'store_item_id',
        'quantity',
        'unit_cost',
        'line_total',
        'inventory_lot_id',
        'batch_no',
        'expiry_date',
        'serial_number',
        'asset_tag',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function grn()
    {
        return $this->belongsTo(StoreGrn::class, 'store_grn_id');
    }

    public function storeItem()
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(StorePurchaseOrderItem::class, 'store_purchase_order_item_id');
    }
}
