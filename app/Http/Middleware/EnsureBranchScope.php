<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureBranchScope
{
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return $next($request);
        }

        $activeCompanyId = $this->toInt(
            $request->attributes->get('active_company_id')
            ?? $request->session()->get('organization.company_id')
        );
        $activeBranchId = $this->toInt(
            $request->attributes->get('active_branch_id')
            ?? $request->session()->get('organization.branch_id')
        );

        $requestedCompanyId = $this->toInt($request->input('company_id'));
        $requestedBranchId = $this->toInt($request->input('branch_id'));

        $canCrossCompany = $this->hasScopeBypass($admin, 'organization.cross_company');
        $canCrossBranch = $canCrossCompany || $this->hasScopeBypass($admin, 'organization.cross_branch');

        if ($activeCompanyId && $requestedCompanyId && $requestedCompanyId !== $activeCompanyId && !$canCrossCompany) {
            if ($this->isReadRequest($request)) {
                $request->merge(['company_id' => $activeCompanyId]);
            } else {
                abort(403, 'Company scope violation.');
            }
        }

        if ($activeBranchId && $requestedBranchId && $requestedBranchId !== $activeBranchId && !$canCrossBranch) {
            if ($this->isReadRequest($request)) {
                $request->merge(['branch_id' => $activeBranchId]);
            } else {
                abort(403, 'Branch scope violation.');
            }
        }

        $effectiveCompanyId = $this->toInt($request->input('company_id')) ?: $activeCompanyId;
        $effectiveBranchId = $this->toInt($request->input('branch_id')) ?: $activeBranchId;

        if ($effectiveBranchId && $effectiveCompanyId) {
            $branchBelongsToCompany = Branch::query()
                ->whereKey($effectiveBranchId)
                ->where('company_id', $effectiveCompanyId)
                ->exists();

            if (!$branchBelongsToCompany) {
                abort(403, 'Invalid branch for selected company.');
            }
        }

        $request->attributes->set('active_company_id', $effectiveCompanyId);
        $request->attributes->set('active_branch_id', $effectiveBranchId);

        return $next($request);
    }

    protected function hasScopeBypass($admin, string $permission): bool
    {
        try {
            if (method_exists($admin, 'can') && ($admin->can('organization.cross_all') || $admin->can($permission))) {
                return true;
            }

            if (method_exists($admin, 'hasRole') && $admin->hasRole(['Super Admin', 'Developer'])) {
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    protected function isReadRequest(Request $request): bool
    {
        return in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    protected function toInt($value): ?int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT);

        return $normalized !== false ? (int) $normalized : null;
    }
}
