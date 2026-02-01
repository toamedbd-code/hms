<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChargeTypeRequest;
use Illuminate\Support\Facades\DB;
use App\Services\ChargeTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class ChargeTypeController extends Controller
{
    use SystemTrait;

    protected $chargetypeService;

    public function __construct(ChargeTypeService $chargetypeService)
    {
        $this->chargetypeService = $chargetypeService;

        $this->middleware('auth:admin');
        $this->middleware('permission:charge-type-list');
        $this->middleware('permission:charge-type-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:charge-type-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:charge-type-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:charge-type-list-status', ['only' => ['changeStatus']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/ChargeType/Index',
            [
                'pageTitle' => fn() => 'Charge Type List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->chargetypeService->list();

        if (request()->filled('name')) {
            $query->where('name', 'like', request()->name . '%');
        }

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        return $datas;
    }

    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-left'],
            ['fieldName' => 'Appointment', 'class' => 'text-center'],
            ['fieldName' => 'OPD', 'class' => 'text-center'],
            ['fieldName' => 'IPD', 'class' => 'text-center'],
            ['fieldName' => 'Pathology', 'class' => 'text-center'],
            ['fieldName' => 'Radiology', 'class' => 'text-center'],
            ['fieldName' => 'Blood Bank', 'class' => 'text-center'],
            ['fieldName' => 'Ambulance', 'class' => 'text-center'],
            ['fieldName' => 'action', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Name',
            'Appointment',
            'OPD',
            'IPD',
            'Pathology',
            'Radiology',
            'Blood Bank',
            'Ambulance',
            'Action',
        ];
    }

    public function toggleModule(Request $request)
    {
        $request->validate([
            'charge_type_id' => 'required',
            'module' => 'required|string',
            'action' => 'required|in:add,remove'
        ]);

        DB::beginTransaction();

        try {
            $chargeType = $this->chargetypeService->find($request->charge_type_id);

            if (!$chargeType) {
                return response()->json(['error' => 'Charge type not found'], 404);
            }

            $modules = json_decode($chargeType->modules ?? '[]', true);

            if (!is_array($modules)) {
                $modules = [];
            }

            if ($request->action === 'add') {
                if (!in_array($request->module, $modules)) {
                    $modules[] = $request->module;
                }
                $message = "Module {$request->module} added successfully";
            } else {
                $modules = array_filter($modules, function ($module) use ($request) {
                    return $module !== $request->module;
                });
                $modules = array_values($modules);
                $message = "Module {$request->module} removed successfully";
            }

            $updateData = ['modules' => json_encode($modules)];
            $this->chargetypeService->update($updateData, $request->charge_type_id);

            $this->storeAdminWorkLog(
                $request->charge_type_id,
                'chargetypes',
                $message
            );

            DB::commit();

            return back()->with('successMessage', $message);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeTypeController', 'toggleModule', substr($err->getMessage(), 0, 1000));
            DB::commit();

            return back()->with('errorMessage', 'Server Error Occurred. Please Try Again.');
        }
    }

    public function create()
    {
        return Inertia::render(
            'Backend/ChargeType/Form',
            [
                'pageTitle' => fn() => 'Charge Type Create',
            ]
        );
    }

    public function store(ChargeTypeRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            $data['modules'] = json_encode($data['modules']);

            $dataInfo = $this->chargetypeService->create($data);

            if ($dataInfo) {
                $message = 'Charge Type created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'charge types', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Charge Type.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeTypeController', 'store', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $chargetype = $this->chargetypeService->find($id);

        return Inertia::render(
            'Backend/ChargeType/Form',
            [
                'pageTitle' => fn() => 'Charge Type Edit',
                'chargetype' => fn() => $chargetype,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(ChargeTypeRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $chargetype = $this->chargetypeService->find($id);

            // if ($request->hasFile('image')) {
            //     $data['image'] = $this->imageUpload($request->file('image'), 'chargetypes');
            //     $path = strstr($chargetype->image, 'storage/');
            //     if (file_exists($path)) {
            //         unlink($path);
            //     }
            // } else {
            //     $data['image'] = strstr($chargetype->image ?? '', 'chargetypes');
            // }

            // if ($request->hasFile('file')) {
            //     $data['file'] = $this->fileUpload($request->file('file'), 'chargetypes/');
            //     $path = strstr($chargetype->file, 'storage/');
            //     if (file_exists($path)) {
            //         unlink($path);
            //     }
            // } else {
            //     $data['file'] = strstr($chargetype->file ?? '', 'chargetypes/');
            // }

            $dataInfo = $this->chargetypeService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Charge Type updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'charge types', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update charge types.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeTypeController', 'update', substr($err->getMessage(), 0, 1000));
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
            if ($this->chargetypeService->delete($id)) {
                $message = 'Charge Type deleted successfully';
                $this->storeAdminWorkLog($id, 'charge types', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Charge Type.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeTypeController', 'destroy', substr($err->getMessage(), 0, 1000));
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
            $dataInfo = $this->chargetypeService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Charge Type ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'charge types', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Charge Type.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'ChargeTypeController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
