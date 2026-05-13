<?php

namespace App\Models;

use App\Traits\ScopesByOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalFlow extends Model
{
    use HasFactory, SoftDeletes, ScopesByOrganization;

    protected $fillable = [
        'module',
        'name',
        'company_id',
        'branch_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(\App\Models\ApprovalStep::class)->orderBy('step_no');
    }

    public function requests()
    {
        return $this->hasMany(\App\Models\ApprovalRequest::class);
    }
}
