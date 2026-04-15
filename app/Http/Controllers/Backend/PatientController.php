<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientRequest;
use Illuminate\Support\Facades\DB;
use App\Services\PatientService;
use App\Services\TpaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class PatientController extends Controller
{
    use SystemTrait;

    protected $patientService, $tpaService;

    public function __construct(PatientService $patientService, TpaService $tpaService)
    {
        $this->patientService = $patientService;
        $this->tpaService = $tpaService;

        $this->middleware('auth:admin');
        $this->middleware('permission:patient-list');
        $this->middleware('permission:patient-list-status', ['only' => ['changeStatus']]);
        $this->middleware('permission:patient-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:patient-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:patient-list-delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/Patient/Index',
            [
                'pageTitle' => fn() => 'Patient List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->patientService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->photo = '<img src="' . $data->photo . '" height="50" width="50"/>';
            $customData->name = $data->name;
            $customData->age = $data->age;
            $customData->gender = $data->gender;
            $customData->phone = $data->phone;
            $customData->guardian_name = $data->guardian_name;
            $customData->address = $data->address;
            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;
            $customData->links = [];
            
            $user = auth()->guard('admin')->user();

            if ($user->can('patient-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.patient.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('patient-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.patient.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('patient-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.patient.destroy', $data->id),
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
            ['fieldName' => 'photo', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'age', 'class' => 'text-center'],
            ['fieldName' => 'gender', 'class' => 'text-center'],
            ['fieldName' => 'phone', 'class' => 'text-center'],
            ['fieldName' => 'guardian_name', 'class' => 'text-center'],
            ['fieldName' => 'address', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Photo',
            'Name',
            'Age',
            'Gender',
            'Phone',
            'Guardian Name',
            'Address',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/Patient/Form',
            [
                'pageTitle' => fn() => 'Patient Create',
                'tpas' => fn() => $this->tpaService->activeList()->get()
            ]
        );
    }


    public function store(PatientRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('photo'))
                $data['photo'] = $this->imageUpload($request->file('photo'), 'patients');


            $dataInfo = $this->patientService->create($data);

            if ($dataInfo) {
                $message = 'Patient created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'patients', $message);

                DB::commit();

                // If this was a non-Inertia AJAX request, return JSON with the created
                // patient so the frontend can select it. Do NOT return plain JSON for
                // Inertia requests because Inertia expects a proper Inertia response.
                if ($request->wantsJson() && !$request->header('X-Inertia')) {
                    return response()->json([
                        'patient' => $dataInfo,
                        'successMessage' => $message,
                    ]);
                }

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Patient.";
                if ($request->wantsJson() && !$request->header('X-Inertia')) {
                    return response()->json(['errorMessage' => $message], 500);
                }

                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'PatientController', 'store', substr($err->getMessage(), 0, 1000));
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
        $patient = $this->patientService->find($id);

        return Inertia::render(
            'Backend/Patient/Form',
            [
                'pageTitle' => fn() => 'Patient Edit',
                'patient' => fn() => $patient,
                'id' => fn() => $id,
                'tpas' => fn() => $this->tpaService->activeList()->get()
            ]
        );
    }

    public function update(PatientRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $patient = $this->patientService->find($id);

            if ($request->hasFile('photo')) {
                $data['photo'] = $this->imageUpload($request->file('photo'), 'patients');
                $path = strstr($patient->photo, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['photo'] = strstr($patient->photo ?? '', 'patients');
            }

            $dataInfo = $this->patientService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Patient updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'patients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update patients.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PatientController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->patientService->delete($id)) {
                $message = 'Patient deleted successfully';
                $this->storeAdminWorkLog($id, 'patients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Patient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PatientController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->patientService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Patient ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'patients', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Patient.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PatientController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
