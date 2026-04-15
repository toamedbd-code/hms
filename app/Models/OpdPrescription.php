<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpdPrescription extends Model
{
    use HasFactory;

    protected $appends = [
        'doctor_signature_url',
        'doctor_seal_url',
    ];

    protected $fillable = [
        'opd_patient_id',
        'notes',
        'doctor_signature_path',
        'doctor_seal_path',
        'doctor_designation',
        'created_by',
        'updated_by',
    ];

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

    public function opdPatient()
    {
        return $this->belongsTo(OpdPatient::class, 'opd_patient_id');
    }

    public function items()
    {
        return $this->hasMany(OpdPrescriptionItem::class, 'opd_prescription_id');
    }
}
