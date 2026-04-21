<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $table = 'accounts';

    protected $guarded = [];

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }

    public function balance()
    {
        return $this->hasOne(AccountBalance::class, 'account_id');
    }
}
