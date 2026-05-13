<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ApprovalAction;
use App\Models\ApprovalFlow;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovalService
{
    public function start(string $module, Model $entity, int $requestedBy, ?int $companyId = null, ?int $branchId = null): ApprovalRequest
    {
        $flow = $this->resolveFlow($module, $companyId, $branchId);

        $status = $flow ? 'pending' : 'approved';
        $firstStepNo = $flow ? $flow->steps()->min('step_no') : null;

        return ApprovalRequest::create([
            'module' => $module,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getKey(),
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'approval_flow_id' => $flow?->id,
            'current_step_no' => $firstStepNo,
            'status' => $status,
            'requested_by' => $requestedBy,
            'requested_at' => now(),
            'resolved_at' => $status === 'approved' ? now() : null,
        ]);
    }

    public function approve(int $approvalRequestId, int $userId, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequestId, $userId, $comment) {
            $request = ApprovalRequest::query()
                ->with('flow.steps')
                ->lockForUpdate()
                ->findOrFail($approvalRequestId);

            if ($request->status !== 'pending') {
                throw new RuntimeException('Only pending requests can be approved.');
            }

            $currentStep = $request->flow?->steps->firstWhere('step_no', $request->current_step_no);
            if (!$currentStep) {
                throw new RuntimeException('Approval step not found for request.');
            }

            if (!$this->canUserApproveStep($currentStep, $userId)) {
                throw new RuntimeException('User is not authorized to approve this step.');
            }

            ApprovalAction::create([
                'approval_request_id' => $request->id,
                'step_no' => $currentStep->step_no,
                'action' => 'approve',
                'acted_by' => $userId,
                'acted_at' => now(),
                'comment' => $comment,
            ]);

            $approvedCount = ApprovalAction::query()
                ->where('approval_request_id', $request->id)
                ->where('step_no', $currentStep->step_no)
                ->where('action', 'approve')
                ->count();

            if ($approvedCount >= max(1, (int) $currentStep->min_approvals)) {
                $nextStepNo = $request->flow->steps
                    ->where('step_no', '>', $currentStep->step_no)
                    ->min('step_no');

                if ($nextStepNo) {
                    $request->current_step_no = $nextStepNo;
                } else {
                    $request->status = 'approved';
                    $request->current_step_no = null;
                    $request->resolved_at = now();
                }

                $request->save();
            }

            return $request->fresh();
        });
    }

    public function reject(int $approvalRequestId, int $userId, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequestId, $userId, $comment) {
            $request = ApprovalRequest::query()->lockForUpdate()->findOrFail($approvalRequestId);

            if ($request->status !== 'pending') {
                throw new RuntimeException('Only pending requests can be rejected.');
            }

            ApprovalAction::create([
                'approval_request_id' => $request->id,
                'step_no' => $request->current_step_no,
                'action' => 'reject',
                'acted_by' => $userId,
                'acted_at' => now(),
                'comment' => $comment,
            ]);

            $request->status = 'rejected';
            $request->resolved_at = now();
            $request->save();

            return $request->fresh();
        });
    }

    public function canUserApprove(int $approvalRequestId, int $userId): bool
    {
        $request = ApprovalRequest::query()->with('flow.steps')->find($approvalRequestId);
        if (!$request || $request->status !== 'pending') {
            return false;
        }

        $currentStep = $request->flow?->steps->firstWhere('step_no', $request->current_step_no);
        if (!$currentStep) {
            return false;
        }

        return $this->canUserApproveStep($currentStep, $userId);
    }

    protected function resolveFlow(string $module, ?int $companyId, ?int $branchId): ?ApprovalFlow
    {
        return ApprovalFlow::query()
            ->where('module', $module)
            ->where('is_active', true)
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id');
                if ($companyId) {
                    $query->orWhere('company_id', $companyId);
                }
            })
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id');
                if ($branchId) {
                    $query->orWhere('branch_id', $branchId);
                }
            })
            ->with('steps')
            ->orderByDesc('branch_id')
            ->orderByDesc('company_id')
            ->first();
    }

    protected function canUserApproveStep(ApprovalStep $step, int $userId): bool
    {
        if ($step->approver_type === 'user') {
            return (int) $step->approver_user_id === (int) $userId;
        }

        if ($step->approver_type === 'role' && $step->approver_role_id) {
            $admin = Admin::query()->select('id', 'role_id')->find($userId);
            return $admin && (int) $admin->role_id === (int) $step->approver_role_id;
        }

        return false;
    }
}
