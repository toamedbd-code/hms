<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id', 'month', 'amount', 'payment_method', 'note', 'paid_at', 'admin_id', 'status', 'is_advance'
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'is_advance' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(Admin::class, 'staff_id');
    }
}
