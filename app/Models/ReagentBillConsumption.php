<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReagentBillConsumption extends Model
{
    protected $fillable = [
        'bill_item_id',
        'store_item_id',
        'inventory_lot_id',
        'department_id',
        'stock_movement_id',
        'department_movement_id',
        'quantity',
        'reversed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'reversed_at' => 'datetime',
    ];
}