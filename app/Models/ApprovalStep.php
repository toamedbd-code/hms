<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_flow_id',
        'step_no',
        'approver_type',
        'approver_role_id',
        'approver_user_id',
        'min_approvals',
        'amount_threshold_min',
        'amount_threshold_max',
    ];

    public function flow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }
}
