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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

        $user = auth('admin')->user() ?? auth()->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->module = $data->module ?? '';
            $customData->footer_content = Str::limit($data->footer_content, 50);
            $customData->header_photo = $data->header_photo_url ? '<img src="' . $data->header_photo_url . '" height="200" width="200"/>' : 'No Image';
            $customData->footer_photo = $data->footer_photo_url ? '<img src="' . $data->footer_photo_url . '" height="200" width="200"/>' : 'No Image';
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user && Gate::check('invoice-design-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.invoicedesign.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user && Gate::check('invoice-design-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.invoicedesign.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user && Gate::check('invoice-design-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.invoicedesign.delete', $data->id),
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

            $headerPath = $this->uploadRequestImage(
                $request,
                ['headerPhoto', 'header_photo'],
                ['headerPhotoPreview', 'header_photo_preview'],
                'invoicedesigns'
            );
            if ($headerPath) {
                $data['header_photo_path'] = $headerPath;
            }

            $footerPath = $this->uploadRequestImage(
                $request,
                ['footerPhoto', 'footer_photo'],
                ['footerPhotoPreview', 'footer_photo_preview'],
                'invoicedesigns'
            );
            if ($footerPath) {
                $data['footer_photo_path'] = $footerPath;
            }

            unset($data['headerPhoto'], $data['header_photo'], $data['footerPhoto'], $data['footer_photo']);

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
            Log::error('InvoiceDesign store failed', [
                'message' => $err->getMessage(),
                'file' => $err->getFile(),
                'line' => $err->getLine(),
            ]);
            $this->storeSystemError('Backend', 'InvoiceDesignController', 'store', substr($err->getMessage(), 0, 1000));
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
            'header_photo_path' => $invoicedesign->header_photo_path,
            'footer_photo_path' => $invoicedesign->footer_photo_path,
            'header_photo_url' => $invoicedesign->header_photo_url,
            'footer_photo_url' => $invoicedesign->footer_photo_url,
            'header_height' => $invoicedesign->header_height,
            'footer_height' => $invoicedesign->footer_height,
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

    public function show($id)
    {
        return redirect()
            ->route('backend.invoicedesign.index')
            ->with('errorMessage', 'Direct view is not available. Please use Edit or Delete action.');
    }

    public function update(InvoiceDesignRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            $headerPath = $this->uploadRequestImage(
                $request,
                ['headerPhoto', 'header_photo'],
                ['headerPhotoPreview', 'header_photo_preview'],
                'invoicedesigns'
            );
            if ($headerPath) {
                $data['header_photo_path'] = $headerPath;
            }

            $footerPath = $this->uploadRequestImage(
                $request,
                ['footerPhoto', 'footer_photo'],
                ['footerPhotoPreview', 'footer_photo_preview'],
                'invoicedesigns'
            );
            if ($footerPath) {
                $data['footer_photo_path'] = $footerPath;
            }

            unset($data['headerPhoto'], $data['header_photo'], $data['footerPhoto'], $data['footer_photo']);

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
            Log::error('InvoiceDesign update failed', [
                'message' => $err->getMessage(),
                'file' => $err->getFile(),
                'line' => $err->getLine(),
            ]);
            $this->storeSystemError('Backend', 'InvoiceDesignController', 'update', substr($err->getMessage(), 0, 1000));
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

            if (!$invoicedesign) {
                DB::rollBack();

                return redirect()
                    ->back()
                    ->with('errorMessage', 'InvoiceDesign not found.');
            }

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
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function imageUpload($image, $folder)
    {
        $folder = trim((string) $folder, '/');
        if ($folder === '') {
            throw new \InvalidArgumentException('Upload folder is missing.');
        }

        if (!$image || !method_exists($image, 'isValid') || !$image->isValid()) {
            throw new \InvalidArgumentException('Upload file is missing or invalid.');
        }

        if (method_exists($image, 'getSize') && (int) $image->getSize() <= 0) {
            throw new \InvalidArgumentException('Upload file is empty.');
        }

        $realPath = method_exists($image, 'getRealPath') ? (string) $image->getRealPath() : '';
        if ($realPath === '' && method_exists($image, 'getPathname')) {
            $realPath = (string) $image->getPathname();
        }

        if ($realPath === '' || !is_readable($realPath)) {
            throw new \InvalidArgumentException('Upload file path is missing.');
        }

        $originalName = (string) $image->getClientOriginalName();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseSlug = Str::slug($baseName);
        if ($baseSlug === '') {
            $baseSlug = 'upload';
        }

        $extension = (string) $image->getClientOriginalExtension();
        if ($extension === '') {
            $extension = (string) $image->extension();
        }
        if ($extension === '') {
            $extension = 'bin';
        }

        $imageName = $baseSlug . '-' . uniqid() . '.' . $extension;

        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        $stream = fopen($realPath, 'r');
        if ($stream === false) {
            throw new \RuntimeException('Failed to read upload file stream.');
        }

        try {
            Storage::disk('public')->put($folder . '/' . $imageName, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $folder . '/' . $imageName;
    }

    private function hasReadableUploadPath($file)
    {
        if (!$file || !method_exists($file, 'getRealPath')) {
            return false;
        }

        $realPath = (string) $file->getRealPath();
        return $realPath !== '' && is_readable($realPath);
    }

    private function uploadRequestImage(Request $request, array $fileKeys, array $previewKeys, string $folder): ?string
    {
        $file = null;

        foreach ($fileKeys as $fileKey) {
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                if ($file) {
                    break;
                }
            }
        }

        if (!$file) {
            return null;
        }

        try {
            return $this->imageUpload($file, $folder);
        } catch (\Throwable $err) {
            foreach ($previewKeys as $previewKey) {
                $previewValue = (string) $request->input($previewKey, '');
                $fallbackPath = $this->uploadBase64Preview($previewValue, $folder, (string) $file->getClientOriginalName());
                if ($fallbackPath) {
                    return $fallbackPath;
                }
            }

            throw $err;
        }
    }

    private function uploadBase64Preview(string $previewValue, string $folder, string $originalName = ''): ?string
    {
        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $previewValue, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            return null;
        }

        $mimeExt = strtolower((string) $matches[1]);
        $extension = $mimeExt === 'jpeg' ? 'jpg' : $mimeExt;
        if ($extension === '') {
            $extension = 'bin';
        }

        $baseSlug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        if ($baseSlug === '') {
            $baseSlug = 'upload';
        }

        $folder = trim((string) $folder, '/');
        if ($folder === '') {
            return null;
        }

        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        $imageName = $baseSlug . '-' . uniqid() . '.' . $extension;
        Storage::disk('public')->put($folder . '/' . $imageName, $binary);

        return $folder . '/' . $imageName;
    }
}
