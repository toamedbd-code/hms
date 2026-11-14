<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\TestCategory;

use Inertia\Inertia;
use App\Http\Requests\RoleRequest;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\AdminService;
use App\Traits\SystemTrait;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class RoleController extends Controller
{
    use SystemTrait;

    protected $roleService, $permissionService, $AdminService;


    public function __construct(RoleService $roleService, PermissionService $permissionService, AdminService $AdminService)
    {
        $this->roleService = $roleService;
        $this->permissionService = $permissionService;
        $this->AdminService = $AdminService;

        $this->middleware('auth:admin');
        $this->middleware('permission:role-list|role-list-create|role-list-edit|role-list-delete');
        $this->middleware('permission:role-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:role-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:role-list-delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/Role/Index',
            [
                'pageTitle' => fn() => 'Role List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
                'filters' => request()->only(['numOfData', 'name']),
            ]
        );
    }



    private function getDatas()
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ignore cache refresh failures
        }

        $query = $this->roleService->list();

        // Apply visibility rules: non-developers should not see developer role.
        try {
            $actor = auth()->guard('admin')->user();
            if ($actor) {
                try {
                    $actor = $actor->fresh(['roles.permissions', 'permissions']);
                } catch (\Throwable $e) {
                    // ignore and continue with current actor instance
                }
            }

            if ($actor && method_exists($actor, 'hasRole') && !$actor->hasRole('developer')) {
                $query->whereRaw('LOWER(name) <> ?', ['developer']);
            }
        } catch (\Throwable $e) {
            // ignore and continue
        }

        if (request()->filled('name')) {
            $query->where(function ($q) {
                $q->where('name', 'like', request()->name . '%');
            });
        }

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->role_name = $data->name;
            $customData->guard_name = $data->guard_name;

            $customData->hasLink = true;
            $customData->links = [];
            
            $user = auth()->guard('admin')->user();
            try {
                if ($user) {
                    $user = $user->fresh(['roles.permissions', 'permissions']);
                }
            } catch (\Throwable $e) {
                // ignore and keep current user instance
            }

            if ($user && method_exists($user, 'can') && $user->can('role-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.role.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user && method_exists($user, 'can') && $user->can('role-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.role.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ];
            }
            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'role_name', 'class' => 'text-center'],
            ['fieldName' => 'guard_name', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Role Name',
            'Guard Name',
            'Action'
        ];
    }


    public function create()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = $this->permissionService->listWithAllChild();

        // filter permissions by current admin's assigned module slugs
        $actor = auth()->guard('admin')->user();
        $isSuperAdmin = $actor && method_exists($actor, 'hasRole') && $actor->hasRole('super admin');
        // Developers and super admins see all permissions; others are restricted to their modules
        try {
            if ($actor && method_exists($actor, 'hasRole') && ($actor->hasRole('developer') || $isSuperAdmin)) {
                $filtered = $allPermissions;
            } else {
                $allowedModuleSlugs = collect();
                $actorPermissionIds = collect();
                try {
                    if ($actor) {
                        $allowedModuleSlugs = $actor->modules()->pluck('slug')->map(function ($s) {
                            return trim(strtolower((string) $s));
                        })->filter()->values();

                        $actorPermissionIds = $this->includeAssignableModulePermissions($actor->getAllPermissions()->pluck('id')->map(function ($i) {
                            return (int) $i;
                        })->filter()->values());
                    }
                } catch (\Throwable $e) {
                    $allowedModuleSlugs = collect();
                    $actorPermissionIds = collect();
                }

                $filterTree = function ($nodes) use (&$filterTree, $allowedModuleSlugs, $actorPermissionIds) {
                    return $nodes->map(function ($node) use (&$filterTree, $allowedModuleSlugs, $actorPermissionIds) {
                        $nodeModule = trim(strtolower((string) ($node->module_slug ?? '')));
                        $nodeId = (int) ($node->id ?? 0);

                        if ($node->relationLoaded('child') && $node->child) {
                            $node->setRelation('child', $filterTree(collect($node->child))->values());
                        }

                        $childCount = is_array($node->child) ? count($node->child) : (collect($node->child)->count() ?? 0);
                        $hasAllowedChild = $childCount > 0;
                        $nodeIsActorPermission = $nodeId > 0 && $actorPermissionIds->contains($nodeId);

                        // Keep the current node only when it is directly allowed by the actor,
                        // when it has allowed children, or when it is a module parent node.
                        if ($nodeModule !== '') {
                            if ($nodeIsActorPermission || $hasAllowedChild) {
                                return $node;
                            }

                            if ($childCount > 0 && $allowedModuleSlugs->contains($nodeModule)) {
                                return $node;
                            }

                            return null;
                        }

                        if ($nodeIsActorPermission || $hasAllowedChild) {
                            return $node;
                        }

                        return null;
                    })->filter()->values();
                };

                $filtered = $filterTree($allPermissions);
            }
        } catch (\Throwable $e) {
            $filtered = collect();
        }

        // Trim role's initial permission_ids to what the current actor is allowed to see/assign.
        try {
            if (isset($role->permission_ids) && is_array($role->permission_ids)) {
                if (!($actor && method_exists($actor, 'hasRole') && $actor->hasRole('developer'))) {
                    $submittedPermissionIds = $role->permission_ids;

                    $permissionQuery = SpatiePermission::whereIn('id', $submittedPermissionIds)->where('guard_name', 'admin');

                    $actorPermissionIds = collect();
                    try {
                        if ($actor) {
                            $actorPermissionIds = $this->includeAssignableModulePermissions($actor->getAllPermissions()->pluck('id')->map(function ($i) {
                                return (int) $i;
                            })->filter()->values());
                        }
                    } catch (\Throwable $e) {
                        $actorPermissionIds = collect();
                    }

                    // compute allowed module slugs for actor
                    $allowedModuleSlugs = collect();
                    try {
                        if ($actor) {
                            $allowedModuleSlugs = $actor->modules()->pluck('slug')->map(function ($s) {
                                return trim(strtolower((string) $s));
                            })->filter()->values();
                        }
                    } catch (\Throwable $e) {
                        $allowedModuleSlugs = collect();
                    }

                    if (!($allowedModuleSlugs->count() || $actorPermissionIds->count())) {
                        $allowedPermissionIds = [];
                    } else {
                        $permissionQuery->where(function ($q) use ($allowedModuleSlugs, $actorPermissionIds) {
                            if ($allowedModuleSlugs->count()) {
                                $q->whereIn('module_slug', $allowedModuleSlugs->toArray());
                            }
                            if ($actorPermissionIds->count()) {
                                $q->orWhereIn('id', $actorPermissionIds->toArray());
                            }
                        });

                        $allowedPermissionIds = $permissionQuery->pluck('id')->toArray();
                    }

                    $role->permission_ids = array_values(array_intersect($role->permission_ids, $allowedPermissionIds));
                }
            }
        } catch (\Throwable $e) {
            // ignore trimming errors and keep original permission_ids
        }

        // Ensure role's initial permission_ids are limited to what the current actor is allowed to see/assign.
        try {
            if (isset($role->permission_ids) && is_array($role->permission_ids)) {
                if (!($actor && method_exists($actor, 'hasRole') && $actor->hasRole('developer'))) {
                    $submittedPermissionIds = $role->permission_ids;

                    $permissionQuery = SpatiePermission::whereIn('id', $submittedPermissionIds)->where('guard_name', 'admin');

                    $actorPermissionIds = collect();
                    try {
                        if ($actor) {
                            $actorPermissionIds = $this->includeAssignableModulePermissions($actor->getAllPermissions()->pluck('id')->map(function ($i) {
                                return (int) $i;
                            })->filter()->values());
                        }
                    } catch (\Throwable $e) {
                        $actorPermissionIds = collect();
                    }

                    if (!($allowedModuleSlugs->count() || $actorPermissionIds->count())) {
                        $allowedPermissionIds = [];
                    } else {
                        $permissionQuery->where(function ($q) use ($allowedModuleSlugs, $actorPermissionIds) {
                            if ($allowedModuleSlugs->count()) {
                                $q->whereIn('module_slug', $allowedModuleSlugs->toArray());
                            }
                            if ($actorPermissionIds->count()) {
                                $q->orWhereIn('id', $actorPermissionIds->toArray());
                            }
                        });

                        $allowedPermissionIds = $permissionQuery->pluck('id')->toArray();
                    }

                    $role->permission_ids = array_values(array_intersect($role->permission_ids, $allowedPermissionIds));
                }
            }
        } catch (\Throwable $e) {
            // ignore trimming errors and keep original permission_ids
        }

        return Inertia::render(
            'Backend/Role/Form',
            [
                'pageTitle' => fn() => 'Role Create',
                'permissions' => fn() => $filtered,
                'dashboardCategoryPermissionNames' => $this->dashboardCategoryPermissionNames(),
                'canManageAllPermissions' => $actor && method_exists($actor, 'hasRole')
                    && ($actor->hasRole('developer') || $isSuperAdmin),
                'isDeveloper' => $actor && method_exists($actor, 'hasRole')
                    && $actor->hasRole('developer'),
            ]
        );
    }

    public function store(RoleRequest $request)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();

            // If the current admin creating the role is a developer, mark the role as private
            try {
                $actor = auth()->guard('admin')->user();
                if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('developer')) {
                    $data['is_private'] = true;
                    $data['created_by'] = $actor->id;
                }
            } catch (\Throwable $e) {
                // ignore
            }

            $dataInfo = $this->roleService->create($data);
            if ($dataInfo) {
                // Restrict permissions to those belonging to modules actor has access to (or global permissions)
                $actor = auth()->guard('admin')->user();
                $allowedModuleSlugs = collect();
                try {
                    if ($actor) {
                        $allowedModuleSlugs = $actor->modules()->pluck('slug')->map(function ($s) {
                            return trim(strtolower((string) $s));
                        })->filter()->values();
                    }
                } catch (\Throwable $e) {
                    $allowedModuleSlugs = collect();
                }

                $submittedPermissionIds = is_array($request->permission_ids) ? $request->permission_ids : (array) ($request->permission_ids ?? []);

                $permissionQuery = SpatiePermission::whereIn('id', $submittedPermissionIds)->where('guard_name', 'admin');
                // Developers and super admins can assign any permission; others are restricted to their modules
                // and only to permissions they themselves already hold.
                if (!($actor && method_exists($actor, 'hasRole') && ($actor->hasRole('developer') || $actor->hasRole('super admin')))) {
                    $actorPermissionIds = collect();
                    try {
                        if ($actor) {
                            $actorPermissionIds = $this->includeAssignableModulePermissions($actor->getAllPermissions()->pluck('id')->map(function ($i) {
                                return (int) $i;
                            })->filter()->values());
                        }
                    } catch (\Throwable $e) {
                        $actorPermissionIds = collect();
                    }

                    if (!($allowedModuleSlugs->count() || $actorPermissionIds->count())) {
                        $allowedPermissionIds = [];
                    } else {
                        $permissionQuery->where(function ($q) use ($allowedModuleSlugs, $actorPermissionIds) {
                            if ($allowedModuleSlugs->count()) {
                                $q->whereIn('module_slug', $allowedModuleSlugs->toArray());
                            }
                            if ($actorPermissionIds->count()) {
                                $q->orWhereIn('id', $actorPermissionIds->toArray());
                            }
                        });

                        $allowedPermissionIds = $permissionQuery->pluck('id')->toArray();
                    }
                } else {
                    $allowedPermissionIds = $permissionQuery->pluck('id')->toArray();
                }

                app(PermissionRegistrar::class)->forgetCachedPermissions();
                Log::info('RoleController@store: syncPermissions', [
                    'actor_id' => $actor->id ?? null,
                    'role_id' => $dataInfo->id ?? null,
                    'submitted' => $submittedPermissionIds,
                    'allowed' => $allowedPermissionIds,
                ]);

                $this->roleService->syncPermissions($dataInfo->id, $allowedPermissionIds);
                $this->syncRoleAssignablePermissionIds($dataInfo->id, $allowedPermissionIds);
                $message = 'Role created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'roles', $message);

                DB::commit();

                // Flash success message so a full reload preserves the toast
                session()->flash('successMessage', $message);

                // If this was an Inertia request, force a full Inertia location
                // visit so shared props (including `auth.sideMenus`) are
                // re-evaluated by the server and the client receives the
                // updated sidebar snapshot.
                if (request()->header('X-Inertia')) {
                    return Inertia::location(route('backend.role.index'));
                }

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Role.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'RoleController', 'store', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertDeveloperRoleVisible((int) $id);

        $role = $this->roleService->spatieRoleFind($id);

        $allPermissions = $this->permissionService->listWithAllChild();

        // same filtering as create
        $actor = auth()->guard('admin')->user();
        $isSuperAdmin = $actor && method_exists($actor, 'hasRole') && $actor->hasRole('super admin');
        $canManageAllPermissions = $actor && method_exists($actor, 'hasRole')
            && ($actor->hasRole('developer') || $isSuperAdmin);
        try {
            if ($canManageAllPermissions) {
                $filtered = $allPermissions;
            } else {
                $assignablePermissionIds = $this->roleAssignablePermissionIds((int) $id);
                if ($assignablePermissionIds->isEmpty()) {
                    $assignablePermissionIds = $this->actorAssignablePermissionIds($actor);
                } elseif (!$this->actorHasRole($actor, (int) $id)) {
                    $assignablePermissionIds = $assignablePermissionIds
                        ->intersect($this->actorAssignablePermissionIds($actor))
                        ->values();
                }

                $filterTree = function ($nodes) use (&$filterTree, $assignablePermissionIds) {
                    return $nodes->map(function ($node) use (&$filterTree, $assignablePermissionIds) {
                        $nodeId = (int) ($node->id ?? 0);

                        if ($node->relationLoaded('child') && $node->child) {
                            $node->setRelation('child', $filterTree(collect($node->child))->values());
                        }

                        $childCount = is_array($node->child) ? count($node->child) : (collect($node->child)->count() ?? 0);
                        $hasAllowedChild = $childCount > 0;
                        $nodeIsAssignable = $nodeId > 0 && $assignablePermissionIds->contains($nodeId);

                        if ($nodeIsAssignable || $hasAllowedChild) {
                            return $node;
                        }

                        return null;
                    })->filter()->values();
                };

                $filtered = $filterTree($allPermissions);
            }
        } catch (\Throwable $e) {
            $filtered = collect();
        }

        return Inertia::render(
            'Backend/Role/Form',
            [
                'pageTitle' => fn() => 'Role Edit',
                'permissions' => fn() => $filtered,
                'role' => $role,
                'id' =>  $id,
                'dashboardCategoryPermissionNames' => $this->dashboardCategoryPermissionNames(),
                'canManageAllPermissions' => $canManageAllPermissions,
                'isDeveloper' => $actor && method_exists($actor, 'hasRole')
                    && $actor->hasRole('developer'),
            ]
        );

    }

    public function update(RoleRequest $request, $id)
    {
        $this->assertDeveloperRoleVisible((int) $id);

        DB::beginTransaction();
        try {
            $data = $request->validated();

            // Compute allowed permissions BEFORE updating the role to avoid side-effects
            $actor = auth()->guard('admin')->user();
            $submittedPermissionIds = is_array($request->permission_ids) ? $request->permission_ids : (array) ($request->permission_ids ?? []);
            // sanitize ids to integers and remove falsy values
            $submittedPermissionIds = array_values(array_filter(array_map(function ($v) {
                return is_numeric($v) ? (int) $v : null;
            }, $submittedPermissionIds)));

            $canManageAllPermissions = $actor && method_exists($actor, 'hasRole')
                && ($actor->hasRole('developer') || $actor->hasRole('super admin'));
            $roleAssignablePermissionIds = $this->roleAssignablePermissionIds((int) $id);
            if (!$canManageAllPermissions && $roleAssignablePermissionIds->isEmpty()) {
                $roleAssignablePermissionIds = $this->actorAssignablePermissionIds($actor);
            } elseif (!$canManageAllPermissions && !$this->actorHasRole($actor, (int) $id)) {
                $roleAssignablePermissionIds = $roleAssignablePermissionIds
                    ->intersect($this->actorAssignablePermissionIds($actor))
                    ->values();
            }

            $currentRolePermissionIds = $this->roleService->roleHasPermission((int) $id);
            $validSubmittedPermissionIds = SpatiePermission::query()
                ->whereIn('id', $submittedPermissionIds)
                ->where('guard_name', 'admin')
                ->pluck('id')
                ->map(fn ($permissionId) => (int) $permissionId)
                ->all();

            if ($canManageAllPermissions) {
                $allowedPermissionIds = $validSubmittedPermissionIds;
            } else {
                $protectedPermissionIds = array_values(array_diff(
                    $currentRolePermissionIds,
                    $roleAssignablePermissionIds->all()
                ));
                $allowedPermissionIds = array_values(array_unique(array_merge(
                    array_intersect(
                        $validSubmittedPermissionIds,
                        $roleAssignablePermissionIds->all()
                    ),
                    $protectedPermissionIds
                )));
            }

            // Unauthorized check (performed BEFORE persisting role changes)
            try {
                if (!$canManageAllPermissions) {
                    $unauthorized = array_values(array_unique(array_merge(
                        array_diff($submittedPermissionIds, $validSubmittedPermissionIds),
                        array_diff(
                            $validSubmittedPermissionIds,
                            array_merge($roleAssignablePermissionIds->all(), $protectedPermissionIds ?? [])
                        )
                    )));
                    if (count($unauthorized) > 0) {
                        Log::warning('RoleController@update: unauthorized permission selection', [
                            'actor_id' => $actor->id ?? null,
                            'role_id' => $id,
                            'submitted' => $submittedPermissionIds,
                            'allowed' => $allowedPermissionIds,
                            'unauthorized' => $unauthorized,
                        ]);

                        DB::rollBack();
                        return redirect()->back()->with('errorMessage', 'Unauthorized permission selection detected.');
                    }
                }
            } catch (\Throwable $e) {
                Log::error('RoleController@update: exception during unauthorized check', [
                    'actor_id' => $actor->id ?? null,
                    'role_id' => $id,
                    'error' => substr($e->getMessage(), 0, 1000),
                ]);

                DB::rollBack();
                return redirect()->back()->with('errorMessage', 'Unauthorized permission selection detected.');
            }

            // All good — persist role update and sync permissions
            if ($this->roleService->update($data, $id)) {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $role = $this->roleService->syncPermissions($id, $allowedPermissionIds);
                if ($canManageAllPermissions) {
                    $this->syncRoleAssignablePermissionIds($id, $allowedPermissionIds);
                } elseif ($this->roleAssignablePermissionIds((int) $id)->isEmpty()) {
                    $this->syncRoleAssignablePermissionIds($id, $roleAssignablePermissionIds->all());
                }
                $users = $this->AdminService->list()->where('role_id', $id)->get();
                foreach ($users as $key => $user) {
                    try {
                        // Keep any other assigned roles while ensuring the edited role remains assigned.
                        $user->assignRole($role);

                        // Remove any direct permissions to enforce role-scoped permissions
                        try { $user->syncPermissions([]); } catch (\Throwable $_) { /* ignore */ }
                    } catch (\Throwable $_) {
                        // ignore per-user failures
                    }
                }
                try {
                    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
                } catch (\Throwable $_) { /* ignore */ }

                $message = 'Role updated successfully';
                $this->storeAdminWorkLog($id, 'roles', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update Role.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'RoleController', 'update', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function includeAssignableModulePermissions($permissionIds)
    {
        $assignableRootNames = [
            'billing',
            'billing-add',
            'prescription-management',
        ];
        $assignableParentIds = SpatiePermission::query()
            ->whereIn('name', $assignableRootNames)
            ->where('guard_name', 'admin')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $permissionIds->contains($id));

        if ($assignableParentIds->isEmpty()) {
            return $permissionIds;
        }

        $childPermissionIds = SpatiePermission::query()
            ->where('guard_name', 'admin')
            ->whereIn('parent_id', $assignableParentIds->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        return $permissionIds->merge($childPermissionIds)->unique()->values();
    }

    private function actorAssignablePermissionIds($actor)
    {
        if (!$actor) {
            return collect();
        }

        $permissionIds = $this->includeAssignableModulePermissions(
            $actor->getAllPermissions()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
        );

        return SpatiePermission::query()
            ->where('guard_name', 'admin')
            ->whereIn('id', $permissionIds->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function roleAssignablePermissionIds(int $roleId)
    {
        return DB::table('role_assignable_permissions')
            ->where('role_id', $roleId)
            ->pluck('permission_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function actorHasRole($actor, int $roleId): bool
    {
        return $actor
            && $actor->roles()->whereKey($roleId)->exists();
    }

    private function syncRoleAssignablePermissionIds(int $roleId, array $permissionIds): void
    {
        DB::table('role_assignable_permissions')
            ->where('role_id', $roleId)
            ->delete();

        $rows = collect($permissionIds)
            ->map(fn ($permissionId) => (int) $permissionId)
            ->filter(fn ($permissionId) => $permissionId > 0)
            ->unique()
            ->map(fn ($permissionId) => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('role_assignable_permissions')->insert($chunk->all());
        }
    }

    private function dashboardCategoryPermissionNames(): array
    {
        return TestCategory::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->pluck('name')
            ->map(fn ($name) => 'dashboard-card-' . trim(
                (string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim((string) $name))),
                '-'
            ) . '-income')
            ->filter(fn ($name) => $name !== 'dashboard-card--income')
            ->unique()
            ->values()
            ->all();
    }

    public function destroy($id)
    {

        $this->assertDeveloperRoleVisible((int) $id);

        DB::beginTransaction();

        try {
            // $dataInfo = $this->roleService->delete($id);

            if ($this->roleService->delete($id)) {
                $message = 'Role deleted successfully';
                $this->storeAdminWorkLog($id, 'roles', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Role.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'RoleController', 'destroy', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function changeStatus()
    {
        $this->assertDeveloperRoleVisible((int) request()->id);

        DB::beginTransaction();

        try {
            $dataInfo = $this->roleService->changeStatus(request());

            if ($dataInfo->wasChanged()) {
                $message = 'Role ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'roles', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . " Role.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'RoleController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function assertDeveloperRoleVisible(int $roleId): void
    {
        if ($roleId <= 0) {
            return;
        }

        $actor = auth()->guard('admin')->user();
        $isActorDeveloper = false;
        try {
            $isActorDeveloper = $actor && method_exists($actor, 'hasRole') && $actor->hasRole('developer');
        } catch (\Throwable $e) {
            $isActorDeveloper = false;
        }

        if ($isActorDeveloper) {
            return;
        }

        try {
            $role = \Spatie\Permission\Models\Role::query()
                ->where('guard_name', 'admin')
                ->find($roleId);

            if ($role && strtolower((string) ($role->name ?? '')) === 'developer') {
                abort(403, 'You are not allowed to access this role.');
            }
        } catch (\Throwable $e) {
            // ignore lookup failures
        }
    }
}
