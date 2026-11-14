<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrescriptionSetting extends Model
{
    protected $fillable = [
        'doctor_designation',
        'doctor_seal_path',
        'doctor_signature_path',
        'doctor_pad_path',
        'prescription_header_path',
        'institution_pad_path',
        'institution_pad_content_offset',
    ];

    protected $appends = [
        'doctor_seal_url',
        'doctor_signature_url',
        'doctor_pad_url',
        'prescription_header_url',
        'institution_pad_url',
    ];

    protected $casts = [
        'institution_pad_content_offset' => 'integer',
    ];

    public function getDoctorSealUrlAttribute(): ?string
    {
        return publicStorageUrl($this->doctor_seal_path);
    }

    public function getDoctorSignatureUrlAttribute(): ?string
    {
        return publicStorageUrl($this->doctor_signature_path);
    }

    public function getDoctorPadUrlAttribute(): ?string
    {
        return publicStorageUrl($this->doctor_pad_path);
    }

    public function getPrescriptionHeaderUrlAttribute(): ?string
    {
        return publicStorageUrl($this->prescription_header_path) ?? $this->doctor_pad_url;
    }

    public function getInstitutionPadUrlAttribute(): ?string
    {
        return publicStorageUrl($this->institution_pad_path);
    }
}
