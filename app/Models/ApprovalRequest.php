<?php

namespace App\Models;

use App\Traits\ScopesByOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    use HasFactory, ScopesByOrganization;

    protected $fillable = [
        'module',
        'entity_type',
        'entity_id',
        'company_id',
        'branch_id',
        'approval_flow_id',
        'current_step_no',
        'status',
        'requested_by',
        'requested_at',
        'resolved_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function flow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function actions()
    {
        return $this->hasMany(\App\Models\ApprovalAction::class);
    }

    public function latestAction()
    {
        return $this->hasOne(\App\Models\ApprovalAction::class)->latestOfMany('id');
    }
}
