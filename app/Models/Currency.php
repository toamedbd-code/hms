<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $table = 'currencies';
    protected $fillable = ['code', 'name', 'symbol', 'is_base'];

    public function rates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }
}
