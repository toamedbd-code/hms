<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PathologyParameterRequest;
use Illuminate\Support\Facades\DB;
use App\Services\PathologyParameterService;
use App\Services\PathologyUnitService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class PathologyParameterController extends Controller
{
    use SystemTrait;

    protected $pathologyparameterService, $pathologyUnitService;

    public function __construct(PathologyParameterService $pathologyparameterService, PathologyUnitService $pathologyUnitService)
    {
        $this->pathologyparameterService = $pathologyparameterService;
        $this->pathologyUnitService = $pathologyUnitService;

        $this->middleware('auth:admin');
        $this->middleware('permission:test-parameter-list');
        $this->middleware('permission:test-parameter-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:test-parameter-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:test-parameter-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:test-parameter-list-status', ['only' => ['changeStatus']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/PathologyParameter/Index',
            [
                'pageTitle' => fn() => 'Test Parameter List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->pathologyparameterService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', '%' . request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->referance_from = $data->referance_from;
            $customData->referance_to = $data->referance_to;
            $customData->pathology_unit_id = $data?->pathologyUnit?->name ?? '';
            $customData->description = $data->description;

            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;
            $user = auth('admin')->user();
            
            $customData->links = [];

            if ($user->can('test-parameter-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.parameterofpathology.status.change', [
                        'id' => $data->id,
                        'status' => $data->status == 'Active' ? 'Inactive' : 'Active'
                    ]),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('test-parameter-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.parameterofpathology.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('test-parameter-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.parameterofpathology.destroy', $data->id),
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
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'referance_from', 'class' => 'text-center'],
            ['fieldName' => 'referance_to', 'class' => 'text-center'],
            ['fieldName' => 'pathology_unit_id', 'class' => 'text-center'],
            ['fieldName' => 'description', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Name',
            'Referance From',
            'Referance To',
            'Unit',
            'Description',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/PathologyParameter/Form',
            [
                'pageTitle' => fn() => 'Test Parameter Create',
                'units' => fn() => $this->pathologyUnitService->activeList()
            ]
        );
    }


    public function store(PathologyParameterRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->pathologyparameterService->create($data);

            if ($dataInfo) {
                $message = 'Test Parameter created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'testparameters', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Test Parameter.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyParameterController', 'store', substr($err->getMessage(), 0, 1000));
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
        $pathologyparameter = $this->pathologyparameterService->find($id);

        return Inertia::render(
            'Backend/PathologyParameter/Form',
            [
                'pageTitle' => fn() => 'Test Parameter Edit',
                'pathologyparameter' => fn() => $pathologyparameter,
                'id' => fn() => $id,
                'units' => fn() => $this->pathologyUnitService->activeList()

            ]
        );
    }

    public function update(PathologyParameterRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $pathologyparameter = $this->pathologyparameterService->find($id);

            $dataInfo = $this->pathologyparameterService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Test Parameter updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pathologyparameters', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update test parameters.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyParameterController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->pathologyparameterService->delete($id)) {
                $message = 'Test Parameter deleted successfully';
                $this->storeAdminWorkLog($id, 'pathologyparameters', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Test Parameter.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyParameterController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->pathologyparameterService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Test Parameter ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pathologyparameters', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Test Parameter.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyParameterController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
