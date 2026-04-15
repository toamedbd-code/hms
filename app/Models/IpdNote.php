<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpdNote extends Model
{
    use HasFactory;

    protected $table = 'ipd_notes';

    protected $fillable = [
        'ipd_patient_id',
        'type',
        'content',
        'created_by',
        'status',
    ];

    public function ipdPatient()
    {
        return $this->belongsTo(IpdPatient::class, 'ipd_patient_id');
    }
}
