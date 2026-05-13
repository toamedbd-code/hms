<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillOfMaterial extends Model
{
    protected $table = 'bill_of_materials';
    protected $fillable = ['code', 'product_id', 'name', 'quantity', 'unit_id', 'notes'];

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class, 'bom_id');
    }
}
