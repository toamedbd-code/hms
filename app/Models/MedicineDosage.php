<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class MedicineDosage extends Authenticatable
{
    use Notifiable, HasFactory;
    use SoftDeletes;

    protected $table = 'medicinedosages';

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

    public function medicineCategory()  
    {
        return $this->belongsTo(MedicineCategory::class, 'medicine_category_id', 'id');
    }
    
    public function medicineUnit()  
    {
        return $this->belongsTo(MedicineUnit::class, 'medicine_unit_id', 'id');
    }
}
