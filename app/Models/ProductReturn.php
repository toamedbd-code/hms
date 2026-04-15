<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number',
        'return_type',
        'supplier_id',
        'source_bill_no',
        'billing_id',
        'customer_name',
        'return_date',
        'total_amount',
        'paid_amount',
        'payment_status',
        'status',
        'reason',
        'notes',
    ];

    protected $casts = [
        'return_date' => 'date',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(MedicineSupplier::class, 'supplier_id');
    }

    public function returnItems()
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function billing()
    {
        return $this->belongsTo(Billing::class, 'billing_id');
    }
}
