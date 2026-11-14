<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\MainCategoryRequest;
use App\Models\MainCategory;
use App\Models\Permission;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MainCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:main-category-list', ['only' => ['index']]);
        $this->middleware('permission:main-category-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:main-category-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:main-category-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:main-category-list-status', ['only' => ['changeStatus']]);
    }

    public function index(Request $request)
    {
        $query = MainCategory::query()->whereNull('deleted_at')->orderBy('name');
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->string('name') . '%');
        }

        $rows = $query->paginate($request->integer('numOfData', 10))->withQueryString();
        $datas = $rows->getCollection()->map(function (MainCategory $category, int $index) {
            $user = auth('admin')->user();
            $links = [];
            if ($user->can('main-category-list-status')) {
                $links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . ($category->status === 'Active' ? 'bg-gray-500' : 'bg-green-500'),
                    'link' => route('backend.maincategory.status.change', [$category->id, $category->status === 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel($category->status === 'Active' ? 'Inactive' : 'Active', null, null),
                ];
            }
            if ($user->can('main-category-list-edit')) {
                $links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.maincategory.edit', $category->id),
                    'linkLabel' => getLinkLabel('Edit', null, null),
                ];
            }
            if ($user->can('main-category-list-delete')) {
                $links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.maincategory.destroy', $category->id),
                    'linkLabel' => getLinkLabel('Delete', null, null),
                ];
            }

            return (object) [
                'index' => $index + 1,
                'category' => $category->name,
                'status' => getStatusText($category->status),
                'hasLink' => true,
                'links' => $links,
            ];
        });

        return Inertia::render('Backend/PathologyCategory/Index', [
            'pageTitle' => 'Main Category List',
            'tableHeaders' => ['Sl/No', 'Main Category', 'Status', 'Action'],
            'dataFields' => [
                ['fieldName' => 'index', 'class' => 'text-center'],
                ['fieldName' => 'category', 'class' => 'text-center'],
                ['fieldName' => 'status', 'class' => 'text-center'],
            ],
            'datas' => regeneratePagination($datas, $rows->total(), $rows->perPage(), $rows->currentPage()),
            'isMainCategory' => true,
        ]);
    }

    public function create()
    {
        return Inertia::render('Backend/PathologyCategory/Form', [
            'pageTitle' => 'Main Category Create',
            'isMainCategory' => true,
            'mainCategory' => null,
        ]);
    }

    public function store(MainCategoryRequest $request)
    {
        $category = MainCategory::create($request->validated() + ['status' => 'Active']);
        $this->syncDashboardPermission($category);
        $redirectTo = $request->input('return_to');
        return $redirectTo
            ? redirect()->to($redirectTo)->with('successMessage', 'Main Category created successfully')
            : redirect()->route('backend.maincategory.index')->with('successMessage', 'Main Category created successfully');
    }

    public function edit(int $id)
    {
        return Inertia::render('Backend/PathologyCategory/Form', [
            'pageTitle' => 'Main Category Edit',
            'isMainCategory' => true,
            'mainCategory' => MainCategory::findOrFail($id),
            'id' => $id,
        ]);
    }

    public function update(MainCategoryRequest $request, int $id)
    {
        $category = MainCategory::findOrFail($id);
        $oldName = trim((string) $category->name);
        $category->update($request->validated());
        $this->syncDashboardPermission($category, $oldName);
        return redirect()->route('backend.maincategory.index')->with('successMessage', 'Main Category updated successfully');
    }

    private function syncDashboardPermission(MainCategory $category, ?string $oldName = null): void
    {
        $dashboardPermission = Permission::query()
            ->where('name', 'dashboard')
            ->where('guard_name', 'admin')
            ->first();
        if (!$dashboardPermission) {
            return;
        }

        $newPermissionName = 'dashboard-main-category-' . $this->permissionSlug($category->name) . '-income';
        $permission = $oldName
            ? Permission::query()
                ->where('name', 'dashboard-main-category-' . $this->permissionSlug($oldName) . '-income')
                ->where('guard_name', 'admin')
                ->first()
            : null;

        if ($permission) {
            $permission->name = $newPermissionName;
            $permission->parent_id = $dashboardPermission->id;
            $permission->save();
        } else {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $newPermissionName, 'guard_name' => 'admin'],
                ['parent_id' => $dashboardPermission->id, 'sorting' => 1]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['Admin', 'developer'] as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'admin')
                ->first();
            if ($role) {
                $role->givePermissionTo($permission->name);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionSlug(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name))), '-');
    }

    public function destroy(int $id)
    {
        MainCategory::findOrFail($id)->delete();
        app(PermissionService::class)->removeOrphanedDashboardCategoryPermissions();
        return redirect()->back()->with('successMessage', 'Main Category deleted successfully');
    }

    public function changeStatus(int $id, string $status)
    {
        MainCategory::findOrFail($id)->update(['status' => $status]);
        return redirect()->back()->with('successMessage', 'Main Category status updated successfully');
    }
}
