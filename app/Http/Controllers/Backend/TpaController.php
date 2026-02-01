<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\TpaRequest;
use Illuminate\Support\Facades\DB;
use App\Services\TpaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class TpaController extends Controller
{
    use SystemTrait;

    protected $tpaService;

    public function __construct(TpaService $tpaService)
    {
        $this->tpaService = $tpaService;

        $this->middleware('auth:admin');
        $this->middleware('permission:tpa-list');
        $this->middleware('permission:tpa-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:tpa-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:tpa-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:tpa-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/Tpa/Index',
            [
                'pageTitle' => fn() => 'Tpa List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->tpaService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->code = $data->code;
            $customData->contact_number = $data->contact_number;
            $customData->address = $data->address;
            $customData->contact_person_name = $data->contact_person_name;
            $customData->contact_person_phone = $data->contact_person_phone;
            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;

            $user = auth('admin')->user();

            $customData->links = [];

            if ($user->can('tpa-list-status-change')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.tpa.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('tpa-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.tpa.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('tpa-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.tpa.destroy', $data->id),
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
            ['fieldName' => 'code', 'class' => 'text-center'],
            ['fieldName' => 'contact_number', 'class' => 'text-center'],
            ['fieldName' => 'address', 'class' => 'text-center'],
            ['fieldName' => 'contact_person_name', 'class' => 'text-center'],
            ['fieldName' => 'contact_person_phone', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Name',
            'Code',
            'Contact Number',
            'Address',
            'Contact Person Name',
            'Contact Person Phone',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/Tpa/Form',
            [
                'pageTitle' => fn() => 'Tpa Create',
            ]
        );
    }


    public function store(TpaRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('image'))
                $data['image'] = $this->imageUpload($request->file('image'), 'tpas');

            if ($request->hasFile('file'))
                $data['file'] = $this->fileUpload($request->file('file'), 'tpas');


            $dataInfo = $this->tpaService->create($data);

            if ($dataInfo) {
                $message = 'Tpa created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'tpas', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Tpa.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'TpaController', 'store', substr($err->getMessage(), 0, 1000));
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
        $tpa = $this->tpaService->find($id);

        return Inertia::render(
            'Backend/Tpa/Form',
            [
                'pageTitle' => fn() => 'Tpa Edit',
                'tpa' => fn() => $tpa,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(TpaRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $tpa = $this->tpaService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'tpas');
                $path = strstr($tpa->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($tpa->image ?? '', 'tpas');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'tpas/');
                $path = strstr($tpa->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($tpa->file ?? '', 'tpas/');
            }

            $dataInfo = $this->tpaService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Tpa updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'tpas', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update tpas.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'TpaController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->tpaService->delete($id)) {
                $message = 'Tpa deleted successfully';
                $this->storeAdminWorkLog($id, 'tpas', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Tpa.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'TpaController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->tpaService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Tpa ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'tpas', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Tpa.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'TpaController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
