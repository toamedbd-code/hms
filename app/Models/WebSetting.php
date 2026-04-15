<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebSetting extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = "web_settings";
    protected $casts = [
        'attendance_device_enabled' => 'boolean',
        'attendance_device_options' => 'array',
        'website_enabled' => 'boolean',
        'patient_panel' => 'boolean',
        'sms_enabled' => 'boolean',
        'sms_is_unicode' => 'boolean',
        'opd_invoice_header_footer' => 'boolean',
        'ipd_invoice_header_footer' => 'boolean',
        'opd_prescription_header_footer' => 'boolean',
        'ipd_prescription_header_footer' => 'boolean',
    ];
    protected static function boot()
    {
        parent::boot();

        $forgetCache = static function (): void {
            if (function_exists('forget_cached_web_setting')) {
                forget_cached_web_setting();
                return;
            }

            Cache::forget('web_setting.active_or_latest');
        };

        static::saving(function ($model) {
            $model->created_at = now();
        });

        static::updating(function ($model) {
            $model->updated_at = now();
        });

        static::saved($forgetCache);
        static::deleted($forgetCache);
    }

    public function getIconAttribute($value)
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', 'data:'])) {
            return $value;
        }

        if (strpos($value, 'webSetting/') !== false) {
            return publicStorageUrl($value);
        }

        return asset(ltrim($value, '/'));
    }

    public function getLogoAttribute($value)
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', 'data:'])) {
            return $value;
        }

        if (strpos($value, 'webSetting/') !== false) {
            return publicStorageUrl($value);
        }

        return asset(ltrim($value, '/'));
    }

    public function getMobileAppLogoAttribute($value)
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', 'data:'])) {
            return $value;
        }

        if (strpos($value, 'webSetting/') !== false) {
            return publicStorageUrl($value);
        }

        return asset(ltrim($value, '/'));
    }
}
