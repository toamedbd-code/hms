<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'phone',
        'message',
        'status',
        'provider_status_code',
        'response_body',
        'error_message',
        'attempts',
        'sent_by_admin_id',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'provider_status_code' => 'integer',
    ];
}
