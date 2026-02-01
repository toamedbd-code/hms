<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineGroupRequest;
use Illuminate\Support\Facades\DB;
use App\Services\MedicineGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class MedicineGroupController extends Controller
{
    use SystemTrait;

    protected $medicinegroupService;

    public function __construct(MedicineGroupService $medicinegroupService)
    {
        $this->medicinegroupService = $medicinegroupService;

        $this->middleware('auth:admin');
        $this->middleware('permission:medicine-group-list');
        $this->middleware('permission:medicine-group-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:medicine-group-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:medicine-group-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:medicine-group-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/MedicineGroup/Index',
            [
                'pageTitle' => fn() => 'MedicineGroup List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->medicinegroupService->list();

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

            if ($user->can('medicine-group-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.medicinegroup.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('medicine-group-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.medicinegroup.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('medicine-group-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.medicinegroup.destroy', $data->id),
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
            'Backend/MedicineGroup/Form',
            [
                'pageTitle' => fn() => 'MedicineGroup Create',
            ]
        );
    }


    public function store(MedicineGroupRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->medicinegroupService->create($data);

            if ($dataInfo) {
                $message = 'MedicineGroup created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinegroups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create MedicineGroup.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineGroupController', 'store', substr($err->getMessage(), 0, 1000));
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
        $medicinegroup = $this->medicinegroupService->find($id);

        return Inertia::render(
            'Backend/MedicineGroup/Form',
            [
                'pageTitle' => fn() => 'MedicineGroup Edit',
                'medicinegroup' => fn() => $medicinegroup,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(MedicineGroupRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $medicinegroup = $this->medicinegroupService->find($id);

            $dataInfo = $this->medicinegroupService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'MedicineGroup updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinegroups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update medicinegroups.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineGroupController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->medicinegroupService->delete($id)) {
                $message = 'MedicineGroup deleted successfully';
                $this->storeAdminWorkLog($id, 'medicinegroups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete MedicineGroup.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineGroupController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->medicinegroupService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'MedicineGroup ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinegroups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "MedicineGroup.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineGroupController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
