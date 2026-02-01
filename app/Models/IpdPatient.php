<?php
namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class IpdPatient extends Authenticatable
{
    use Notifiable,HasFactory;

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
        return $this->belongsTo(Patient::class, 'consultant_doctor_id', 'id');
    }
    
    public function bed()
    {
        return $this->belongsTo(Bed::class, 'bed_id', 'id');
    }
}
