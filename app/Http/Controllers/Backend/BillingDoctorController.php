<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillingDoctorRequest;
use Illuminate\Support\Facades\DB;
use App\Services\BillingDoctorService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class BillingDoctorController extends Controller
{
    use SystemTrait;

    protected $billingdoctorService;

    public function __construct(BillingDoctorService $billingdoctorService)
    {
        $this->billingdoctorService = $billingdoctorService;

        $this->middleware('auth:admin');

        $this->middleware('permission:billing-doctor-list', ['only' => ['index']]);
        $this->middleware('permission:billing-doctor-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:billing-doctor-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:billing-doctor-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:billing-doctor-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/BillingDoctor/Index',
            [
                'pageTitle' => fn() => 'Billing Doctor List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->billingdoctorService->list();

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

            if ($user->can('billing-doctor-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.billingdoctor.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            // if ($user->can('billing-doctor-list-edit')) {
            //     $customData->links[] = [
            //         'linkClass' => 'bg-yellow-400 text-black semi-bold',
            //         'link' => route('backend.billingdoctor.edit',  $data->id),
            //         'linkLabel' => getLinkLabel('Edit', null, null)
            //     ];
            // }

            if ($user->can('billing-doctor-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.billingdoctor.destroy', $data->id),
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
            'Backend/BillingDoctor/Form',
            [
                'pageTitle' => fn() => 'BillingDoctor Create',
                'breadcrumbs' => fn() => [
                    ['link' => null, 'title' => 'BillingDoctor Manage'],
                    ['link' => route('backend.billingdoctor.create'), 'title' => 'BillingDoctor Create'],
                ],
            ]
        );
    }


    public function store(BillingDoctorRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('image'))
                $data['image'] = $this->imageUpload($request->file('image'), 'billingdoctors');

            if ($request->hasFile('file'))
                $data['file'] = $this->fileUpload($request->file('file'), 'billingdoctors');


            $dataInfo = $this->billingdoctorService->create($data);

            if ($dataInfo) {
                $message = 'BillingDoctor created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'billingdoctors', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create BillingDoctor.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingDoctorController', 'store', substr($err->getMessage(), 0, 1000));
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
        $billingdoctor = $this->billingdoctorService->find($id);

        return Inertia::render(
            'Backend/BillingDoctor/Form',
            [
                'pageTitle' => fn() => 'BillingDoctor Edit',
                'breadcrumbs' => fn() => [
                    ['link' => null, 'title' => 'BillingDoctor Manage'],
                    ['link' => route('backend.billingdoctor.edit', $id), 'title' => 'BillingDoctor Edit'],
                ],
                'billingdoctor' => fn() => $billingdoctor,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(BillingDoctorRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $billingdoctor = $this->billingdoctorService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'billingdoctors');
                $path = strstr($billingdoctor->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($billingdoctor->image ?? '', 'billingdoctors');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'billingdoctors/');
                $path = strstr($billingdoctor->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($billingdoctor->file ?? '', 'billingdoctors/');
            }

            $dataInfo = $this->billingdoctorService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'BillingDoctor updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'billingdoctors', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update billingdoctors.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingDoctorController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->billingdoctorService->delete($id)) {
                $message = 'BillingDoctor deleted successfully';
                $this->storeAdminWorkLog($id, 'billingdoctors', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete BillingDoctor.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingDoctorController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->billingdoctorService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'BillingDoctor ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'billingdoctors', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "BillingDoctor.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingDoctorController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
