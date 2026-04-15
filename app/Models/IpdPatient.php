<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class IpdPatient extends Authenticatable
{
    use Notifiable, HasFactory;

    // Add consultantDoctor relationship for compatibility with ReportController
    public function consultantDoctor()
    {
        return $this->belongsTo(Admin::class, 'consultant_doctor_id', 'id');
    }
    use Notifiable, HasFactory;

    protected $table = 'ipdpatients';

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            $model->created_at = date('Y-m-d H:i:s');
        });

        static::updating(function ($model) {
            $model->updated_at = date('Y-m-d H:i:s');
        });
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(Admin::class, 'consultant_doctor_id', 'id');
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class, 'bed_id', 'id');
    }

    public function prescriptions()
    {
        return $this->hasMany(IpdPrescription::class, 'ipd_patient_id');
    }

        public function latestPrescription()
    {
        return $this->hasOne(IpdPrescription::class, 'ipd_patient_id')->latestOfMany();
    }

        public function billing()
    {
        return $this->belongsTo(Billing::class, 'billing_id');
    }

    public function roomRentCharges()
    {
        return $this->hasMany(IpdRoomRentCharge::class, 'ipd_patient_id');
    }

    public function bedCharges()
    {
        return $this->hasMany(IpdBedCharge::class, 'ipd_patient_id');
    }

    public function otCharges()
    {
        return $this->hasMany(IpdOtCharge::class, 'ipd_patient_id');
    }

    public function doctorVisitCharges()
    {
        return $this->hasMany(IpdDoctorVisitCharge::class, 'ipd_patient_id');
    }

    public function ipdNotes()
    {
        return $this->hasMany(\App\Models\IpdNote::class, 'ipd_patient_id');
    }
}


