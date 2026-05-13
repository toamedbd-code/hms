<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    use HasFactory;

    protected $table = 'ledger_entries';

    protected $guarded = [];

    protected $fillable = ['transaction_id', 'account_id', 'amount', 'entry_type', 'narration'];

    public function transaction()
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
