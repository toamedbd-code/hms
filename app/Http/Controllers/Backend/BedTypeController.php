<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BedTypeRequest;
use Illuminate\Support\Facades\DB;
use App\Services\BedTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class BedTypeController extends Controller
{
    use SystemTrait;

    protected $bedtypeService;

    public function __construct(BedTypeService $bedtypeService)
    {
        $this->bedtypeService = $bedtypeService;

        $this->middleware('auth:admin');
        $this->middleware('permission:bed-type-list');
        // PermissionSeeder generates permissions like: bed-type-list-create/edit/delete/status
        $this->middleware('permission:bed-type-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:bed-type-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:bed-type-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:bed-type-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/BedType/Index',
            [
                'pageTitle' => fn() => 'BedType List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->bedtypeService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;

            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;

            $user = auth('admin')->user();

            $customData->links = [];

            if ($user->can('bed-type-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.bedtype.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('bed-type-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.bedtype.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('bed-type-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.bedtype.destroy', $data->id),
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
            'Backend/BedType/Form',
            [
                'pageTitle' => fn() => 'Bed Type Create',
            ]
        );
    }


    public function store(BedTypeRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->bedtypeService->create($data);

            if ($dataInfo) {
                $message = 'Bed Type created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bedtypes', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Bed Type.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedTypeController', 'store', substr($err->getMessage(), 0, 1000));
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
        $bedtype = $this->bedtypeService->find($id);

        return Inertia::render(
            'Backend/BedType/Form',
            [
                'pageTitle' => fn() => 'Bed Type Edit',
                'bedtype' => fn() => $bedtype,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(BedTypeRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $bedtype = $this->bedtypeService->find($id);

            $dataInfo = $this->bedtypeService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Bed Type updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bedtypes', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update bedtypes.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedTypeController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->bedtypeService->delete($id)) {
                $message = 'Bed Type deleted successfully';
                $this->storeAdminWorkLog($id, 'bedtypes', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete BedType.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedTypeController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->bedtypeService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'BedType ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bedtypes', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "BedType.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BedTypeController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
