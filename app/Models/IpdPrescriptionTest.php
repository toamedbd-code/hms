<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpdPrescriptionTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'ipd_prescription_id',
        'test_name',
    ];

    public function prescription()
    {
        return $this->belongsTo(IpdPrescription::class, 'ipd_prescription_id');
    }
}
