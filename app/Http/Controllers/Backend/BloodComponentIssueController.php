<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BloodComponentIssueRequest;
use App\Services\AdminService;
use Illuminate\Support\Facades\DB;
use App\Services\BloodComponentIssueService;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class BloodComponentIssueController extends Controller
{
    use SystemTrait;

    protected $bloodcomponentissueService, $adminService, $patientService;

    public function __construct(BloodComponentIssueService $bloodcomponentissueService, AdminService $adminService, PatientService $patientService)
    {
        $this->bloodcomponentissueService = $bloodcomponentissueService;
        $this->adminService = $adminService;
        $this->patientService = $patientService;

        $this->middleware('auth:admin');
        $this->middleware('permission:blood-component-issue-list');
        $this->middleware('permission:blood-component-issue-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:blood-component-issue-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:blood-component-issue-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:blood-component-issue-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/BloodComponentIssue/Index',
            [
                'pageTitle' => fn() => 'Blood ComponentIssue List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->bloodcomponentissueService->list();

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
            $customData->reference_name = $data->reference_name ?? '';
            $customData->blood_group = $data->blood_group ?? '';
            $customData->bag = $data->bag ?? '';
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user->can('blood-component-issue-list-status-change')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.bloodcomponentissue.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('blood-component-issue-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.bloodcomponentissue.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('blood-component-issue-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.bloodcomponentissue.destroy', $data->id),
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
            ['fieldName' => 'reference_name', 'class' => 'text-center'],
            ['fieldName' => 'blood_group', 'class' => 'text-center'],
            ['fieldName' => 'bag', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Patient Name',
            'Issue Date',
            'Doctor',
            'Reference',
            'Blood Group',
            'Bag',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/BloodComponentIssue/Form',
            [
                'pageTitle' => fn() => 'Blood ComponentIssue Create',
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
            ]
        );
    }


    public function store(BloodComponentIssueRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->bloodcomponentissueService->create($data);

            if ($dataInfo) {
                $message = 'Blood ComponentIssue created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bloodcomponentissues', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Blood ComponentIssue.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodComponentIssueController', 'store', substr($err->getMessage(), 0, 1000));
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
        $bloodcomponentissue = $this->bloodcomponentissueService->find($id);

        return Inertia::render(
            'Backend/BloodComponentIssue/Form',
            [
                'pageTitle' => fn() => 'Blood ComponentIssue Edit',
                'bloodcomponentissue' => fn() => $bloodcomponentissue,
                'id' => fn() => $id,
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
            ]
        );
    }

    public function update(BloodComponentIssueRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $bloodcomponentissue = $this->bloodcomponentissueService->find($id);

            $dataInfo = $this->bloodcomponentissueService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Blood Component Issue updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bloodcomponentissues', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update blood componentissues.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodComponentIssueController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->bloodcomponentissueService->delete($id)) {
                $message = 'Blood Component Issue deleted successfully';
                $this->storeAdminWorkLog($id, 'bloodcomponentissues', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Blood Component Issue.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodComponentIssueController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->bloodcomponentissueService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Blood ComponentIssue ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bloodcomponentissues', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Blood Component Issue.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodComponentIssueController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
