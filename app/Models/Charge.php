<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class Charge extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'charges';

    protected $fillable = [
        'name',
        'charge_type_id',
        'charge_category_id',
        'unit_type_id',
        'tax_category_id',
        'tax',
        'standard_charge',
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
    
    public function chargeCategory()
    {
        return $this->belongsTo(ChargeCategory::class, 'charge_category_id', 'id');
    }
    
    public function chargeUnitType()
    {
        return $this->belongsTo(ChargeUnitType::class, 'unit_type_id', 'id');
    }

    public function chargeTaxCategory()
    {
        return $this->belongsTo(ChargeCategory::class, 'tax_category_id', 'id');
    }
}
