<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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

    public static function getCurrent(): ?self
    {
        return static::first();
    }

    public static function ensureExists(): self
    {
        return static::first() ?? static::create(['is_active' => false]);
    }
}
