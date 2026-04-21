<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'device_id',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';
    protected $guarded = [];

    protected $dates = ['recorded_at', 'recorded_out'];

    protected $casts = [
        'recorded_at' => 'datetime',
        'recorded_out' => 'datetime',
        'meta' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }
}
