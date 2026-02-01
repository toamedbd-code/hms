<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BloodBankRequest;
use Illuminate\Support\Facades\DB;
use App\Services\BloodBankService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class BloodBankController extends Controller
{
    use SystemTrait;

    protected $bloodbankService;

    public function __construct(BloodBankService $bloodbankService)
    {
        $this->bloodbankService = $bloodbankService;
    }



    public function index()
    {
        return Inertia::render(
            'Backend/BloodBank/Index',
            [
                'pageTitle' => fn () => 'BloodBank List',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'BloodBank Manage'],
                    ['link' => route('backend.bloodbank.index'), 'title' => 'BloodBank List'],
                ],
                'tableHeaders' => fn () => $this->getTableHeaders(),
                'dataFields' => fn () => $this->dataFields(),
                'datas' => fn () => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->bloodbankService->list();

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
                    'link' => route('backend.bloodbank.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ],
                [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.bloodbank.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ],
                [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.bloodbank.destroy', $data->id),
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
            'Backend/BloodBank/Form',
            [
                'pageTitle' => fn () => 'BloodBank Create',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'BloodBank Manage'],
                    ['link' => route('backend.bloodbank.create'), 'title' => 'BloodBank Create'],
                ],
            ]
        );
    }


    public function store(BloodBankRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('image'))
                $data['image'] = $this->imageUpload($request->file('image'), 'bloodbanks');

            if ($request->hasFile('file'))
                $data['file'] = $this->fileUpload($request->file('file'), 'bloodbanks');


            $dataInfo = $this->bloodbankService->create($data);

            if ($dataInfo) {
                $message = 'BloodBank created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bloodbanks', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create BloodBank.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodBankController', 'store', substr($err->getMessage(), 0, 1000));
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
        $bloodbank = $this->bloodbankService->find($id);

        return Inertia::render(
            'Backend/BloodBank/Form',
            [
                'pageTitle' => fn () => 'BloodBank Edit',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'BloodBank Manage'],
                    ['link' => route('backend.bloodbank.edit', $id), 'title' => 'BloodBank Edit'],
                ],
                'bloodbank' => fn () => $bloodbank,
                'id' => fn () => $id,
            ]
        );
    }

    public function update(BloodBankRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $bloodbank = $this->bloodbankService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'bloodbanks');
                $path = strstr($bloodbank->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($bloodbank->image ?? '', 'bloodbanks');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'bloodbanks/');
                $path = strstr($bloodbank->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($bloodbank->file ?? '', 'bloodbanks/');
            }

            $dataInfo = $this->bloodbankService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'BloodBank updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bloodbanks', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update bloodbanks.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodBankController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->bloodbankService->delete($id)) {
                $message = 'BloodBank deleted successfully';
                $this->storeAdminWorkLog($id, 'bloodbanks', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete BloodBank.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodBankController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->bloodbankService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'BloodBank ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'bloodbanks', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "BloodBank.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BloodBankController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
        }