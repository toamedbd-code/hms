<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BloodIssueRequest;
use App\Services\AdminService;
use Illuminate\Support\Facades\DB;
use App\Services\BloodIssueService;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class BloodIssueController extends Controller
{
    use SystemTrait;

    protected $bloodissueService, $patientService, $adminService;

    public function __construct(BloodIssueService $bloodissueService, AdminService $adminService, PatientService $patientService)
    {
        $this->bloodissueService = $bloodissueService;
        $this->adminService = $adminService;
        $this->patientService = $patientService;

        $this->middleware('auth:admin');
        $this->middleware('permission:blood-issue-list');
        $this->middleware('permission:blood-issue-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:blood-issue-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:blood-issue-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:blood-issue-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/BloodIssue/Index',
            [
                'pageTitle' => fn() => 'BloodIssue List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->bloodissueService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();
        
        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->patient_id = $data?->patient?->name ?? '';
            $customData->issue_date = $data->issue_date ?? '';
            $customData->doctor_id = $data?->doctor?->name ?? '';
            $customData->blood_group = $data->blood_group ?? '';
            $customData->bag = $data->bag ?? '';
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user->can('blood-issue-list-status-change')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.bloodissue.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('blood-issue-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.bloodissue.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('blood-issue-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.bloodissue.destroy', $data->id),
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
            ['fieldName' => 'patient_id', 'class' => 'text-center'],
            ['fieldName' => 'issue_date', 'class' => 'text-center'],
            ['fieldName' => 'doctor_id', 'class' => 'text-center'],
            ['fieldName' => 'blood_group', 'class' => 'text-center'],
            ['fieldName' => 'bag', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Patient',
            'Issue Date',
            'Doctor',
            'Blood Group',
            'Bag',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/BloodIssue/Form',
            [
                'pageTitle' => fn() => 'BloodIssue Create',
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
            ]
        );
    }


    public function store(BloodIssueRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->bloodissueService->create($data);

            if ($dataInfo) {
                $message = 'BloodIssue created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bloodissues', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create BloodIssue.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodIssueController', 'store', substr($err->getMessage(), 0, 1000));
            //dd($err);
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            // dd($message);
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $bloodissue = $this->bloodissueService->find($id);

        return Inertia::render(
            'Backend/BloodIssue/Form',
            [
                'pageTitle' => fn() => 'BloodIssue Edit',
                'bloodissue' => fn() => $bloodissue,
                'id' => fn() => $id,
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
            ]
        );
    }

    public function update(BloodIssueRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $bloodissue = $this->bloodissueService->find($id);

            $dataInfo = $this->bloodissueService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'BloodIssue updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bloodissues', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update bloodissues.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodIssueController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->bloodissueService->delete($id)) {
                $message = 'BloodIssue deleted successfully';
                $this->storeAdminWorkLog($id, 'bloodissues', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete BloodIssue.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodIssueController', 'destroy', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function changeStatus(Request $request, $id, $status)
    {
        DB::beginTransaction();

        try {

            $dataInfo = $this->bloodissueService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'BloodIssue ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bloodissues', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "BloodIssue.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodIssueController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
