<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetupRequest;
use Illuminate\Support\Facades\DB;
use App\Services\SetupService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class SetupController extends Controller
{
    use SystemTrait;

    protected $setupService;

    public function __construct(SetupService $setupService)
    {
        $this->setupService = $setupService;
    }



    public function index()
    {
        return Inertia::render(
            'Backend/Setup/Index',
            [
                'pageTitle' => fn () => 'Setup List',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Setup Manage'],
                    ['link' => route('backend.setup.index'), 'title' => 'Setup List'],
                ],
                'tableHeaders' => fn () => $this->getTableHeaders(),
                'dataFields' => fn () => $this->dataFields(),
                'datas' => fn () => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->setupService->list();

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
                    'link' => route('backend.setup.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ],
                [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.setup.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ],
                [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.setup.destroy', $data->id),
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
            'Backend/Setup/Form',
            [
                'pageTitle' => fn () => 'Setup Create',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Setup Manage'],
                    ['link' => route('backend.setup.create'), 'title' => 'Setup Create'],
                ],
            ]
        );
    }


    public function store(SetupRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('image'))
                $data['image'] = $this->imageUpload($request->file('image'), 'setups');

            if ($request->hasFile('file'))
                $data['file'] = $this->fileUpload($request->file('file'), 'setups');


            $dataInfo = $this->setupService->create($data);

            if ($dataInfo) {
                $message = 'Setup created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'setups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create Setup.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'SetupController', 'store', substr($err->getMessage(), 0, 1000));
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
        $setup = $this->setupService->find($id);

        return Inertia::render(
            'Backend/Setup/Form',
            [
                'pageTitle' => fn () => 'Setup Edit',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Setup Manage'],
                    ['link' => route('backend.setup.edit', $id), 'title' => 'Setup Edit'],
                ],
                'setup' => fn () => $setup,
                'id' => fn () => $id,
            ]
        );
    }

    public function update(SetupRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $setup = $this->setupService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'setups');
                $path = strstr($setup->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($setup->image ?? '', 'setups');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'setups/');
                $path = strstr($setup->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($setup->file ?? '', 'setups/');
            }

            $dataInfo = $this->setupService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'Setup updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'setups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update setups.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'SetupController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->setupService->delete($id)) {
                $message = 'Setup deleted successfully';
                $this->storeAdminWorkLog($id, 'setups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete Setup.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'SetupController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->setupService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'Setup ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'setups', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "Setup.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'SetupController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
        }