<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PathologyTestParameter extends Model
{
    use HasFactory;

    protected $table = 'pathology_test_parameters';

    protected $fillable = [
        'pathology_test_id',
        'test_parameter_id',
        'name',
        'reference_from',
        'reference_to',
        'pathology_unit_id'
    ];

    public function pathologyTest()
    {
        return $this->belongsTo(PathologyTest::class, 'pathology_test_id');
    }

    public function testParameter()
    {
        return $this->belongsTo(PathologyParameter::class, 'test_parameter_id');
    }

    public function pathologyUnit()
    {
        return $this->belongsTo(PathologyUnit::class, 'pathology_unit_id');
    }
}