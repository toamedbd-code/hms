<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class OpdPatient extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'opdpatients';

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

    public function chargeType()
    {
        return $this->belongsTo(ChargeType::class, 'charge_type_id', 'id');
    }

    public function consultantDoctor()
    {
        return $this->belongsTo(Admin::class, 'consultant_doctor_id', 'id');
    }
}
