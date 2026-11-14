<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReagentCategoryUsage extends Model
{
    protected $fillable = ['store_item_id', 'category_key', 'department_id', 'quantity_per_test'];

    protected $casts = ['quantity_per_test' => 'decimal:2'];

    public function storeItem()
    {
        return $this->belongsTo(StoreItem::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
