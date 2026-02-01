<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefineLeave extends Model
{
    use HasFactory;

    protected $fillable = ['role_id', 'type_id', 'days', 'status', 'created_at', 'updated_at', 'deleted_at'];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            $model->created_at = now();
        });

        static::updating(function ($model) {
            $model->updated_at = now();
        });
    }


    public function role()
    {
        return $this->belongsTo(Role::class, "role_id", "id");
    }

    public function roles()
    {
        return $this->hasMany(Role::class, "role", "id");
    }
    
    public function LeaveType()
    {
        return $this->belongsTo(LeaveType::class, "type_id", "id");
    }

    public function LeaveTypes()
    {
        return $this->hasMany(LeaveType::class, "type", "id");
    }
}
