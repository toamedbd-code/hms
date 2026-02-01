<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DueCollection extends Model
{
    use HasFactory;

    protected $table = 'due_collections';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'billing_id',
        'collected_amount',
        'collected_at',
        'payment_method',
        'note',
        'created_by',
    ];

    /**
     * Cast attributes
     */
    protected $casts = [
        'collected_amount' => 'decimal:2',
        'collected_at'     => 'datetime',
    ];

    /**
     * Relationship: DueCollection belongs to Billing
     */
    public function billing()
    {
        return $this->belongsTo(Billing::class, 'billing_id');
    }
}