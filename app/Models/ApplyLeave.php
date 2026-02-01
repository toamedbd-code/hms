<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplyLeave extends Model
{
    use HasFactory;

    protected $fillable = ['apply_date', 'employee_id', 'leave_type_id', 'from', 'to', 'reason', 'attachment', 'status', 'created_at', 'updated_at', 'deleted_at'];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            $model->created_at = now();
        });

        static::updating(function ($model) {
            $model->updated_at = now();
        });
    }


    public function LeaveType()
    {
        return $this->belongsTo(LeaveType::class, "leave_type_id", "id");
    }

    public function employee()
    {
        return $this->belongsTo(Admin::class, "employee_id", "id");
    }

    public function LeaveTypes()
    {
        return $this->hasMany(LeaveType::class, "leave_type_id", "id");
    }

    public function getAttachmentAttribute($value)
    {
        return (!is_null($value)) ? env('APP_URL') . '/public/storage/' . $value : null;
    }
}
