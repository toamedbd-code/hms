<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpdPrescription extends Model
{
    use HasFactory;

    protected $appends = [
        'doctor_signature_url',
        'doctor_seal_url',
    ];

    protected $fillable = [
        'ipd_patient_id',
        'patient_id',
        'doctor_id',
        'complaints',
        'diagnosis',
        'advice',
        'follow_up_date',
        'doctor_signature_path',
        'doctor_seal_path',
        'doctor_designation',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
    ];

    public function ipdPatient()
    {
        return $this->belongsTo(IpdPatient::class, 'ipd_patient_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Admin::class, 'doctor_id');
    }

    public function medicines()
    {
        return $this->hasMany(IpdPrescriptionMedicine::class, 'ipd_prescription_id');
    }

    public function getDoctorSealUrlAttribute(): ?string
    {
        $path = trim((string) ($this->getRawOriginal('doctor_seal_path') ?? ''));
        if ($path === '') {
            return null;
        }

        return publicStorageUrl($path);
    }

    public function tests()
    {
        return $this->hasMany(IpdPrescriptionTest::class, 'ipd_prescription_id');
    }

    public function getDoctorSignatureUrlAttribute(): ?string
    {
        $path = trim((string) ($this->getRawOriginal('doctor_signature_path') ?? ''));
        if ($path === '') {
            return null;
        }

        return publicStorageUrl($path);
    }
}
