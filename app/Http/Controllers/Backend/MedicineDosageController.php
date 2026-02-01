<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineDosageRequest;
use App\Services\MedicineCategoryService;
use Illuminate\Support\Facades\DB;
use App\Services\MedicineDosageService;
use App\Services\MedicineUnitService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class MedicineDosageController extends Controller
{
    use SystemTrait;

    protected $medicinedosageService, $medicindeCategoryService, $medicineUnitService;

    public function __construct(MedicineDosageService $medicinedosageService, MedicineCategoryService $medicindeCategoryService, MedicineUnitService $medicineUnitService)
    {
        $this->medicinedosageService = $medicinedosageService;
        $this->medicindeCategoryService = $medicindeCategoryService;
        $this->medicindeCategoryService = $medicindeCategoryService;
        $this->medicineUnitService = $medicineUnitService;

        $this->middleware('auth:admin');
        $this->middleware('permission:medicine-dosage-list');
        $this->middleware('permission:medicine-dosage-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:medicine-dosage-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:medicine-dosage-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:medicine-dosage-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/MedicineDosage/Index',
            [
                'pageTitle' => fn() => 'Medicine Dosage List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->medicinedosageService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->medicine_category_id = $data->medicineCategory->name ?? '';
            $customData->dose = $data->dose;
            $customData->medicine_unit_id = $data->medicineUnit->name ?? '';
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user->can('medicine-dosage-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.medicinedosage.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('medicine-dosage-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.medicinedosage.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('medicine-dosage-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.medicinedosage.destroy', $data->id),
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
            ['fieldName' => 'medicine_category_id', 'class' => 'text-center'],
            ['fieldName' => 'dose', 'class' => 'text-center'],
            ['fieldName' => 'medicine_unit_id', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Medicine Category Name',
            'Dose',
            'Unit',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/MedicineDosage/Form',
            [
                'pageTitle' => fn() => 'Medicine Dosage Create',
                'medicineCatgories' => fn() => $this->medicindeCategoryService->activeList(),
                'medicineUnits' => fn() => $this->medicineUnitService->activeList(),
            ]
        );
    }


    public function store(MedicineDosageRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->medicinedosageService->create($data);

            if ($dataInfo) {
                $message = 'MedicineDosage created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinedosages', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create MedicineDosage.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineDosageController', 'store', substr($err->getMessage(), 0, 1000));
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
        $medicinedosage = $this->medicinedosageService->find($id);

        return Inertia::render(
            'Backend/MedicineDosage/Form',
            [
                'pageTitle' => fn() => 'Medicine Dosage Edit',
                'medicinedosage' => fn() => $medicinedosage,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(MedicineDosageRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $medicinedosage = $this->medicinedosageService->find($id);

            $dataInfo = $this->medicinedosageService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'MedicineDosage updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinedosages', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update medicinedosages.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineDosageController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->medicinedosageService->delete($id)) {
                $message = 'MedicineDosage deleted successfully';
                $this->storeAdminWorkLog($id, 'medicinedosages', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete MedicineDosage.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineDosageController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->medicinedosageService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'MedicineDosage ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinedosages', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "MedicineDosage.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineDosageController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
