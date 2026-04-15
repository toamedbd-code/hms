<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpdDoctorVisitCharge extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ipd_doctor_visit_charges';

    protected $guarded = [];

    protected $casts = [
        'visited_at' => 'datetime',
        'fee_per_visit' => 'decimal:2',
        'visit_count' => 'decimal:3',
        'total_amount' => 'decimal:2',
    ];

    public function ipdPatient()
    {
        return $this->belongsTo(IpdPatient::class, 'ipd_patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Admin::class, 'doctor_id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
