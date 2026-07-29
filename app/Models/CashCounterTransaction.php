<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashCounterTransaction extends Model
{
    protected $table = 'cash_counter_transactions';

    protected $fillable = [
        'cash_counter_session_id',
        'type',
        'amount',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashCounterSession::class, 'cash_counter_session_id');
    }
}
