<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_inventory_id',
        'adjustment_type',
        'quantity',
        'unit_price',
        'reason',
        'adjustment_date',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'adjustment_date' => 'date',
    ];

    public function medicineInventory()
    {
        return $this->belongsTo(MedicineInventory::class, 'medicine_inventory_id');
    }
}
