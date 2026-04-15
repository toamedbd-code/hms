<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreStockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_item_id',
        'movement_type',
        'quantity',
        'unit_price',
        'reason',
        'movement_date',
        'reference_no',
        'department',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'movement_date' => 'date',
    ];

    public function storeItem()
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }
}
