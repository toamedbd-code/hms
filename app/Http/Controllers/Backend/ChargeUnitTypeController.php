<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChargeUnitTypeRequest;
use Illuminate\Support\Facades\DB;
use App\Services\ChargeUnitTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class ChargeUnitTypeController extends Controller
{
    use SystemTrait;

    protected $chargeunittypeService;

    public function __construct(ChargeUnitTypeService $chargeunittypeService)
    {
        $this->chargeunittypeService = $chargeunittypeService;

        $this->middleware('auth:admin');
        $this->middleware('permission:charge-unit-type-list');
        $this->middleware('permission:charge-unit-type-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:charge-unit-type-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:charge-unit-type-delete', ['only' => ['destroy']]);
        $this->middleware('permission:charge-unit-type-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/ChargeUnitType/Index',
            [
                'pageTitle' => fn() => 'Charge Unit Type List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->chargeunittypeService->list();

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

            if ($user->can('charge-unit-type-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.chargeunittype.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('charge-unit-type-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.chargeunittype.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('charge-unit-type-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.chargeunittype.destroy', $data->id),
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
            'Backend/ChargeUnitType/Form',
            [
                'pageTitle' => fn() => 'Charge Unit Type Create',
            ]
        );
    }


    public function store(ChargeUnitTypeRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->chargeunittypeService->create($data);

            if ($dataInfo) {
                $message = 'Charge Unit Type created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'chargeunittypes', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Charge Unit Type.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeUnitTypeController', 'store', substr($err->getMessage(), 0, 1000));
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
        $chargeunittype = $this->chargeunittypeService->find($id);

        return Inertia::render(
            'Backend/ChargeUnitType/Form',
            [
                'pageTitle' => fn() => 'Charge Unit Type Edit',
                'chargeunittype' => fn() => $chargeunittype,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(ChargeUnitTypeRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $chargeunittype = $this->chargeunittypeService->find($id);

            $dataInfo = $this->chargeunittypeService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Charge Unit Type updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'chargeunittypes', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update charge unit types.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeUnitTypeController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->chargeunittypeService->delete($id)) {
                $message = 'Charge Unit Type deleted successfully';
                $this->storeAdminWorkLog($id, 'chargeunittypes', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Charge Unit Type.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeUnitTypeController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->chargeunittypeService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Charge Unit Type ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'chargeunittypes', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Charge Unit Type.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeUnitTypeController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}