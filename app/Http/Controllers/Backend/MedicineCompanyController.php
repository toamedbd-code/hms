<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineCompanyRequest;
use Illuminate\Support\Facades\DB;
use App\Services\MedicineCompanyService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class MedicineCompanyController extends Controller
{
    use SystemTrait;

    protected $medicinecompanyService;

    public function __construct(MedicineCompanyService $medicinecompanyService)
    {
        $this->medicinecompanyService = $medicinecompanyService;

        $this->middleware('auth:admin');
        $this->middleware('permission:medicine-company-list');
        $this->middleware('permission:medicine-company-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:medicine-company-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:medicine-company-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:medicine-company-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/MedicineCompany/Index',
            [
                'pageTitle' => fn() => 'Medicine Company List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->medicinecompanyService->list();

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

            if ($user->can('medicine-company-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.medicinecompany.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('medicine-company-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.medicinecompany.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('medicine-company-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.medicinecompany.destroy', $data->id),
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
            'Backend/MedicineCompany/Form',
            [
                'pageTitle' => fn() => 'Medicine Company Create',
            ]
        );
    }


    public function store(MedicineCompanyRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->medicinecompanyService->create($data);

            if ($dataInfo) {
                $message = 'MedicineCompany created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinecompanies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create MedicineCompany.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineCompanyController', 'store', substr($err->getMessage(), 0, 1000));
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
        $medicinecompany = $this->medicinecompanyService->find($id);

        return Inertia::render(
            'Backend/MedicineCompany/Form',
            [
                'pageTitle' => fn() => 'Medicine Company Edit',
                'medicinecompany' => fn() => $medicinecompany,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(MedicineCompanyRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $medicinecompany = $this->medicinecompanyService->find($id);

            $dataInfo = $this->medicinecompanyService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'MedicineCompany updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinecompanies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update medicinecompanies.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineCompanyController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->medicinecompanyService->delete($id)) {
                $message = 'MedicineCompany deleted successfully';
                $this->storeAdminWorkLog($id, 'medicinecompanies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete MedicineCompany.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineCompanyController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->medicinecompanyService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'MedicineCompany ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinecompanies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "MedicineCompany.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineCompanyController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
