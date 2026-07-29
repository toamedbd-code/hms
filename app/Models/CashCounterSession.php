<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashCounterSession extends Model
{
    protected $table = 'cash_counter_sessions';

    protected $fillable = [
        'counter_name',
        'user_name',
        'shift_name',
        'opening_amount',
        'expected_amount',
        'closing_amount',
        'difference_amount',
        'handover_in_amount',
        'handover_out_amount',
        'opening_note',
        'opened_at',
        'closed_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'opening_amount' => 'float',
        'expected_amount' => 'float',
        'closing_amount' => 'float',
        'difference_amount' => 'float',
        'handover_in_amount' => 'float',
        'handover_out_amount' => 'float',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(CashCounterTransaction::class, 'cash_counter_session_id');
    }
}
