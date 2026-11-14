<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDevice extends Model
{
    use HasFactory;

    protected $table = 'attendance_devices';
    protected $guarded = [];
    protected $casts = [
        'meta' => 'array',
    ];

    // device attributes: name, identifier (serial/ip), type (fingerprint|face), secret, status, meta
}
