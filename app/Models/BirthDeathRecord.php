<?php
namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class BirthDeathRecord extends Authenticatable
{
    use Notifiable,HasFactory;

    protected $table = 'birthdeathrecords';

    protected $fillable = [
        'name',
        'child_name',
        'patient_name',
        'record_type',
        'record_date',
        'birth_date',
        'death_date',
        'weight',
        'case_id',
        'guardian_name',
        'mother_name',
        'father_name',
        'gender',
        'phone',
        'address',
        'report',
        'photo',
        'child_photo',
        'mother_photo',
        'father_photo',
        'attachment',
        'report_attachment',
    ];

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

    public function getPhotoAttribute($value)
    {
        return (!is_null($value) && $value !== '') ? env('APP_URL') . '/public/storage/' . ltrim($value, '/') : null;
    }
}
