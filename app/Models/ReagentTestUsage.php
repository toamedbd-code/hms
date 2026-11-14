<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReagentTestUsage extends Model
{
    protected $fillable = ['store_item_id', 'test_id', 'department_id', 'quantity_per_test'];

    protected $casts = ['quantity_per_test' => 'decimal:2'];

    public function storeItem()
    {
        return $this->belongsTo(StoreItem::class);
    }

    public function test()
    {
        return $this->belongsTo(Test::class, 'test_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}