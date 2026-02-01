<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expenses';

    protected $fillable = [
        'expense_header_id',
        'invoice_number',
        'bill_number',
        'case_id',
        'name',
        'document',
        'description',
        'amount',
        'date',
        'status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    protected $dates = ['deleted_at'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = now();
        });

        static::updating(function ($model) {
            $model->updated_at = now();
        });
    }

    public function expenseHead()
    {
        return $this->belongsTo(ExpenseHead::class, 'expense_header_id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    public function getDocumentAttribute($value)
    {
        return (!is_null($value)) ? env('APP_URL') . '/storage/' . $value : null;
    }

    public function setDocumentAttribute($value)
    {
        if (!is_null($value) && strpos($value, 'http') !== 0) {
            $this->attributes['document'] = $value;
        } else {
            $this->attributes['document'] = str_replace(env('APP_URL') . '/public/storage/', '', $value ?? '');
        }
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function getFormattedAmountAttribute()
    {
        return '৳ ' . number_format($this->amount, 2);
    }

    public function getFormattedDateAttribute()
    {
        return $this->date->format('d M, Y');
    }
}
