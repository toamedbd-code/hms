<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_code',
        'item_name',
        'category',
        'unit',
        'reorder_level',
        'current_stock',
        'unit_cost',
        'status',
        'notes',
    ];

    protected $casts = [
        'reorder_level' => 'decimal:2',
        'current_stock' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function movements()
    {
        return $this->hasMany(StoreStockMovement::class, 'store_item_id');
    }

    public function requisitionItems()
    {
        return $this->hasMany(StoreRequisitionItem::class, 'store_item_id');
    }
}
