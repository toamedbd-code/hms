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
        'duration_days',
        'expires_at',
        'last_payment_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration_days' => 'integer',
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

    public function daysRemaining(?Carbon $at = null): int
    {
        if (! $this->expires_at) {
            return 0;
        }

        $secondsRemaining = $this->expires_at->getTimestamp() - ($at ?? Carbon::now())->getTimestamp();

        return max(0, (int) ceil($secondsRemaining / 86400));
    }

    public function canRenew(?Carbon $at = null): bool
    {
        return ! $this->isActive() || $this->daysRemaining($at) <= 5;
    }

    public function renewalMonthsDue(?Carbon $at = null): int
    {
        if (! $this->expires_at) {
            return 1;
        }

        $at ??= Carbon::now();
        $secondsOverdue = max(0, $at->getTimestamp() - $this->expires_at->getTimestamp());

        return 1 + intdiv($secondsOverdue, 30 * 86400);
    }

    public function extendForPeriod(string $period = 'monthly', int $months = 1): void
    {
        $months = max(1, $months);
        $start = $this->expires_at && $this->expires_at->isFuture()
            ? $this->expires_at->copy()
            : Carbon::now();

        $this->is_active = true;
        $this->expires_at = $period === 'yearly'
            ? $start->addYear()
            : $start->addDays(30 * $months);
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
