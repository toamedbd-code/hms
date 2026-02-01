<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\DutyRoasterRequest;
use Illuminate\Support\Facades\DB;
use App\Services\DutyRoasterService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class DutyRoasterController extends Controller
{
    use SystemTrait;

    protected $dutyroasterService;

    public function __construct(DutyRoasterService $dutyroasterService)
    {
        $this->dutyroasterService = $dutyroasterService;
    }



    public function index()
    {
        return Inertia::render(
            'Backend/DutyRoaster/Index',
            [
                'pageTitle' => fn () => 'DutyRoaster List',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'DutyRoaster Manage'],
                    ['link' => route('backend.dutyroaster.index'), 'title' => 'DutyRoaster List'],
                ],
                'tableHeaders' => fn () => $this->getTableHeaders(),
                'dataFields' => fn () => $this->dataFields(),
                'datas' => fn () => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->dutyroasterService->list();

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
                    'link' => route('backend.dutyroaster.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ],
                [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.dutyroaster.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ],
                [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.dutyroaster.destroy', $data->id),
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
            'Backend/DutyRoaster/Form',
            [
                'pageTitle' => fn () => 'DutyRoaster Create',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'DutyRoaster Manage'],
                    ['link' => route('backend.dutyroaster.create'), 'title' => 'DutyRoaster Create'],
                ],
            ]
        );
    }


    public function store(DutyRoasterRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('image'))
                $data['image'] = $this->imageUpload($request->file('image'), 'dutyroasters');

            if ($request->hasFile('file'))
                $data['file'] = $this->fileUpload($request->file('file'), 'dutyroasters');


            $dataInfo = $this->dutyroasterService->create($data);

            if ($dataInfo) {
                $message = 'DutyRoaster created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'dutyroasters', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create DutyRoaster.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'DutyRoasterController', 'store', substr($err->getMessage(), 0, 1000));
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
        $dutyroaster = $this->dutyroasterService->find($id);

        return Inertia::render(
            'Backend/DutyRoaster/Form',
            [
                'pageTitle' => fn () => 'DutyRoaster Edit',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'DutyRoaster Manage'],
                    ['link' => route('backend.dutyroaster.edit', $id), 'title' => 'DutyRoaster Edit'],
                ],
                'dutyroaster' => fn () => $dutyroaster,
                'id' => fn () => $id,
            ]
        );
    }

    public function update(DutyRoasterRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $dutyroaster = $this->dutyroasterService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'dutyroasters');
                $path = strstr($dutyroaster->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($dutyroaster->image ?? '', 'dutyroasters');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'dutyroasters/');
                $path = strstr($dutyroaster->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($dutyroaster->file ?? '', 'dutyroasters/');
            }

            $dataInfo = $this->dutyroasterService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'DutyRoaster updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'dutyroasters', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update dutyroasters.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'DutyRoasterController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->dutyroasterService->delete($id)) {
                $message = 'DutyRoaster deleted successfully';
                $this->storeAdminWorkLog($id, 'dutyroasters', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete DutyRoaster.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'DutyRoasterController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->dutyroasterService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'DutyRoaster ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'dutyroasters', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "DutyRoaster.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'DutyRoasterController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
        }