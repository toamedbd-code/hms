<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpeningBalancePosting extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'ledger_transaction_id',
        'posted_by',
        'is_repost',
        'posting_date',
        'total_debit',
        'total_credit',
        'line_count',
        'snapshot',
        'notes',
    ];

    protected $casts = [
        'is_repost' => 'boolean',
        'posting_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'snapshot' => 'array',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function ledgerTransaction()
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    public function postedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'posted_by');
    }
}
