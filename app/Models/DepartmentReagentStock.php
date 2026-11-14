<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentReagentStock extends Model
{
    protected $fillable = ['department_id', 'store_item_id', 'current_stock', 'reorder_level'];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'reorder_level' => 'decimal:2',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function storeItem()
    {
        return $this->belongsTo(StoreItem::class);
    }

    public function movements()
    {
        return $this->hasMany(DepartmentReagentMovement::class, 'department_reagent_stock_id');
    }
}