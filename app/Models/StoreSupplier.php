<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSupplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'contact_person_name',
        'contact_person_phone',
        'address',
        'notes',
        'status',
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(StorePurchaseOrder::class, 'store_supplier_id');
    }
}
