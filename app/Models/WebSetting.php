<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebSetting extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = "web_settings";
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

    public function getIconAttribute($value)
    {
        if (strpos($value, 'webSetting/') !== false) {
            return (!is_null($value)) ? env('APP_URL') . '/storage/' . $value : null;
        } else {
            return (!is_null($value)) ? env('APP_URL') . '/' . $value : null;
        }
    }

    public function getLogoAttribute($value)
    {
        if (strpos($value, 'webSetting/') !== false) {
            return (!is_null($value)) ? env('APP_URL') . '/storage/' . $value : null;
        } else {
            return (!is_null($value)) ? env('APP_URL') . '/' . $value : null;
        }
    }
}
