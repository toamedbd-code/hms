<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\DoseDurationRequest;
use Illuminate\Support\Facades\DB;
use App\Services\DoseDurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class DoseDurationController extends Controller
{
    use SystemTrait;

    protected $dosedurationService;

    public function __construct(DoseDurationService $dosedurationService)
    {
        $this->dosedurationService = $dosedurationService;

        $this->middleware('auth:admin');
        $this->middleware('permission:dose-duration-list');
        $this->middleware('permission:dose-duration-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:dose-duration-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:dose-duration-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:dose-duration-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/DoseDuration/Index',
            [
                'pageTitle' => fn() => 'Dose Duration List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->dosedurationService->list();

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

            if ($user->can('dose-duration-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.doseduration.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('dose-duration-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.doseduration.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('dose-duration-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.doseduration.destroy', $data->id),
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
            'Backend/DoseDuration/Form',
            [
                'pageTitle' => fn() => 'Dose Duration Create',
            ]
        );
    }


    public function store(DoseDurationRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            $dataInfo = $this->dosedurationService->create($data);

            if ($dataInfo) {
                $message = 'DoseDuration created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'dosedurations', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create DoseDuration.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'DoseDurationController', 'store', substr($err->getMessage(), 0, 1000));
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
        $doseduration = $this->dosedurationService->find($id);

        return Inertia::render(
            'Backend/DoseDuration/Form',
            [
                'pageTitle' => fn() => 'Dose Duration Edit',
                'doseduration' => fn() => $doseduration,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(DoseDurationRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $doseduration = $this->dosedurationService->find($id);

            $dataInfo = $this->dosedurationService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'DoseDuration updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'dosedurations', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update dosedurations.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'DoseDurationController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->dosedurationService->delete($id)) {
                $message = 'DoseDuration deleted successfully';
                $this->storeAdminWorkLog($id, 'dosedurations', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete DoseDuration.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'DoseDurationController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->dosedurationService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'DoseDuration ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'dosedurations', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "DoseDuration.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'DoseDurationController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
