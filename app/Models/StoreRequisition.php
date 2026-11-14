<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreRequisition extends Model
{
    use HasFactory;

    protected $fillable = [
        'requisition_no',
        'department',
        'department_id',
        'requested_by',
        'needed_date',
        'status',
        'notes',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'needed_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(StoreRequisitionItem::class, 'store_requisition_id');
    }

    public function requestedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'requested_by');
    }

    public function approvedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }
}
