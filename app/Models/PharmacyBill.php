<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PharmacyBill extends Authenticatable
{
    use Notifiable,HasFactory;

    protected $table = 'pharmacybills';

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
        return $this->belongsTo(Patient::class);
    }
}
