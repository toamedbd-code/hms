<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PharmacyRequest;
use Illuminate\Support\Facades\DB;
use App\Services\PharmacyService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class PharmacyController extends Controller
{
    use SystemTrait;

    protected $pharmacyService;

    public function __construct(PharmacyService $pharmacyService)
    {
        $this->pharmacyService = $pharmacyService;
    }



    public function index()
    {
        return Inertia::render(
            'Backend/Pharmacy/Index',
            [
                'pageTitle' => fn () => 'Pharmacy List',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Pharmacy Manage'],
                    ['link' => route('backend.pharmacy.index'), 'title' => 'Pharmacy List'],
                ],
                'tableHeaders' => fn () => $this->getTableHeaders(),
                'dataFields' => fn () => $this->dataFields(),
                'datas' => fn () => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->pharmacyService->list();

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
                    'link' => route('backend.pharmacy.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ],
                [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.pharmacy.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ],
                [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.pharmacy.destroy', $data->id),
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
            'Backend/Pharmacy/Form',
            [
                'pageTitle' => fn () => 'Pharmacy Create',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Pharmacy Manage'],
                    ['link' => route('backend.pharmacy.create'), 'title' => 'Pharmacy Create'],
                ],
            ]
        );
    }


    public function store(PharmacyRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('image'))
                $data['image'] = $this->imageUpload($request->file('image'), 'pharmacies');

            if ($request->hasFile('file'))
                $data['file'] = $this->fileUpload($request->file('file'), 'pharmacies');


            $dataInfo = $this->pharmacyService->create($data);

            if ($dataInfo) {
                $message = 'Pharmacy created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pharmacies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Pharmacy.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyController', 'store', substr($err->getMessage(), 0, 1000));
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
        $pharmacy = $this->pharmacyService->find($id);

        return Inertia::render(
            'Backend/Pharmacy/Form',
            [
                'pageTitle' => fn () => 'Pharmacy Edit',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Pharmacy Manage'],
                    ['link' => route('backend.pharmacy.edit', $id), 'title' => 'Pharmacy Edit'],
                ],
                'pharmacy' => fn () => $pharmacy,
                'id' => fn () => $id,
            ]
        );
    }

    public function update(PharmacyRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $pharmacy = $this->pharmacyService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'pharmacies');
                $path = strstr($pharmacy->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($pharmacy->image ?? '', 'pharmacies');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'pharmacies/');
                $path = strstr($pharmacy->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($pharmacy->file ?? '', 'pharmacies/');
            }

            $dataInfo = $this->pharmacyService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Pharmacy updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pharmacies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update pharmacies.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->pharmacyService->delete($id)) {
                $message = 'Pharmacy deleted successfully';
                $this->storeAdminWorkLog($id, 'pharmacies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Pharmacy.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->pharmacyService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Pharmacy ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'pharmacies', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Pharmacy.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'PharmacyController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
        }