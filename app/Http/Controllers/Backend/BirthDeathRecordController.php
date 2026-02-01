<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BirthDeathRecordRequest;
use Illuminate\Support\Facades\DB;
use App\Services\BirthDeathRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class BirthDeathRecordController extends Controller
{
    use SystemTrait;

    protected $birthdeathrecordService;

    public function __construct(BirthDeathRecordService $birthdeathrecordService)
    {
        $this->birthdeathrecordService = $birthdeathrecordService;
    }



    public function index()
    {
        return Inertia::render(
            'Backend/BirthDeathRecord/Index',
            [
                'pageTitle' => fn () => 'BirthDeathRecord List',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'BirthDeathRecord Manage'],
                    ['link' => route('backend.birthdeathrecord.index'), 'title' => 'BirthDeathRecord List'],
                ],
                'tableHeaders' => fn () => $this->getTableHeaders(),
                'dataFields' => fn () => $this->dataFields(),
                'datas' => fn () => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->birthdeathrecordService->list();

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
                    'link' => route('backend.birthdeathrecord.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ],
                [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.birthdeathrecord.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ],
                [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.birthdeathrecord.destroy', $data->id),
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
            'Backend/BirthDeathRecord/Form',
            [
                'pageTitle' => fn () => 'BirthDeathRecord Create',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'BirthDeathRecord Manage'],
                    ['link' => route('backend.birthdeathrecord.create'), 'title' => 'BirthDeathRecord Create'],
                ],
            ]
        );
    }


    public function store(BirthDeathRecordRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('image'))
                $data['image'] = $this->imageUpload($request->file('image'), 'birthdeathrecords');

            if ($request->hasFile('file'))
                $data['file'] = $this->fileUpload($request->file('file'), 'birthdeathrecords');


            $dataInfo = $this->birthdeathrecordService->create($data);

            if ($dataInfo) {
                $message = 'BirthDeathRecord created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'birthdeathrecords', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create BirthDeathRecord.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'BirthDeathRecordController', 'store', substr($err->getMessage(), 0, 1000));
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
        $birthdeathrecord = $this->birthdeathrecordService->find($id);

        return Inertia::render(
            'Backend/BirthDeathRecord/Form',
            [
                'pageTitle' => fn () => 'BirthDeathRecord Edit',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'BirthDeathRecord Manage'],
                    ['link' => route('backend.birthdeathrecord.edit', $id), 'title' => 'BirthDeathRecord Edit'],
                ],
                'birthdeathrecord' => fn () => $birthdeathrecord,
                'id' => fn () => $id,
            ]
        );
    }

    public function update(BirthDeathRecordRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $birthdeathrecord = $this->birthdeathrecordService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'birthdeathrecords');
                $path = strstr($birthdeathrecord->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($birthdeathrecord->image ?? '', 'birthdeathrecords');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'birthdeathrecords/');
                $path = strstr($birthdeathrecord->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($birthdeathrecord->file ?? '', 'birthdeathrecords/');
            }

            $dataInfo = $this->birthdeathrecordService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'BirthDeathRecord updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'birthdeathrecords', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update birthdeathrecords.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BirthDeathRecordController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->birthdeathrecordService->delete($id)) {
                $message = 'BirthDeathRecord deleted successfully';
                $this->storeAdminWorkLog($id, 'birthdeathrecords', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete BirthDeathRecord.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BirthDeathRecordController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->birthdeathrecordService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'BirthDeathRecord ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'birthdeathrecords', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "BirthDeathRecord.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BirthDeathRecordController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
        }