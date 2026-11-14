<?php

namespace App\Services;

use App\Models\MainCategory;
use App\Models\Permission;
use App\Models\TestCategory;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    protected $permissionModel,$spatiePermissionModel;

    public function __construct(Permission $permissionModel,SpatiePermission $spatiePermissionModel)
    {
        $this->permissionModel = $permissionModel;
        $this->spatiePermissionModel = $spatiePermissionModel;
    }

    public function list()
    {
        return $this->permissionModel->query();
    }
    public function parentList()
    {
        return $this->permissionModel->whereNull('parent_id')->get();
    }

    public function all()
    {
        return $this->permissionModel->all();
    }

    public function find($id)
    {
        return $this->permissionModel->find($id);
    }

    public function create(array $data)
    {
        return $this->permissionModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo = $this->permissionModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function updateByName(array $data, $name)
    {
        $dataInfo = $this->permissionModel->where('name',$name)->firstOrFail();

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        return $this->permissionModel->find($id)->delete();

    }

    public function changeStatus($request)
    {
        $dataInfo = $this->permissionModel->findOrFail($request->id);

        $dataInfo->update($request->all());

        return $dataInfo;
    }
    public function listWithAllChild()
    {
        $this->removeOrphanedDashboardCategoryPermissions();
        $this->ensureDashboardPermissions();

        // Keep permission tree aligned with sidebar/menu serial by using
        // sorting first and id as tie-breaker.
        return $this->permissionModel
                ->whereNull('parent_id')
                ->where('guard_name', 'admin')
                ->whereNotIn('name', ['dutyroaster-management'])
                ->with(['child' => function ($q) {
                    $q->orderBy('sorting', 'asc')->orderBy('id', 'asc')->with(['child' => function ($q2) {
                        $q2->orderBy('sorting', 'asc')->orderBy('id', 'asc');
                    }]);
                }])
                ->orderBy('sorting', 'asc')
                ->orderBy('id', 'asc')
                ->get();
    }

    private function ensureDashboardPermissions(): void
    {
        $dashboard = $this->permissionModel->firstOrCreate(
            ['name' => 'dashboard', 'guard_name' => 'admin'],
            ['sorting' => 1]
        );
        $permissionNames = collect([
            'dashboard-card-opd-income',
            'dashboard-card-doctor-visit-appointment',
            'dashboard-card-ipd-income',
            'dashboard-card-pharmacy-income',
            'dashboard-card-pathology-income',
            'dashboard-card-radiology-income',
            'dashboard-card-disposable-income',
            'dashboard-chart-income-by-department',
            'dashboard-chart-income-distribution',
            'dashboard-card-blood-bank-income',
            'dashboard-card-expenses',
            'dashboard-card-pending-income',
            'dashboard-card-referral-commission',
            'dashboard-card-net-income',
            'dashboard-card-total-income',
            'dashboard-card-refunds',
            'dashboard-card-total-discount',
            'dashboard-card-expired-medicines',
            'dashboard-card-expiring-medicines',
            'dashboard-card-product-returns',
        ])->merge(
            TestCategory::query()
                ->whereNull('deleted_at')
                ->where('status', 'Active')
                ->pluck('name')
                ->map(fn ($name) => 'dashboard-card-' . $this->permissionSlug((string) $name) . '-income')
        )->merge(
            MainCategory::query()
                ->whereNull('deleted_at')
                ->where('status', 'Active')
                ->pluck('name')
                ->map(fn ($name) => 'dashboard-main-category-' . $this->permissionSlug((string) $name) . '-income')
        )->filter(fn ($name) => !str_ends_with($name, 'dashboard-card--income')
            && !str_ends_with($name, 'dashboard-main-category--income'))
            ->unique()
            ->values()
            ->all();

        $changed = false;
        foreach ($permissionNames as $name) {
            $permission = $this->permissionModel->firstOrCreate(
                ['name' => $name, 'guard_name' => 'admin'],
                ['parent_id' => $dashboard->id, 'sorting' => 1]
            );
            if ((int) $permission->parent_id !== (int) $dashboard->id) {
                $permission->parent_id = $dashboard->id;
                $permission->save();
                $changed = true;
            }
            $changed = $changed || $permission->wasRecentlyCreated;
        }

        if ($changed) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function removeOrphanedDashboardCategoryPermissions(): void
    {
        $activeCategoryPermissions = TestCategory::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->pluck('name')
            ->map(fn ($name) => 'dashboard-card-' . $this->permissionSlug((string) $name) . '-income')
            ->all();
        $activeMainCategoryPermissions = MainCategory::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->pluck('name')
            ->map(fn ($name) => 'dashboard-main-category-' . $this->permissionSlug((string) $name) . '-income')
            ->all();
        $fixedDashboardPermissions = [
            'dashboard-card-opd-income',
            'dashboard-card-doctor-visit-appointment',
            'dashboard-card-ipd-income',
            'dashboard-card-pharmacy-income',
            'dashboard-card-pathology-income',
            'dashboard-card-radiology-income',
            'dashboard-card-disposable-income',
            'dashboard-card-blood-bank-income',
            'dashboard-card-expenses',
            'dashboard-card-pending-income',
            'dashboard-card-referral-commission',
            'dashboard-card-net-income',
            'dashboard-card-total-income',
            'dashboard-card-refunds',
            'dashboard-card-total-discount',
            'dashboard-card-expired-medicines',
            'dashboard-card-expiring-medicines',
        ];
        $activePermissionNames = array_fill_keys(
            array_merge($activeCategoryPermissions, $activeMainCategoryPermissions, $fixedDashboardPermissions),
            true
        );
        $removedPermission = false;

        $this->permissionModel
            ->where('guard_name', 'admin')
            ->where(function ($query) {
                $query->where('name', 'like', 'dashboard-card-%-income')
                    ->orWhere('name', 'like', 'dashboard-main-category-%-income');
            })
            ->get()
            ->each(function (Permission $permission) use ($activePermissionNames, &$removedPermission): void {
                if (!isset($activePermissionNames[$permission->name])) {
                    $permission->delete();
                    $removedPermission = true;
                }
            });

        if ($removedPermission) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function permissionSlug(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name))), '-');
    }
}
