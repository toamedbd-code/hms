<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreRequisitionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_requisition_id',
        'store_item_id',
        'requested_qty',
        'issued_qty',
        'remarks',
    ];

    protected $casts = [
        'requested_qty' => 'decimal:2',
        'issued_qty' => 'decimal:2',
    ];

    public function requisition()
    {
        return $this->belongsTo(StoreRequisition::class, 'store_requisition_id');
    }

    public function storeItem()
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }
}
