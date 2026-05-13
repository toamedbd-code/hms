<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountBalance extends Model
{
    use HasFactory;

    protected $table = 'account_balances';

    protected $guarded = [];

    protected $fillable = ['account_id', 'balance', 'profit', 'loss'];

    protected $casts = [
        'balance' => 'decimal:2',
        'profit' => 'decimal:2',
        'loss' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
