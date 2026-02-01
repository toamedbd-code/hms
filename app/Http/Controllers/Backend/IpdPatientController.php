<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\IpdPatientRequest;
use App\Services\AdminService;
use App\Services\BedGroupService;
use App\Services\BedService;
use Illuminate\Support\Facades\DB;
use App\Services\IpdPatientService;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class IpdPatientController extends Controller
{
    use SystemTrait;

    protected $ipdpatientService, $patientService, $adminService, $bedGroupService, $bedService;

    public function __construct(IpdPatientService $ipdpatientService, PatientService $patientService, AdminService $adminService, BedGroupService $bedGroupService, BedService $bedService)
    {
        $this->ipdpatientService = $ipdpatientService;
        $this->patientService = $patientService;
        $this->adminService = $adminService;
        $this->bedGroupService = $bedGroupService;
        $this->bedService = $bedService;

        $this->middleware('auth:admin');
        $this->middleware('permission:ipd-patient-list');
        $this->middleware('permission:ipd-patient-status', ['only' => ['changeStatus']]);
        $this->middleware('permission:ipd-patient-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:ipd-patient-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:ipd-patient-delete', ['only' => ['destroy']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/IpdPatient/Index',
            [
                'pageTitle' => fn() => 'IpdPatient List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->ipdpatientService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->case = $data->case ?? '';
            $customData->patient_id = $data?->patient?->name ?? '';
            $customData->gender = $data?->patient?->gender ?? '';
            $customData->phone = $data?->patient?->phone;
            $customData->consultant_doctor_id = $data?->doctor?->name ?? '';
            $customData->bed_id = $data?->bed?->name ?? '';
            $customData->is_antenatal = $data->is_antenatal ?? 'No';
            $customData->credit_limit = $data->credit_limit;
            $customData->status = getStatusText($data->status);

            $user = auth('admin')->user();
            $customData->hasLink = true;
            $customData->links = [];

            if ($user && $user->can('ipd-patient-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' .
                        (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.ipdpatient.status.change', [
                        'id' => $data->id,
                        'status' => $data->status == 'Active' ? 'Inactive' : 'Active'
                    ]),
                    'linkLabel' => getLinkLabel(
                        (($data->status == 'Active') ? "Inactive" : "Active"),
                        null,
                        null
                    )
                ];
            }

            if ($user && $user->can('ipd-patient-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.ipdpatient.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user && $user->can('ipd-patient-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.ipdpatient.destroy', $data->id),
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
            ['fieldName' => 'case', 'class' => 'text-center'],
            ['fieldName' => 'patient_id', 'class' => 'text-center'],
            ['fieldName' => 'gender', 'class' => 'text-center'],
            ['fieldName' => 'phone', 'class' => 'text-center'],
            ['fieldName' => 'consultant_doctor_id', 'class' => 'text-center'],
            ['fieldName' => 'bed_id', 'class' => 'text-center'],
            ['fieldName' => 'is_antenatal', 'class' => 'text-center'],
            ['fieldName' => 'credit_limit', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Case',
            'Name',
            'Gender',
            'Phone',
            'Consultant Doctor',
            'Bed',
            'Is Antenatal',
            'Credit Limit',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/IpdPatient/Form',
            [
                'pageTitle' => fn() => 'IpdPatient Create',
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
                'bedGroups' => fn() => $this->bedGroupService->activeList(),
                'beds' => fn() => $this->bedService->activeList()->load('bedGroup'),
            ]
        );
    }


    public function store(IpdPatientRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->ipdpatientService->create($data);

            if ($dataInfo) {
                $message = 'IpdPatient created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'ipdpatients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create IpdPatient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'IpdPatientController', 'store', substr($err->getMessage(), 0, 1000));
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
        $ipdpatient = $this->ipdpatientService->find($id);

        return Inertia::render(
            'Backend/IpdPatient/Form',
            [
                'pageTitle' => fn() => 'IpdPatient Edit',
                'ipdpatient' => fn() => $ipdpatient,
                'id' => fn() => $id,
                'patients' => fn() => $this->patientService->activeList(),
                'doctors' => fn() => $this->adminService->activeDoctors(),
            ]
        );
    }

    public function update(IpdPatientRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $ipdpatient = $this->ipdpatientService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'ipdpatients');
                $path = strstr($ipdpatient->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($ipdpatient->image ?? '', 'ipdpatients');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'ipdpatients/');
                $path = strstr($ipdpatient->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($ipdpatient->file ?? '', 'ipdpatients/');
            }

            $dataInfo = $this->ipdpatientService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'IpdPatient updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'ipdpatients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update ipdpatients.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'IpdPatientController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->ipdpatientService->delete($id)) {
                $message = 'IpdPatient deleted successfully';
                $this->storeAdminWorkLog($id, 'ipdpatients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete IpdPatient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'IpdPatientController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->ipdpatientService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'IpdPatient ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'ipdpatients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "IpdPatient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'IpdPatientController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
