<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentReagentMovement extends Model
{
    protected $fillable = [
        'department_reagent_stock_id',
        'department_id',
        'store_item_id',
        'inventory_lot_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'reference_no',
        'reason',
        'movement_date',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'movement_date' => 'date',
    ];

    public function stock()
    {
        return $this->belongsTo(DepartmentReagentStock::class, 'department_reagent_stock_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function storeItem()
    {
        return $this->belongsTo(StoreItem::class);
    }

    public function inventoryLot()
    {
        return $this->belongsTo(StoreItemLot::class, 'inventory_lot_id');
    }

    public function createdByAdmin()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}