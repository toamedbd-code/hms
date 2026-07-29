<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_active',
        'expires_at',
        'last_payment_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at === null) {
            return false;
        }

        return Carbon::now()->lessThan($this->expires_at);
    }

    public static function tableExists(): bool
    {
        try {
            return app('db')->connection()->getSchemaBuilder()->hasTable((new static())->getTable());
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public static function getCurrent(): ?self
    {
        // Cache subscription for a short time to reduce database queries during login.
        // This cache is cleared whenever the subscription is updated.
        return Cache::remember('subscription_current', 300, function () {
            if (!static::tableExists()) {
                return null;
            }
            return static::first();
        });
    }

    public static function clearCurrentCache(): void
    {
        Cache::forget('subscription_current');
        Cache::forget('login_subscription_status');
    }

    public static function ensureExists(): self
    {
        if (!static::tableExists()) {
            return new static(['is_active' => false]);
        }

        return static::first() ?? static::create(['is_active' => false]);
    }
}
