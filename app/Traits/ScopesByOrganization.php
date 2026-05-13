<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait ScopesByOrganization
{
    protected static array $organizationScopeColumnCache = [];

    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        if (!$companyId || !$this->supportsOrganizationColumn($query, 'company_id')) {
            return $query;
        }

        return $query->where($query->qualifyColumn('company_id'), $companyId);
    }

    public function scopeForBranch(Builder $query, ?int $branchId, bool $includeGlobal = false): Builder
    {
        if (!$this->supportsOrganizationColumn($query, 'branch_id')) {
            return $query;
        }

        $column = $query->qualifyColumn('branch_id');

        if (!$branchId) {
            return $includeGlobal ? $query->whereNull($column) : $query;
        }

        if (!$includeGlobal) {
            return $query->where($column, $branchId);
        }

        return $query->where(function (Builder $builder) use ($column, $branchId) {
            $builder->where($column, $branchId)
                ->orWhereNull($column);
        });
    }

    public function scopeForOrganization(
        Builder $query,
        ?int $companyId,
        ?int $branchId,
        bool $includeGlobalBranch = false
    ): Builder {
        return $this->scopeForBranch(
            $this->scopeForCompany($query, $companyId),
            $branchId,
            $includeGlobalBranch
        );
    }

    public function scopeForActiveOrganization(Builder $query, bool $includeGlobalBranch = false): Builder
    {
        return $this->scopeForOrganization(
            $query,
            $this->resolveActiveContextValue('active_company_id', 'organization.company_id'),
            $this->resolveActiveContextValue('active_branch_id', 'organization.branch_id'),
            $includeGlobalBranch
        );
    }

    protected function supportsOrganizationColumn(Builder $query, string $column): bool
    {
        $model = $query->getModel();
        $table = $model->getTable();
        $cacheKey = $model::class . ':' . $table . ':' . $column;

        if (!array_key_exists($cacheKey, self::$organizationScopeColumnCache)) {
            self::$organizationScopeColumnCache[$cacheKey] = Schema::hasColumn($table, $column);
        }

        return self::$organizationScopeColumnCache[$cacheKey];
    }

    protected function resolveActiveContextValue(string $requestKey, string $sessionKey): ?int
    {
        $value = null;

        try {
            $value = request()?->attributes->get($requestKey);
        } catch (\Throwable $e) {
            $value = null;
        }

        if (!$value) {
            $value = session($sessionKey);
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT);

        return $normalized !== false ? (int) $normalized : null;
    }
}