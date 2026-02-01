<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Bed extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'beds';

    protected $fillable = [
        'name',
        'bed_type_id',
        'bed_group_id',
        'status'
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

    public function bedGroup() 
    {
        return $this->belongsTo(BedGroup::class, 'bed_group_id', 'id');
    }
    
    public function bedType() 
    {
        return $this->belongsTo(BedType::class, 'bed_type_id', 'id');
    }
}
