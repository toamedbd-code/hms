<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DutyRoster extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'staff_id', 'date', 'start_time', 'end_time', 'shift_name', 'note', 'status'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function staff()
    {
        return $this->belongsTo(Admin::class, 'staff_id');
    }
}
