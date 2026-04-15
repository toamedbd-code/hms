<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Investigation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tests';

    protected $fillable = [
        'opd_patient_id',
        'category_type',
        'test_name',
        'test_short_name',
        'test_type',
        'test_category_id',
        'test_sub_category_id',
        'method',
        'report_days',
        'charge_category_id',
        'charge_name',
        'tax',
        'standard_charge',
        'amount',
        'test_parameters',
        'status',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function opdPatient()
    {
        return $this->belongsTo(OpdPatient::class, 'opd_patient_id');
    }
}
