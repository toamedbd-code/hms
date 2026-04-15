<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BkashSetting extends Model
{
    use HasFactory;

    protected $table = 'bkash_settings';

    protected $fillable = [
        'app_key',
        'app_secret',
        'username',
        'password',
        'merchant_number',
        'is_sandbox',
        'is_enabled',
        'monthly_amount',
    ];

    protected $casts = [
        'is_sandbox' => 'boolean',
        'is_enabled' => 'boolean',
        'monthly_amount' => 'decimal:2',
    ];

    public static function singleton()
    {
        return static::first();
    }
}
