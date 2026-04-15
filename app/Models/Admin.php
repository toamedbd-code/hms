<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use Notifiable, HasFactory, HasRoles;

    /**
     * Spatie permissions must use the same guard as the admin authentication guard.
     */
    protected string $guard_name = 'admin';

    protected $table = 'admins';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['name'];
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

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function getNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getPhotoAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        $photo = (string) $value;

        if (
            str_starts_with($photo, 'http://')
            || str_starts_with($photo, 'https://')
            || str_starts_with($photo, 'data:')
        ) {
            return $photo;
        }

        if (str_starts_with($photo, '/')) {
            return $photo;
        }

        // Resolve via app URL configuration without hardcoding APP_URL manually.
        return asset('storage/' . ltrim($photo, '/'));
    }

    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    public function details()
    {
        return $this->hasOne(AdminDetail::class);
    }

    public function staffAttendance()
    {
        return $this->hasMany(StaffAttendance::class, 'staff_id', 'id');
    }
}
