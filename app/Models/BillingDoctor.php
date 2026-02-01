<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingDoctor extends Model
{
    use SoftDeletes;

    protected $table = 'billing_doctors';
    
    protected $fillable = [
        'name',
        'status'
    ];
}