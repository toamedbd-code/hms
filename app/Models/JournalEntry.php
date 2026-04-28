<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'entry_date',
        'reference',
        'description',
        'total_debit',
        'total_credit',
        'posted',
        'status',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted' => 'boolean',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
