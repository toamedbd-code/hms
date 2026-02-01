<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PathologyUnitRequest;
use Illuminate\Support\Facades\DB;
use App\Services\PathologyUnitService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class PathologyUnitController extends Controller
{
    use SystemTrait;

    protected $pathologyunitService;

    public function __construct(PathologyUnitService $pathologyunitService)
    {
        $this->pathologyunitService = $pathologyunitService;

        $this->middleware('auth:admin');
        $this->middleware('permission:test-unit-list');
        $this->middleware('permission:test-unit-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:test-unit-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:test-unit-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:test-unit-list-status', ['only' => ['changeStatus']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/PathologyUnit/Index',
            [
                'pageTitle' => fn() => 'Test Unit List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->pathologyunitService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', '%' . request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;
            $user = auth('admin')->user();

            $customData->links = [];

            if ($user->can('test-unit-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.pathologyunit.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('test-unit-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.pathologyunit.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('test-unit-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.pathologyunit.destroy', $data->id),
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
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Name',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/PathologyUnit/Form',
            [
                'pageTitle' => fn() => 'Test Unit Create',
            ]
        );
    }


    public function store(PathologyUnitRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->pathologyunitService->create($data);

            if ($dataInfo) {
                $message = 'Test unit created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'testunits', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Pathology Unit.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyUnitController', 'store', substr($err->getMessage(), 0, 1000));
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
        $pathologyunit = $this->pathologyunitService->find($id);

        return Inertia::render(
            'Backend/PathologyUnit/Form',
            [
                'pageTitle' => fn() => 'Test Unit Edit',
                'pathologyunit' => fn() => $pathologyunit,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(PathologyUnitRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $pathologyunit = $this->pathologyunitService->find($id);

            $dataInfo = $this->pathologyunitService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Test unit updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pathologyunits', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update test unit.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyUnitController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->pathologyunitService->delete($id)) {
                $message = 'Test unit deleted successfully';
                $this->storeAdminWorkLog($id, 'pathologyunits', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete test unit.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyUnitController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->pathologyunitService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Test unit ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pathologyunits', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Test unit.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PathologyUnitController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
