<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreProductReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number',
        'store_supplier_id',
        'return_date',
        'total_amount',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(StoreSupplier::class, 'store_supplier_id');
    }

    public function items()
    {
        return $this->hasMany(StoreProductReturnItem::class, 'store_product_return_id');
    }
}
