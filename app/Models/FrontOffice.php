<?php
namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class FrontOffice extends Authenticatable
{
    use Notifiable,HasFactory;

    protected $table = 'frontoffices';

    protected $fillable = [
        'name',
        'phone',
        'purpose',
        'visit_to',
        'date_in',
        'time_in',
        'time_out',
        'photo',
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
