<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminDetail extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected $table = 'admin_details';

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function getResumePathAttribute($value)
    {
        return (!is_null($value)) ? env('APP_URL') . '/storage/' . $value : null;
    }

    public function getJoiningLetterPathAttribute($value)
    {
        return (!is_null($value)) ? env('APP_URL') . '/storage/' . $value : null;
    }

    public function getResignationLetterPathAttribute($value)
    {
        return (!is_null($value)) ? env('APP_URL') . '/' . $value : null;
    }

    public function getOtherDocumentsPathAttribute($value)
    {
        return (!is_null($value)) ? env('APP_URL') . '/' . $value : null;
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }
}
