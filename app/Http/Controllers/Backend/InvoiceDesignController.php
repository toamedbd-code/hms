<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceDesignRequest;
use App\Models\InvoiceDesign;
use Illuminate\Support\Facades\DB;
use App\Services\InvoiceDesignService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class InvoiceDesignController extends Controller
{
    use SystemTrait;

    protected $invoicedesignService;

    public function __construct(InvoiceDesignService $invoicedesignService)
    {
        $this->invoicedesignService = $invoicedesignService;

        $this->middleware('auth:admin');
        $this->middleware('permission:invoice-design-list');
        $this->middleware('permission:invoice-design-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:invoice-design-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:invoice-design-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:invoice-design-list-status', ['only' => ['changeStatus']]);
    }

    public function index()
    {
        return Inertia::render(
            'Backend/InvoiceDesign/Index',
            [
                'pageTitle' => fn() => 'Invoice Design List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->invoicedesignService->list();

        if (request()->filled('footer_content'))
            $query->where('footer_content', 'like', request()->footer_content . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->module = $data->module ?? '';
            $customData->footer_content = Str::limit($data->footer_content, 50);
            $customData->header_photo = $data->header_photo_url ? '<img src="' . $data->header_photo_url . '" height="200" width="200"/>' : 'No Image';
            $customData->footer_photo = $data->footer_photo_url ? '<img src="' . $data->footer_photo_url . '" height="200" width="200"/>' : 'No Image';
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user->can('invoice-design-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.invoicedesign.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('invoice-design-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.invoicedesign.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('invoice-design-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.invoicedesign.destroy', $data->id),
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
            ['fieldName' => 'module', 'class' => 'text-left'],
            ['fieldName' => 'footer_content', 'class' => 'text-left'],
            ['fieldName' => 'header_photo', 'class' => 'text-center'],
            ['fieldName' => 'footer_photo', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Module',
            'Footer Content',
            'Header Photo',
            'Footer Photo',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/InvoiceDesign/Form',
            [
                'pageTitle' => fn() => 'Invoice Design Create',
            ]
        );
    }

    public function store(InvoiceDesignRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            $existingDesign = InvoiceDesign::where('module', $data['module'])
                ->whereNull('deleted_at')
                ->first();

            if ($existingDesign) {
                DB::commit();
                return redirect()
                    ->back()
                    ->with('errorMessage', 'This module design already exists.');
            }

            if ($request->hasFile('headerPhoto')) {
                $data['header_photo_path'] = $this->imageUpload($request->file('headerPhoto'), 'invoicedesigns');
            }

            if ($request->hasFile('footerPhoto')) {
                $data['footer_photo_path'] = $this->imageUpload($request->file('footerPhoto'), 'invoicedesigns');
            }

            unset($data['headerPhoto'], $data['footerPhoto']);

            $dataInfo = $this->invoicedesignService->create($data);

            if ($dataInfo) {
                $message = 'InvoiceDesign created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'invoicedesigns', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create InvoiceDesign.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'InvoiceDesignController', 'store', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $invoicedesign = $this->invoicedesignService->find($id);
        $appUrl = config('app.url');

        $invoicedesignData = $invoicedesign ? [
            'id' => $invoicedesign->id,
            'module' => $invoicedesign->module,
            'footer_content' => $invoicedesign->footer_content,
            'header_photo_path' => $invoicedesign->header_photo_path ? $appUrl . '/storage/' . $invoicedesign->header_photo_path : null,
            'footer_photo_path' => $invoicedesign->footer_photo_path ? $appUrl . '/storage/' . $invoicedesign->footer_photo_path : null,
            'header_photo_url' => $invoicedesign->header_photo_url,
            'footer_photo_url' => $invoicedesign->footer_photo_url,
            'status' => $invoicedesign->status,
        ] : null;

        return Inertia::render(
            'Backend/InvoiceDesign/Form',
            [
                'pageTitle' => fn() => 'Invoice Design Edit',
                'invoicedesign' => fn() => $invoicedesignData,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(InvoiceDesignRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $invoicedesign = $this->invoicedesignService->find($id);

            $existingDesign = InvoiceDesign::where('module', $data['module'])
                ->where('id', '!=', $id)
                ->whereNull('deleted_at')
                ->first();

            if ($existingDesign) {
                DB::commit();
                return redirect()
                    ->back()
                    ->with('errorMessage', 'Another design already exists for this module.');
            }

            if ($request->hasFile('headerPhoto')) {
                $data['header_photo_path'] = $this->imageUpload($request->file('headerPhoto'), 'invoicedesigns');

                if ($invoicedesign->header_photo_path) {
                    $path = public_path('storage/' . $invoicedesign->header_photo_path);
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            } else {
                $data['header_photo_path'] = $invoicedesign->header_photo_path;
            }

            if ($request->hasFile('footerPhoto')) {
                $data['footer_photo_path'] = $this->imageUpload($request->file('footerPhoto'), 'invoicedesigns');

                if ($invoicedesign->footer_photo_path) {
                    $path = public_path('storage/' . $invoicedesign->footer_photo_path);
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            } else {
                $data['footer_photo_path'] = $invoicedesign->footer_photo_path;
            }

            unset($data['headerPhoto'], $data['footerPhoto']);

            $dataInfo = $this->invoicedesignService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'InvoiceDesign updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'invoicedesigns', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update invoicedesigns.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'InvoiceDesignController', 'update', substr($err->getMessage(), 0, 1000));
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
            $invoicedesign = $this->invoicedesignService->find($id);

            // Delete associated files
            if ($invoicedesign->header_photo_path) {
                $path = public_path('storage/' . $invoicedesign->header_photo_path);
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            if ($invoicedesign->footer_photo_path) {
                $path = public_path('storage/' . $invoicedesign->footer_photo_path);
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            if ($this->invoicedesignService->delete($id)) {
                $message = 'InvoiceDesign deleted successfully';
                $this->storeAdminWorkLog($id, 'invoicedesigns', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete InvoiceDesign.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'InvoiceDesignController', 'destroy', substr($err->getMessage(), 0, 1000));
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
            $dataInfo = $this->invoicedesignService->find($id);

            // if ($status === 'Active') {
            //     $this->invoicedesignService->deactivateAllExcept($id);
            // }

            $dataInfo = $this->invoicedesignService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'InvoiceDesign ' . $status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'invoicedesigns', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . $status . " InvoiceDesign.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'InvoiceDesignController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
