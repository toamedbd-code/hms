<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreProductReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_product_return_id',
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

    public function productReturn()
    {
        return $this->belongsTo(StoreProductReturn::class, 'store_product_return_id');
    }

    public function storeItem()
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }
}
