<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreGrn extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_no',
        'supplier_name',
        'invoice_no',
        'receive_date',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'receive_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(StoreGrnItem::class, 'store_grn_id');
    }
}
