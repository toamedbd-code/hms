<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Test extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tests';

    protected $fillable = [
        'category_type',
        'test_name',
        'test_short_name',
        'test_type',
        'test_category_id',
        'pathology_sub_category',
        'method',
        'report_days',
        'charge_category_id',
        'charge_name',
        'tax',
        'standard_charge',
        'amount',
        'test_parameters',
        'status'
    ];

    // Relationships
    public function pathologyCategory()
    {
        return $this->belongsTo(TestCategory::class, 'test_category_id');
    }

    public function chargeCategory()
    {
        return $this->belongsTo(ChargeCategory::class, 'charge_category_id');
    }

    public function testParameters()
    {
        return $this->hasMany(PathologyTestParameter::class, 'pathology_test_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'Inactive');
    }

    public function test()
    {
        return $this->belongsTo(Test::class, 'charge_category_id');
    }
}
