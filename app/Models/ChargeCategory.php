<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class ChargeCategory extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'chargecategories';

    protected $fillable = [
        'charge_type_id',
        'name',
        'description',
        'status',
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

    public function chargeType()
    {
        return $this->belongsTo(ChargeType::class, 'charge_type_id', 'id');
    }
    
    public function charge()
    {
        return $this->belongsTo(Charge::class, 'charge_type_id', 'id');
    }
}
