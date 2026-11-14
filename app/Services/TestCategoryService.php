<?php
namespace App\Services;
use App\Models\TestCategory;
use App\Models\Test;
use App\Models\BillItem;
use App\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class TestCategoryService
{
    protected $testCategoryModel;

    public function __construct(TestCategory $testCategoryModel)
    {
        $this->testCategoryModel = $testCategoryModel;
    }

    public function list()
    {
        return $this->testCategoryModel
            ->with('parent')
            ->whereNull('deleted_at')
            ->orderBy('name');
    }

    public function all()
    {
        return  $this->testCategoryModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->testCategoryModel->find($id);
    }

    public function create(array $data)
    {
        $category = $this->testCategoryModel->create($data);
        $this->syncDashboardPermission($category);

        return $category;
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->testCategoryModel->findOrFail($id);
        $oldName = trim((string) $dataInfo->name);
        $newName = trim((string) ($data['name'] ?? $oldName));

        $dataInfo->update($data);

        if ($oldName !== '' && $oldName !== $newName) {
            Test::whereRaw('LOWER(category_type) = ?', [strtolower($oldName)])
                ->update(['category_type' => $newName]);
            BillItem::whereRaw('LOWER(category) = ?', [strtolower($oldName)])
                ->update(['category' => $newName]);
        }

        $this->syncDashboardPermission($dataInfo, $oldName);

        return $dataInfo;
    }

    private function syncDashboardPermission(TestCategory $category, ?string $oldName = null): void
    {
        $dashboardPermission = Permission::firstOrCreate(
            ['name' => 'dashboard', 'guard_name' => 'admin'],
            ['parent_id' => null, 'sorting' => 1]
        );

        $newPermissionName = $this->dashboardPermissionName($category->name);
        $permission = $oldName
            ? Permission::where('name', $this->dashboardPermissionName($oldName))->where('guard_name', 'admin')->first()
            : null;

        if ($permission) {
            $permission->name = $newPermissionName;
            $permission->parent_id = $dashboardPermission->id;
            $permission->save();
        } else {
            $permission = Permission::firstOrCreate(
                ['name' => $newPermissionName, 'guard_name' => 'admin'],
                ['parent_id' => $dashboardPermission->id, 'sorting' => 1]
            );
        }

        if (
            (int) ($permission->parent_id ?? 0) !== (int) $dashboardPermission->id
            || (int) ($permission->sorting ?? 0) !== 1
        ) {
            $permission->parent_id = $dashboardPermission->id;
            $permission->sorting = 1;
            $permission->save();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function dashboardPermissionName(string $categoryName): string
    {
        $slug = strtolower(trim($categoryName));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return 'dashboard-card-' . trim($slug, '-') . '-income';
    }

    public function delete($id)
    {
        $dataInfo =  $this->testCategoryModel->find($id);

        if (!empty($dataInfo)) {
            $deletedAt = date('Y-m-d H:i:s');
            $categoryIds = collect([$dataInfo->id]);
            $pendingIds = collect([$dataInfo->id]);

            while ($pendingIds->isNotEmpty()) {
                $childIds = $this->testCategoryModel
                    ->whereIn('parent_id', $pendingIds->all())
                    ->pluck('id');

                $pendingIds = $childIds->diff($categoryIds)->values();
                $categoryIds = $categoryIds->merge($pendingIds)->unique()->values();
            }

            $deleted = $this->testCategoryModel
                ->whereIn('id', $categoryIds->all())
                ->update([
                    'deleted_at' => $deletedAt,
                    'status' => 'Deleted',
                ]) > 0;

            if ($deleted) {
                app(PermissionService::class)->removeOrphanedDashboardCategoryPermissions();
            }

            return $deleted;
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->testCategoryModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->testCategoryModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return $this->testCategoryModel
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();
    }

    public function activeNames(): array
    {
        return $this->testCategoryModel
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => strtolower($name))
            ->sort()
            ->values()
            ->all();
    }

}
