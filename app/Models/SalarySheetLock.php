<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalarySheetLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'is_locked',
        'locked_at',
        'locked_by',
        'lock_note',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function locker()
    {
        return $this->belongsTo(Admin::class, 'locked_by');
    }
}
