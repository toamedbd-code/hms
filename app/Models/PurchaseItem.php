<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_purchase_id',
        'medicine_category_id',
        'medicine_name',
        'quantity',
        'unit_purchase_price',
        'total_purchase_price',
        'received_quantity',
    ];

    protected $casts = [
        'unit_purchase_price' => 'decimal:2',
        'total_purchase_price' => 'decimal:2',
    ];

    public function medicinePurchase()
    {
        return $this->belongsTo(MedicinePurchase::class);
    }

    public function medicineCategory()
    {
        return $this->belongsTo(MedicineCategory::class, 'medicine_category_id');
    }
}
