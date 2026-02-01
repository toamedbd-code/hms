<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineDoseIntervalRequest;
use Illuminate\Support\Facades\DB;
use App\Services\MedicineDoseIntervalService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class MedicineDoseIntervalController extends Controller
{
    use SystemTrait;

    protected $medicinedoseintervalService;

    public function __construct(MedicineDoseIntervalService $medicinedoseintervalService)
    {
        $this->medicinedoseintervalService = $medicinedoseintervalService;

        $this->middleware('auth:admin');
        $this->middleware('permission:medicine-dose-interval-list');
        $this->middleware('permission:medicine-dose-interval-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:medicine-dose-interval-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:medicine-dose-interval-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:medicine-dose-interval-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/MedicineDoseInterval/Index',
            [
                'pageTitle' => fn() => 'Dose Interval List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->medicinedoseintervalService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', '%' . request()->name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user->can('medicine-dose-interval-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.medicinedoseinterval.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('medicine-dose-interval-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.medicinedoseinterval.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('medicine-dose-interval-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.medicinedoseinterval.destroy', $data->id),
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
            'Backend/MedicineDoseInterval/Form',
            [
                'pageTitle' => fn() => 'Dose Interval Create',
            ]
        );
    }


    public function store(MedicineDoseIntervalRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->medicinedoseintervalService->create($data);

            if ($dataInfo) {
                $message = 'MedicineDoseInterval created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinedoseintervals', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create MedicineDoseInterval.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineDoseIntervalController', 'store', substr($err->getMessage(), 0, 1000));
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
        $medicinedoseinterval = $this->medicinedoseintervalService->find($id);

        return Inertia::render(
            'Backend/MedicineDoseInterval/Form',
            [
                'pageTitle' => fn() => 'Dose Interval Edit',
                'medicinedoseinterval' => fn() => $medicinedoseinterval,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(MedicineDoseIntervalRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $medicinedoseinterval = $this->medicinedoseintervalService->find($id);

            $dataInfo = $this->medicinedoseintervalService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'MedicineDoseInterval updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinedoseintervals', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update medicinedoseintervals.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineDoseIntervalController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->medicinedoseintervalService->delete($id)) {
                $message = 'MedicineDoseInterval deleted successfully';
                $this->storeAdminWorkLog($id, 'medicinedoseintervals', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete MedicineDoseInterval.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineDoseIntervalController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->medicinedoseintervalService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'MedicineDoseInterval ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'medicinedoseintervals', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "MedicineDoseInterval.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'MedicineDoseIntervalController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
