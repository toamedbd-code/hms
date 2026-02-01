<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_return_id',
        'medicine_inventory_id',
        'quantity',
        'unit_price',
        'total_amount',
        'condition',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function productReturn()
    {
        return $this->belongsTo(ProductReturn::class);
    }

    public function medicineInventory()
    {
        return $this->belongsTo(MedicineInventory::class, 'medicine_inventory_id');
    }
}
