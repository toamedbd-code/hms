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
use Exception;
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
                'roles' => fn() => $this->roleService->all(),
                'filters' => request()->only(['numOfData', 'name', 'division', 'district', 'upazila', 'union']),
                'permissions' => fn() => $user->getAllPermissions()->pluck('name'),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->adminService->list();

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
            $query->where('role', request()->role_id);

        $user = auth()->guard('admin')->user();
        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->email = $data->email;
            $customData->phone = $data->phone;
            $customData->role_name = $data->role?->name;
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
            ['fieldName' => 'address', 'class' => 'text-center'],
            ['fieldName' => 'role_name', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Photo',
            'Name',
            'Email',
            'Phone',
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
                'roles' => fn() => $this->roleService->all(),
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

            $admin = $this->adminService->create($adminData);
            $admin->assignRole($admin->role->name);

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
                ->with('successMessage', $message);
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

        return Inertia::render(
            'Backend/Admin/Form',
            [
                'pageTitle' => fn() => 'Staff Edit',
                'user' => fn() => $user,
                'id' => fn() => $id,
                'roles' => fn() => $this->roleService->all(),
                'designations' => fn() => $this->designationService->activeList(),
                'departments' => fn() => $this->departmentService->activeList(),
                'specialists' => fn() => $this->specialistService->activeList(),
                'adminDetails' => fn() => $this->adminService->adminDetails($id)
            ]
        );
    }

    public function update(AdminRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $admin = $this->adminService->find($id);

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

            $adminUpdated = $this->adminService->update($adminData, $id);
            $adminUpdated->assignRole($adminUpdated->role->name);

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
                ->with('successMessage', $message);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AdminController', 'update', $err->getMessage());
            return redirect()
                ->back()
                ->with('errorMessage', "Server Error: " . $err->getMessage());
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
}
