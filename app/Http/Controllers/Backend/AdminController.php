<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequest;
use App\Http\Requests\UserRequest;
use App\Models\AdminDetail;
use Illuminate\Support\Facades\DB;
use App\Services\AdminService;
use App\Services\DepartmentService;
use App\Services\DesignationService;
use App\Services\RoleService;
use App\Services\SpecialistService;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use App\Support\DefaultDeveloperManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    use SystemTrait;

    protected $adminService, $roleService, $designationService, $departmentService, $specialistService;

    public function __construct(AdminService $adminService, RoleService $roleService, DesignationService $designationService, DepartmentService $departmentService, SpecialistService $specialistService)
    {
        $this->adminService = $adminService;
        $this->roleService = $roleService;
        $this->designationService = $designationService;
        $this->departmentService = $departmentService;
        $this->specialistService = $specialistService;

        $this->middleware('auth:admin');
        $this->middleware('permission:admin-list', ['only' => ['index']]);
        $this->middleware('permission:admin-list-status', ['only' => ['changeStatus']]);
        $this->middleware('permission:admin-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:admin-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:admin-list-delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        $user = auth()->guard('admin')->user();
        return Inertia::render(
            'Backend/Admin/Index',
            [
                'pageTitle' => fn() => 'Staff List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
                'roles' => fn() => (function () use ($user) {
                    $roles = $this->roleService->all();
                    if (DefaultDeveloperManager::isDeveloper($user)) {
                        return $roles;
                    }

                    return collect($roles)->filter(function ($r) use ($user) {
                        if (strtolower((string) ($r->name ?? '')) === 'developer') {
                            return false;
                        }

                        return true;
                    })->values();
                })(),
                'filters' => request()->only(['numOfData', 'name', 'division', 'district', 'upazila', 'union']),
                'permissions' => fn() => $user->getAllPermissions()->pluck('name'),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->adminService->list();

        // Join admin_details to include `staff_id` so we can order/serialize by staff identifier
        $query = $query->leftJoin('admin_details as ad', 'ad.admin_id', '=', 'admins.id')
            ->leftJoin('roles as r', 'admins.role_id', '=', 'r.id');

        if (request()->filled('name')) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', '%' . request()->name . '%')
                    ->orWhere('last_name', 'like', '%' . request()->name . '%');
            });
        }

        if (request()->filled('phone'))
            $query->where('phone', 'like', request()->phone . '%');

        if (request()->filled('email'))
            $query->where('email', 'like', request()->email . '%');

        if (request()->filled('role_id'))
            $query->where('role_id', request()->role_id);

        $user = auth()->guard('admin')->user();

        // Apply visibility rules: non-developers should not see developer users.
        // Developers can see all users.
        try {
            if ($user) {
                $isDeveloper = false;
                try {
                    if (method_exists($user, 'hasRole') && $user->hasRole('developer')) {
                        $isDeveloper = true;
                    }
                } catch (\Throwable $_) {
                    $isDeveloper = false;
                }

                if (! $isDeveloper) {
                    $query = $query->whereRaw('LOWER(COALESCE(r.name, "")) <> ?', ['developer']);
                }
            }
        } catch (\Throwable $e) {
            // ignore and proceed without additional filtering
        }

        // Select staff_id and role name explicitly so UI can show them when joins are used
        $query = $query->select('admins.*', 'ad.staff_id as staff_id', 'r.name as role_name');
        $query = $query->orderByRaw('COALESCE(ad.staff_id, admins.id) ASC');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

            $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            // Show the staff identifier (from admin_details.staff_id) when present,
            // otherwise fall back to the numeric admin id. This serializes the list
            // according to the staff ID the user entered.
            $customData->index = $data->staff_id ?? $data->id;
            $customData->name = trim((string) (($data->first_name ?? '') . ' ' . ($data->last_name ?? '')));
            $customData->email = $data->email;
            $customData->phone = $data->phone;
            $customData->password = !empty($data->password) ? 'Set' : 'Not Set';
            // role_name is selected as r.name in the query (alias `role_name`)
            $customData->role_name = $data->role_name ?? null;
            $customData->photo = '<img src="' . $data->photo . '" height="50" width="50"/>';
            $customData->address = $data->address;
            $customData->status = getStatusText($data->status);

            $customData->hasLink = false; 
            $customData->links = [];

            $user = auth()->guard('admin')->user();

            if ($user->can('admin-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.admin.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel(($data->status == 'Active') ? "Inactive" : "Active", null, null)
                ];
            }

            if ($user->can('admin-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.admin.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('admin-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.admin.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ];
            }

            $customData->hasLink = count($customData->links) > 0;

            return $customData;
        });


        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'photo', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'email', 'class' => 'text-center'],
            ['fieldName' => 'phone', 'class' => 'text-center'],
            ['fieldName' => 'password', 'class' => 'text-center'],
            ['fieldName' => 'address', 'class' => 'text-center'],
            ['fieldName' => 'role_name', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Staff ID',
            'Photo',
            'Name',
            'Email',
            'Phone',
            'Password',
            'Address',
            'Role Name',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/Admin/Form',
            [
                'pageTitle' => fn() => 'Basic Information',
                'roles' => fn() => (function () {
                    $user = auth()->guard('admin')->user();
                    $roles = $this->roleService->all();
                    try {
                        if ($user) {
                            $isDeveloper = (method_exists($user, 'hasRole') && $user->hasRole('developer'));
                            if (!$isDeveloper) {
                                $roles = collect($roles)->filter(function ($r) {
                                    return strtolower((string) ($r->name ?? '')) !== 'developer';
                                })->values();
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore and return unfiltered roles
                    }
                    return $roles;
                })(),
                'designations' => fn() => $this->designationService->activeList(),
                'departments' => fn() => $this->departmentService->activeList(),
                'specialists' => fn() => $this->specialistService->activeList(),
            ]
        );
    }

    public function store(AdminRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            if ($request->hasFile('photo')) {
                $data['photo'] = $this->imageUpload($request->file('photo'), 'users');
            }

            $adminData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'role_id' => $data['role_id'],
                'password' => $data['password'],
                'doctor_charge' => $data['doctor_charge'] ?? 0,
                'photo' => $data['photo'] ?? null,
            ];

            // Prevent assigning private roles (like `developer`) to newly created users.
            try {
                $selectedRole = \Spatie\Permission\Models\Role::find($adminData['role_id']);
                // Only block assignment of the explicit `developer` role during creation.
                // Do not blanket-null other private roles (they may be intentional).
                try {
                    if ($selectedRole && strtolower((string) $selectedRole->name) === 'developer') {
                        $fallback = \Spatie\Permission\Models\Role::where('name', 'Admin')->where('guard_name', 'admin')->first();
                        if ($fallback) {
                            $adminData['role_id'] = $fallback->id;
                        } else {
                            // if no fallback, leave role_id unchanged (do not null it)
                        }
                    }
                } catch (\Throwable $_) {
                    // ignore and proceed
                }
            } catch (\Throwable $e) {
                // ignore and proceed
            }

            $admin = $this->adminService->create($adminData);

            // Log incoming create payload and created admin record for debugging
            try {
                \Log::info('AdminController@store: create payload', ['payload' => $adminData]);
                if ($admin) {
                    \Log::info('AdminController@store: created admin', ['admin_id' => $admin->id, 'role_id' => $admin->role_id ?? null]);
                }
            } catch (\Throwable $_) { /* ignore logging failures */ }

            // Safely assign role: only when a valid role_id exists and the role can be found.
            try {
                $roleToAssign = null;
                if (!empty($adminData['role_id'])) {
                    $roleToAssign = \Spatie\Permission\Models\Role::find($adminData['role_id']);
                }

                if ($roleToAssign) {
                    try {
                        // Use role name when syncing roles to avoid id/name ambiguity
                        $admin->syncRoles([$roleToAssign->name]);

                        // Remove any direct permissions so the user's effective
                        // permissions come only from the role.
                        try { $admin->syncPermissions([]); } catch (\Throwable $_) { /* ignore */ }

                        // Persist role_id on the admins table so UI listing shows the assigned role
                        try { $admin->role_id = $roleToAssign->id; $admin->save(); } catch (\Throwable $_) { /* ignore */ }

                        // Clear Spatie permission cache so changes are immediate
                        try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $_) { /* ignore */ }

                        // Reload relations for immediate consistency
                        try { $admin->load('roles', 'permissions'); } catch (\Throwable $_) { /* ignore */ }

                        \Log::info('AdminController@store: assigned role', ['admin_id' => $admin->id, 'role_id' => $roleToAssign->id, 'role_name' => $roleToAssign->name]);
                    } catch (\Throwable $_) {
                        // fallback to best-effort assign using role name
                        try { $admin->assignRole($roleToAssign->name); } catch (\Throwable $__e) { /* ignore */ }
                        try { $admin->role_id = $roleToAssign->id; $admin->save(); } catch (\Throwable $__e) { /* ignore */ }
                    }
                }
            } catch (\Throwable $e) {
                // ignore role assignment failures to avoid breaking the create flow
            }

            if (!$admin) {
                throw new Exception("Failed to create admin record");
            }

            $adminDetailsData = [
                'admin_id' => $admin->id,
                'staff_id' => $data['staff_id'],
                'father_name' => $data['father_name'],
                'mother_name' => $data['mother_name'],
                'gender' => $data['gender'],
                'marital_status' => $data['marital_status'],
                'blood_group' => $data['blood_group'],
                'date_of_birth' => $data['date_of_birth'],
                'date_of_joining' => $data['date_of_joining'],
                'emergency_contact' => $data['emergency_contact'],
                'designation_id' => $data['designation_id'],
                'department_id' => $data['department_id'],
                'specialist_id' => $data['specialist_id'],
                'current_address' => $data['current_address'],
                'permanent_address' => $data['permanent_address'],
                'pan_number' => $data['pan_number'],
                'national_id_number' => $data['national_id_number'],
                'local_id_number' => $data['local_id_number'],
                'qualification' => $data['qualification'],
                'work_experience' => $data['work_experience'],
                'specialization' => $data['specialization'],
                'note' => $data['note'],
                'epf_no' => $data['epf_no'],
                'basic_salary' => $data['basic_salary'],
                'contract_type' => $data['contract_type'],
                'work_shift' => $data['work_shift'],
                'work_location' => $data['work_location'],
                'number_of_leaves' => $data['number_of_leaves'],
                'bank_account_title' => $data['bank_account_title'],
                'bank_account_no' => $data['bank_account_no'],
                'bank_name' => $data['bank_name'],
                'bank_branch_name' => $data['bank_branch_name'],
                'ifsc_code' => $data['ifsc_code'],
                'facebook_url' => $data['facebook_url'],
                'linkedin_url' => $data['linkedin_url'],
                'twitter_url' => $data['twitter_url'],
                'instagram_url' => $data['instagram_url'],
            ];

            $documentFields = [
                'resume' => 'resume_path',
                'joining_letter' => 'joining_letter_path',
                'resignation_letter' => 'resignation_letter_path',
                'other_documents' => 'other_documents_path'
            ];

            foreach ($documentFields as $field => $dbField) {
                if ($request->hasFile($field)) {
                    $adminDetailsData[$dbField] = $this->fileUpload($request->file($field), 'admin_documents');
                }
            }

            $adminDetails = AdminDetail::create($adminDetailsData);

            if (!$adminDetails) {
                throw new Exception("Failed to create staff details record");
            }

            $message = 'Staff created successfully';
            $this->storeAdminWorkLog($admin->id, 'admins', $message);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message)
                ->with('savedPassword', (string) ($data['password'] ?? ''));
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AdminController', 'store', $err->getMessage());
            return redirect()
                ->back()
                ->with('errorMessage', "Server Error: " . $err->getMessage());
        }
    }

    public function edit($id)
    {
        $user = $this->adminService->find($id);
        $this->assertDeveloperRecordVisible($user);

        return Inertia::render(
            'Backend/Admin/Form',
            [
                'pageTitle' => fn() => 'Staff Edit',
                'user' => fn() => $user,
                'id' => fn() => $id,
                'previewSideMenus' => fn() => (function () use ($user) {
                    try {
                        return getSideMenus($user);
                    } catch (\Throwable $e) {
                        return [];
                    }
                })(),
                'roles' => fn() => (function () {
                    $user = auth()->guard('admin')->user();
                    $roles = $this->roleService->all();
                    try {
                        if ($user) {
                            $isDeveloper = (method_exists($user, 'hasRole') && $user->hasRole('developer'));
                            if (!$isDeveloper) {
                                $roles = collect($roles)->filter(function ($r) {
                                    return strtolower((string) ($r->name ?? '')) !== 'developer';
                                })->values();
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore and return unfiltered roles
                    }
                    return $roles;
                })(),
                'designations' => fn() => $this->designationService->activeList(),
                'departments' => fn() => $this->departmentService->activeList(),
                'specialists' => fn() => $this->specialistService->activeList(),
                'adminDetails' => fn() => $this->adminService->adminDetails($id),
                'hasPassword' => fn() => !empty($user?->getRawOriginal('password')),
            ]
        );
    }

    public function update(AdminRequest $request, $id)
    {
        $admin = $this->adminService->find($id);
        $this->assertDeveloperRecordVisible($admin);

        DB::beginTransaction();
        try {
            if (!$admin) {
                throw new Exception("Staff not found");
            }

            $data = $request->validated();

            if (!empty($data['password'])) {
                $data['password'] = $data['password'];
            } else {
                unset($data['password']);
            }

            if ($request->hasFile('photo')) {
                if ($admin->photo) {
                    $oldPhotoPath = $this->cleanFilePath($admin->photo);
                    Storage::delete('public/' . $oldPhotoPath);
                }

                $data['photo'] = $this->imageUpload($request->file('photo'), 'users');
            } else {
                $data['photo'] = $this->cleanFilePath($admin->photo);
            }



            $adminData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'role_id' => $data['role_id'],
                'doctor_charge' => $data['doctor_charge'] ?? 0,
                'photo' => $data['photo'] ?? $admin->photo,
            ];

            if (isset($data['password'])) {
                $adminData['password'] = $data['password'];
            }

            // Only prevent assignment of the `developer` role to other users.
            try {
                $actor = auth()->guard('admin')->user();
                $selectedRole = \Spatie\Permission\Models\Role::find($adminData['role_id']);
                if ($selectedRole && strtolower((string) $selectedRole->name) === 'developer') {
                    // Only allow assigning the developer role if the actor is updating their own account.
                    if (!($actor && isset($actor->id) && $actor->id == (int) $id)) {
                        $fallback = \Spatie\Permission\Models\Role::where('name', 'Admin')->where('guard_name', 'admin')->first();
                        if ($fallback) {
                            $adminData['role_id'] = $fallback->id;
                        } else {
                            $adminData['role_id'] = $actor?->role_id ?? $admin->role_id;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore and proceed
            }

            $adminUpdated = $this->adminService->update($adminData, $id);

            try {
                \Log::info('AdminController@update: after update', ['admin_id' => $adminUpdated->id ?? null, 'role_id' => $adminUpdated->role_id ?? null]);
            } catch (\Throwable $_) { /* ignore */ }

            // Safely assign updated role if applicable
            try {
                $roleToAssign = null;
                if (!empty($adminData['role_id'])) {
                    $roleToAssign = \Spatie\Permission\Models\Role::find($adminData['role_id']);
                }

                if ($roleToAssign) {
                    try {
                        // Sync by name to avoid ambiguity, then persist the role_id field
                        $adminUpdated->syncRoles([$roleToAssign->name]);
                        try { $adminUpdated->syncPermissions([]); } catch (\Throwable $_) { /* ignore */ }
                        try { $adminUpdated->role_id = $roleToAssign->id; $adminUpdated->save(); } catch (\Throwable $_) { /* ignore */ }
                        try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $_) { /* ignore */ }
                        try { $adminUpdated->load('roles', 'permissions'); } catch (\Throwable $_) { /* ignore */ }
                        \Log::info('AdminController@update: assigned role', ['admin_id' => $adminUpdated->id, 'role_id' => $roleToAssign->id, 'role_name' => $roleToAssign->name]);
                    } catch (\Throwable $_) {
                        try { $adminUpdated->assignRole($roleToAssign->name); } catch (\Throwable $__e) { /* ignore */ }
                        try { $adminUpdated->role_id = $roleToAssign->id; $adminUpdated->save(); } catch (\Throwable $__e) { /* ignore */ }
                    }
                }
            } catch (\Throwable $e) {
                // ignore role assignment failures
            }

            if (!$adminUpdated) {
                throw new Exception("Failed to update staff record");
            }

            $adminDetailsData = [
                'staff_id' => $data['staff_id'],
                'father_name' => $data['father_name'],
                'mother_name' => $data['mother_name'],
                'gender' => $data['gender'],
                'marital_status' => $data['marital_status'],
                'blood_group' => $data['blood_group'],
                'date_of_birth' => $data['date_of_birth'],
                'date_of_joining' => $data['date_of_joining'],
                'emergency_contact' => $data['emergency_contact'],
                'designation_id' => $data['designation_id'],
                'department_id' => $data['department_id'],
                'specialist_id' => $data['specialist_id'],
                'current_address' => $data['current_address'],
                'permanent_address' => $data['permanent_address'],
                'pan_number' => $data['pan_number'],
                'national_id_number' => $data['national_id_number'],
                'local_id_number' => $data['local_id_number'],
                'qualification' => $data['qualification'],
                'work_experience' => $data['work_experience'],
                'specialization' => $data['specialization'],
                'note' => $data['note'],
                'epf_no' => $data['epf_no'],
                'basic_salary' => $data['basic_salary'],
                'contract_type' => $data['contract_type'],
                'work_shift' => $data['work_shift'],
                'work_location' => $data['work_location'],
                'number_of_leaves' => $data['number_of_leaves'],
                'bank_account_title' => $data['bank_account_title'],
                'bank_account_no' => $data['bank_account_no'],
                'bank_name' => $data['bank_name'],
                'bank_branch_name' => $data['bank_branch_name'],
                'ifsc_code' => $data['ifsc_code'],
                'facebook_url' => $data['facebook_url'],
                'linkedin_url' => $data['linkedin_url'],
                'twitter_url' => $data['twitter_url'],
                'instagram_url' => $data['instagram_url'],
            ];

            $documentFields = [
                'resume' => 'resume_path',
                'joining_letter' => 'joining_letter_path',
                'resignation_letter' => 'resignation_letter_path',
                'other_documents' => 'other_documents_path'
            ];

            foreach ($documentFields as $field => $dbField) {
                if ($request->hasFile($field)) {
                    if ($admin->details && $admin->details->$dbField) {
                        $oldPath = $this->cleanFilePath($admin->details->$dbField);
                        Storage::delete('public/' . $oldPath);
                    }

                    $adminDetailsData[$dbField] = $this->fileUpload($request->file($field), 'admin_documents');
                } elseif ($admin->details && $admin->details->$dbField) {

                    $adminDetailsData[$dbField] = $this->cleanFilePath($admin->details->$dbField);
                }
            }

            if ($admin->details) {
                $adminDetailsUpdated = $admin->details()->update($adminDetailsData);
            } else {
                $adminDetailsUpdated = $admin->details()->create($adminDetailsData);
            }

            if (!$adminDetailsUpdated) {
                throw new Exception("Failed to update staff details");
            }

            $message = 'Staff updated successfully';
            $this->storeAdminWorkLog($admin->id, 'admins', $message);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message)
                ->with('savedPassword', isset($data['password']) ? (string) $data['password'] : '');
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AdminController', 'update', $err->getMessage());
            return redirect()
                ->back()
                ->with('errorMessage', "Server Error: " . $err->getMessage());
        }
    }

    public function editModules($id)
    {
        $user = $this->adminService->find($id);
        $this->assertDeveloperRecordVisible($user);
        $modules = \App\Models\Module::orderBy('name')->get();
        // limit visible/assignable modules to actor's modules unless developer
        try {
            $actor = auth()->guard('admin')->user();
            if (!($actor && method_exists($actor, 'hasRole') && $actor->hasRole('developer'))) {
                $allowedModuleIds = $actor->modules()->pluck('id')->toArray();
                $modules = $modules->filter(function ($m) use ($allowedModuleIds) {
                    return in_array($m->id, $allowedModuleIds);
                })->values();
            }
        } catch (\Throwable $e) {
            // ignore and show modules as-is
        }
        $assigned = [];
        try {
            $assigned = $user->modules()->pluck('id')->toArray();
        } catch (\Throwable $e) {
            $assigned = [];
        }

        return Inertia::render(
            'Backend/Admin/Modules',
            [
                'pageTitle' => fn() => 'Assign Modules',
                'user' => fn() => $user,
                'modules' => fn() => $modules,
                'assignedModules' => fn() => $assigned,
            ]
        );
    }

    public function updateModules(Request $request, $id)
    {
        $admin = $this->adminService->find($id);
        $this->assertDeveloperRecordVisible($admin);

        DB::beginTransaction();
        try {
            if (!$admin) {
                throw new Exception("Staff not found");
            }

            $submitted = $request->input('modules', []);
            try {
                $actor = auth()->guard('admin')->user();
                if ($actor && method_exists($actor, 'hasRole') && !$actor->hasRole('developer')) {
                    $allowedModuleIds = $actor->modules()->pluck('id')->toArray();
                    $toSync = array_values(array_intersect($allowedModuleIds, $submitted));
                } else {
                    $toSync = $submitted;
                }
            } catch (\Throwable $e) {
                $toSync = [];
            }

            $admin->modules()->sync($toSync);

            $message = 'Modules updated successfully';
            $this->storeAdminWorkLog($admin->id, 'admin_module', $message);
            DB::commit();
            return redirect()->back()->with('successMessage', $message);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AdminController', 'updateModules', $err->getMessage());
            return redirect()->back()->with('errorMessage', "Server Error: " . $err->getMessage());
        }
    }

    protected function cleanFilePath($path)
    {
        if (empty($path)) {
            return null;
        }

        $path = preg_replace('#^https?://[^/]+/#', '', $path);
        $path = str_replace('storage/', '', $path);

        return $path;
    }

    public function destroy($id)
    {
        $target = $this->adminService->find((int) $id);
        $this->assertDeveloperRecordVisible($target);

        DB::beginTransaction();

        try {
            $dataInfo = $this->adminService->delete((int) $id);

            if ($dataInfo) {
                $message = 'Staff deleted successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'admins', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Staff.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AdminController', 'destroy', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function changeStatus()
    {
        $target = $this->adminService->find((int) request()->id);
        $this->assertDeveloperRecordVisible($target);

        DB::beginTransaction();

        try {
            $dataInfo = $this->adminService->changeStatus(request());

            if ($dataInfo->wasChanged()) {
                $message = 'Staff ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'admins', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . " User.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AdminController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    private function assertDeveloperRecordVisible($target): void
    {
        if (!$target) {
            return;
        }

        $actor = auth()->guard('admin')->user();
        if (DefaultDeveloperManager::isDeveloper($target)) {
            if (!DefaultDeveloperManager::isDeveloper($actor)) {
                abort(403, 'You are not allowed to access this user.');
            }
        }
    }
}
