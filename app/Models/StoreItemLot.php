<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreItemLot extends Model
{
    protected $fillable = [
        'store_item_id',
        'department_id',
        'batch_no',
        'expiry_date',
        'serial_number',
        'asset_tag',
        'quantity',
        'unit_cost',
        'received_date',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'received_date' => 'date',
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function storeItem()
    {
        return $this->belongsTo(StoreItem::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}