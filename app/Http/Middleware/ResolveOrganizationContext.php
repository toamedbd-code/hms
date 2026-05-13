<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResolveOrganizationContext
{
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return $next($request);
        }

        $activeCompanyId = $this->resolveCompanyId($request, $admin);
        $activeBranchId = $this->resolveBranchId($request, $admin, $activeCompanyId);

        $request->attributes->set('active_company_id', $activeCompanyId);
        $request->attributes->set('active_branch_id', $activeBranchId);

        if ($activeCompanyId && !$request->filled('company_id')) {
            $request->merge(['company_id' => $activeCompanyId]);
        }

        if ($activeBranchId && !$request->filled('branch_id')) {
            $request->merge(['branch_id' => $activeBranchId]);
        }

        session([
            'organization.company_id' => $activeCompanyId,
            'organization.branch_id' => $activeBranchId,
        ]);

        app()->instance('organization.context', [
            'company_id' => $activeCompanyId,
            'branch_id' => $activeBranchId,
        ]);

        return $next($request);
    }

    protected function resolveCompanyId(Request $request, $admin): ?int
    {
        $requestedCompanyId = $this->toInt(
            $request->header('X-Company-Id')
            ?? $request->input('company_id')
            ?? $request->session()->get('organization.company_id')
            ?? data_get($admin, 'default_company_id')
            ?? data_get($admin, 'company_id')
        );

        if ($requestedCompanyId && Company::query()->whereKey($requestedCompanyId)->exists()) {
            return $requestedCompanyId;
        }

        $fallbackCompanyId = Company::query()->value('id');

        return $fallbackCompanyId ? (int) $fallbackCompanyId : null;
    }

    protected function resolveBranchId(Request $request, $admin, ?int $companyId): ?int
    {
        $requestedBranchId = $this->toInt(
            $request->header('X-Branch-Id')
            ?? $request->input('branch_id')
            ?? $request->session()->get('organization.branch_id')
            ?? data_get($admin, 'default_branch_id')
            ?? data_get($admin, 'branch_id')
        );

        if ($requestedBranchId) {
            $branchQuery = Branch::query()->whereKey($requestedBranchId);

            if ($companyId) {
                $branchQuery->where('company_id', $companyId);
            }

            if ($branchQuery->exists()) {
                return $requestedBranchId;
            }
        }

        if (!$companyId) {
            return null;
        }

        $fallbackBranchId = Branch::query()
            ->where('company_id', $companyId)
            ->orderByRaw("CASE WHEN LOWER(status) = 'active' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->value('id');

        return $fallbackBranchId ? (int) $fallbackBranchId : null;
    }

    protected function toInt($value): ?int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT);

        return $normalized !== false ? (int) $normalized : null;
    }
}
