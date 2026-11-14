<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    protected $fillable = [
        'account_id',
        'statement_date',
        'statement_balance',
        'book_balance',
        'cleared_balance',
        'difference',
        'notes',
        'status',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'statement_balance' => 'float',
        'book_balance' => 'float',
        'cleared_balance' => 'float',
        'difference' => 'float',
        'completed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BankReconciliationItem::class);
    }
}
