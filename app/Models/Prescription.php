<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory;

    protected $appends = [
        'doctor_signature_url',
        'doctor_seal_url',
        'patient_name',
        'doctor_name',
    ];

    protected $fillable = [
        'prescription_no',
        'patient_id',
        'doctor_id',
        'doctor_designation',
        'nibp',
        'weight',
        'pulse',
        'temperature',
        'complaints',
        'examinations',
        'diagnosis',
        'advice',
        'follow_up_date',
        'doctor_signature_path',
        'doctor_seal_path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
    ];

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
        return $this->hasMany(PrescriptionMedicine::class, 'prescription_id');
    }

    public function tests()
    {
        return $this->hasMany(PrescriptionTest::class, 'prescription_id');
    }

    public function getDoctorSignatureUrlAttribute(): ?string
    {
        $path = trim((string) ($this->getRawOriginal('doctor_signature_path') ?? ''));
        if ($path === '') {
            return null;
        }

        return publicStorageUrl($path);
    }

    public function getDoctorSealUrlAttribute(): ?string
    {
        $path = trim((string) ($this->getRawOriginal('doctor_seal_path') ?? ''));
        if ($path === '') {
            return null;
        }

        return publicStorageUrl($path);
    }

    public function getPatientNameAttribute(): string
    {
        $patient = $this->patient;
        if (!$patient) {
            return '';
        }

        $name = trim((string) ($patient->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) (($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '')));
    }

    public function getDoctorNameAttribute(): string
    {
        return trim((string) ($this->doctor?->name ?? ''));
    }
}
