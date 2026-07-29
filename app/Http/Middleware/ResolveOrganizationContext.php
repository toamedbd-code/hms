<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $companyQuery = $this->baseTableQuery('companies');

        if ($requestedCompanyId) {
            $companyQuery->where('id', $requestedCompanyId);

            if ($companyQuery->exists()) {
                return $requestedCompanyId;
            }
        }

        $fallbackCompanyId = $this->baseTableQuery('companies')->value('id');

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
            $branchQuery = $this->baseTableQuery('branches')->where('id', $requestedBranchId);

            if ($companyId && Schema::hasColumn('branches', 'company_id')) {
                $branchQuery->where('company_id', $companyId);
            }

            if ($branchQuery->exists()) {
                return $requestedBranchId;
            }
        }

        if (!$companyId) {
            return null;
        }

        $fallbackBranchQuery = $this->baseTableQuery('branches');

        if (Schema::hasColumn('branches', 'company_id')) {
            $fallbackBranchQuery->where('company_id', $companyId);
        }

        $fallbackBranchId = $fallbackBranchQuery
            ->orderByRaw("CASE WHEN LOWER(status) = 'active' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->value('id');

        return $fallbackBranchId ? (int) $fallbackBranchId : null;
    }

    protected function baseTableQuery(string $table)
    {
        $query = DB::table($table);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    protected function toInt($value): ?int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT);

        return $normalized !== false ? (int) $normalized : null;
    }
}
