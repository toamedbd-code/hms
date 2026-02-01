<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RadiologyTest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'radiology_id',
        'test_id',
        'report_days',
        'report_date',
        'tax_percentage',
        'amount',
        'status'
    ];

    protected $casts = [
        'report_date' => 'date',
        'tax_percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'report_days' => 'integer'
    ];

    protected $dates = [
        'report_date',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /**
     * Get the radiology that owns the test.
     */
    public function radiology()
    {
        return $this->belongsTo(Radiology::class);
    }

    /**
     * Get the test details.
     */
    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    /**
     * Scope a query to only include active tests.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Get the tax amount for this test.
     */
    public function getTaxAmountAttribute()
    {
        return ($this->amount * $this->tax_percentage) / 100;
    }

    /**
     * Get the net amount including tax.
     */
    public function getNetAmountAttribute()
    {
        return $this->amount + $this->tax_amount;
    }
}