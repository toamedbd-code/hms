<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\AmbulanceRequest;
use Illuminate\Support\Facades\DB;
use App\Services\AmbulanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class AmbulanceController extends Controller
{
    use SystemTrait;

    protected $ambulanceService;

    public function __construct(AmbulanceService $ambulanceService)
    {
        $this->ambulanceService = $ambulanceService;
    }



    public function index()
    {
        return Inertia::render(
            'Backend/Ambulance/Index',
            [
                'pageTitle' => fn () => 'Ambulance List',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Ambulance Manage'],
                    ['link' => route('backend.ambulance.index'), 'title' => 'Ambulance List'],
                ],
                'tableHeaders' => fn () => $this->getTableHeaders(),
                'dataFields' => fn () => $this->dataFields(),
                'datas' => fn () => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->ambulanceService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', request()->name . '%');


        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->photo = '<img src="' . $data->photo . '" height="50" width="50"/>';
            $customData->status = getStatusText($data->status);

            $customData->hasLink = true;
            $customData->links = [
                [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.ambulance.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ],
                [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.ambulance.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ],
                [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.ambulance.destroy', $data->id),
                    'linkLabel' => getLinkLabel('Delete', null, null)
                ]

            ];
            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    private function dataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'photo', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Photo',
            'Name',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/Ambulance/Form',
            [
                'pageTitle' => fn () => 'Ambulance Create',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Ambulance Manage'],
                    ['link' => route('backend.ambulance.create'), 'title' => 'Ambulance Create'],
                ],
            ]
        );
    }


    public function store(AmbulanceRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('image'))
                $data['image'] = $this->imageUpload($request->file('image'), 'ambulances');

            if ($request->hasFile('file'))
                $data['file'] = $this->fileUpload($request->file('file'), 'ambulances');


            $dataInfo = $this->ambulanceService->create($data);

            if ($dataInfo) {
                $message = 'Ambulance created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'ambulances', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Ambulance.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'AmbulanceController', 'store', substr($err->getMessage(), 0, 1000));
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
        $ambulance = $this->ambulanceService->find($id);

        return Inertia::render(
            'Backend/Ambulance/Form',
            [
                'pageTitle' => fn () => 'Ambulance Edit',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Ambulance Manage'],
                    ['link' => route('backend.ambulance.edit', $id), 'title' => 'Ambulance Edit'],
                ],
                'ambulance' => fn () => $ambulance,
                'id' => fn () => $id,
            ]
        );
    }

    public function update(AmbulanceRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $ambulance = $this->ambulanceService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'ambulances');
                $path = strstr($ambulance->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($ambulance->image ?? '', 'ambulances');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'ambulances/');
                $path = strstr($ambulance->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($ambulance->file ?? '', 'ambulances/');
            }

            $dataInfo = $this->ambulanceService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Ambulance updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'ambulances', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update ambulances.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AmbulanceController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->ambulanceService->delete($id)) {
                $message = 'Ambulance deleted successfully';
                $this->storeAdminWorkLog($id, 'ambulances', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Ambulance.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AmbulanceController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->ambulanceService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Ambulance ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'ambulances', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Ambulance.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AmbulanceController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
        }