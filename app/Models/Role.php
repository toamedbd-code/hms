<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'guard_name', 'created_at', 'updated_at', 'is_private', 'created_by'];

    protected $casts = [
        'is_private' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'created_by');
    }

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
}
