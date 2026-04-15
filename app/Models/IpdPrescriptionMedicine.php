<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpdPrescriptionMedicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'ipd_prescription_id',
        'medicine_name',
        'dose',
        'frequency',
        'duration',
        'instructions',
    ];

    public function prescription()
    {
        return $this->belongsTo(IpdPrescription::class, 'ipd_prescription_id');
    }
}
