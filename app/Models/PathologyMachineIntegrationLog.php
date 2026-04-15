<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PathologyMachineIntegrationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'event',
        'level',
        'source_format',
        'communication_mode',
        'ip_address',
        'message',
        'context',
        'raw_payload',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
