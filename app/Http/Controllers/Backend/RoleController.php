<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;

use Inertia\Inertia;
use App\Http\Requests\RoleRequest;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\AdminService;
use App\Traits\SystemTrait;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;
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
        $this->middleware('permission:role-list');
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
        $query = $this->roleService->list();

        // Apply visibility rules: if current actor is NOT developer, hide private roles
        try {
            $actor = auth()->guard('admin')->user();
            if ($actor && method_exists($actor, 'hasRole') && !$actor->hasRole('developer')) {
                $currentRoleId = $actor->role_id;
                $query->where(function ($q) use ($currentRoleId) {
                    $q->whereNull('is_private')
                        ->orWhere('is_private', false)
                        ->orWhere('id', $currentRoleId);
                });
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

            if ($user->can('role-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.role.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('role-list-delete')) {
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
        // Developers see all permissions; others are restricted to their modules
        try {
            if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('developer')) {
                $filtered = $allPermissions;
            } else {
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

                $filterTree = function ($nodes) use (&$filterTree, $allowedModuleSlugs) {
                    return $nodes->map(function ($node) use (&$filterTree, $allowedModuleSlugs) {
                        $nodeModule = trim(strtolower((string) ($node->module_slug ?? '')));

                        // filter children recursively
                        if ($node->relationLoaded('child') && $node->child) {
                            $node->child = $filterTree(collect($node->child))->filter()->values()->toArray();
                        }

                        $childCount = is_array($node->child) ? count($node->child) : (collect($node->child)->count() ?? 0);
                        $hasAllowedChild = $childCount > 0;

                        // If node has an explicit module slug, keep it only if the module is allowed or any child remains
                        if ($nodeModule !== '') {
                            if ($allowedModuleSlugs->contains($nodeModule) || $hasAllowedChild) {
                                return $node;
                            }
                            return null;
                        }

                        // nodeModule is empty (global group): keep only when it's a standalone global permission (no children)
                        // or when it has allowed children after recursive filtering. This prevents showing empty groups.
                        if ($childCount === 0 || $hasAllowedChild) {
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
                'pageTitle' => fn() => 'Role Create',
                'permissions' => fn() => $filtered,
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
                // Developers can assign any permission; others are restricted to their modules
                // and only to global permissions they themselves already hold.
                if (!($actor && method_exists($actor, 'hasRole') && $actor->hasRole('developer'))) {
                    $actorPermissionIds = collect();
                    try {
                        if ($actor) {
                            $actorPermissionIds = $actor->getAllPermissions()->pluck('id')->map(function ($i) {
                                return (int) $i;
                            })->filter()->values();
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
                $this->roleService->syncPermissions($dataInfo->id, $allowedPermissionIds);
                $message = 'Role created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'roles', $message);

                DB::commit();

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

        $role = $this->roleService->spatieRoleFind($id);

        $allPermissions = $this->permissionService->listWithAllChild();

        // same filtering as create
        $actor = auth()->guard('admin')->user();
        try {
            if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('developer')) {
                $filtered = $allPermissions;
            } else {
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

                $filterTree = function ($nodes) use (&$filterTree, $allowedModuleSlugs) {
                    return $nodes->map(function ($node) use (&$filterTree, $allowedModuleSlugs) {
                        $nodeModule = trim(strtolower((string) ($node->module_slug ?? '')));

                        if ($node->relationLoaded('child') && $node->child) {
                            $node->child = $filterTree(collect($node->child))->filter()->values()->toArray();
                        }

                        $childCount = is_array($node->child) ? count($node->child) : (collect($node->child)->count() ?? 0);
                        $hasAllowedChild = $childCount > 0;

                        if ($nodeModule !== '') {
                            if ($allowedModuleSlugs->contains($nodeModule) || $hasAllowedChild) {
                                return $node;
                            }
                            return null;
                        }

                        if ($childCount === 0 || $hasAllowedChild) {
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
            ]
        );
    }

    public function update(RoleRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            if ($this->roleService->update($data, $id)) {
                // Restrict permissions to actor's modules (same logic as store)
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
                if (!($actor && method_exists($actor, 'hasRole') && $actor->hasRole('developer'))) {
                    $actorPermissionIds = collect();
                    try {
                        if ($actor) {
                            $actorPermissionIds = $actor->getAllPermissions()->pluck('id')->map(function ($i) {
                                return (int) $i;
                            })->filter()->values();
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

                try {
                    if (!($actor && method_exists($actor, 'hasRole') && $actor->hasRole('developer'))) {
                        $unauthorized = array_values(array_diff($submittedPermissionIds, $allowedPermissionIds));
                        if (count($unauthorized) > 0) {
                            DB::rollBack();
                            return redirect()->back()->with('errorMessage', 'Unauthorized permission selection detected.');
                        }
                    }
                } catch (\Throwable $e) {
                    DB::rollBack();
                    return redirect()->back()->with('errorMessage', 'Unauthorized permission selection detected.');
                }

                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $role = $this->roleService->syncPermissions($id, $allowedPermissionIds);
                $users = $this->AdminService->list()->where('status', 'Active')->where('role_id', $id)->get();
                //dd($permissions);
                foreach ($users as $key => $user)
                    $user->syncRoles($role->id);

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

    public function destroy($id)
    {

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
}
