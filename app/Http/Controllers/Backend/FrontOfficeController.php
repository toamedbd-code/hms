<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontOfficeRequest;
use Illuminate\Support\Facades\DB;
use App\Services\FrontOfficeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class FrontOfficeController extends Controller
{
    use SystemTrait;

    protected $frontofficeService;

    public function __construct(FrontOfficeService $frontofficeService)
    {
        $this->frontofficeService = $frontofficeService;
    }



    public function index()
    {
        return Inertia::render(
            'Backend/FrontOffice/Index',
            [
                'pageTitle' => fn () => 'FrontOffice List',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'FrontOffice Manage'],
                    ['link' => route('backend.frontoffice.index'), 'title' => 'FrontOffice List'],
                ],
                'tableHeaders' => fn () => $this->getTableHeaders(),
                'dataFields' => fn () => $this->dataFields(),
                'datas' => fn () => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->frontofficeService->list();

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
                    'link' => route('backend.frontoffice.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ],
                [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.frontoffice.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ],
                [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.frontoffice.destroy', $data->id),
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
            'Backend/FrontOffice/Form',
            [
                'pageTitle' => fn () => 'FrontOffice Create',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'FrontOffice Manage'],
                    ['link' => route('backend.frontoffice.create'), 'title' => 'FrontOffice Create'],
                ],
            ]
        );
    }


    public function store(FrontOfficeRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('image'))
                $data['image'] = $this->imageUpload($request->file('image'), 'frontoffices');

            if ($request->hasFile('file'))
                $data['file'] = $this->fileUpload($request->file('file'), 'frontoffices');


            $dataInfo = $this->frontofficeService->create($data);

            if ($dataInfo) {
                $message = 'FrontOffice created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'frontoffices', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create FrontOffice.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'FrontOfficeController', 'store', substr($err->getMessage(), 0, 1000));
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
        $frontoffice = $this->frontofficeService->find($id);

        return Inertia::render(
            'Backend/FrontOffice/Form',
            [
                'pageTitle' => fn () => 'FrontOffice Edit',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'FrontOffice Manage'],
                    ['link' => route('backend.frontoffice.edit', $id), 'title' => 'FrontOffice Edit'],
                ],
                'frontoffice' => fn () => $frontoffice,
                'id' => fn () => $id,
            ]
        );
    }

    public function update(FrontOfficeRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $frontoffice = $this->frontofficeService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'frontoffices');
                $path = strstr($frontoffice->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($frontoffice->image ?? '', 'frontoffices');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'frontoffices/');
                $path = strstr($frontoffice->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($frontoffice->file ?? '', 'frontoffices/');
            }

            $dataInfo = $this->frontofficeService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'FrontOffice updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'frontoffices', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update frontoffices.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'FrontOfficeController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->frontofficeService->delete($id)) {
                $message = 'FrontOffice deleted successfully';
                $this->storeAdminWorkLog($id, 'frontoffices', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete FrontOffice.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'FrontOfficeController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->frontofficeService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'FrontOffice ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'frontoffices', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "FrontOffice.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'FrontOfficeController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
        }