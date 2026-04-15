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

    protected $casts = [
        'sample_collected_at' => 'datetime',
        'reported_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function collectedBy()
    {
        return $this->belongsTo(Admin::class, 'sample_collected_by');
    }

    public function reportedBy()
    {
        return $this->belongsTo(Admin::class, 'reported_by');
    }

    public function deliveredBy()
    {
        return $this->belongsTo(Admin::class, 'delivered_by');
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
