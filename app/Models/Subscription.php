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
        if (!static::tableExists()) {
            return null;
        }

        return static::first();
    }

    public static function ensureExists(): self
    {
        if (!static::tableExists()) {
            return new static(['is_active' => false]);
        }

        return static::first() ?? static::create(['is_active' => false]);
    }
}
