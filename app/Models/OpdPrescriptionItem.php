<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpdPrescriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'opd_prescription_id',
        'test_name',
        'medicine_name',
        'dose',
        'duration',
        'frequency',
        'instructions',
    ];

    public function prescription()
    {
        return $this->belongsTo(OpdPrescription::class, 'opd_prescription_id');
    }

    public function investigation()
    {
        return $this->belongsTo(Investigation::class, 'test_name', 'test_name');
    }
}
