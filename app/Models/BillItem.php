<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function itemable()
    {
        return $this->morphTo(__FUNCTION__, 'category', 'item_id');
    }

    public function getItemNameAttribute($value)
    {
        if (empty($value) && $this->itemable) {
            return $this->itemable->name ?? $this->itemable->title ?? 'N/A';
        }

        return $value ?? 'N/A';
    }
}
