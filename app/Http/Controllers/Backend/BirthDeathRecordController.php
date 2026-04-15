<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BirthDeathRecordRequest;
use App\Models\InvoiceDesign;
use Illuminate\Support\Facades\DB;
use App\Services\BirthDeathRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $this->middleware('auth:admin');
        $this->middleware('permission:birthdeathrecord-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:birthdeathrecord-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:birthdeathrecord-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:birthdeathrecord-delete', ['only' => ['destroy']]);
        $this->middleware('permission:birthdeathrecord-status', ['only' => ['changeStatus']]);
        $this->middleware('permission:birthdeathrecord-list', ['only' => ['printCertificate']]);
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
            $customData->case_id = $data->case_id ?? '-';
            $customData->record_type = $data->record_type ?? '-';
            $customData->record_date = $data->record_date
                ? Carbon::parse($data->record_date)->format('d M Y')
                : '-';
            $customData->relative_name = $data->record_type === 'Birth'
                ? ($data->mother_name ?? '-')
                : ($data->guardian_name ?? '-');
            $customData->phone = $data->phone ?? '-';
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
                    'linkClass' => 'bg-indigo-600 text-white semi-bold',
                    'link' => route('backend.birthdeathrecord.certificate.print', $data->id),
                    'linkLabel' => getLinkLabel('Print Certificate', null, null)
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
            ['fieldName' => 'case_id', 'class' => 'text-center'],
            ['fieldName' => 'record_type', 'class' => 'text-center'],
            ['fieldName' => 'record_date', 'class' => 'text-center'],
            ['fieldName' => 'photo', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'relative_name', 'class' => 'text-center'],
            ['fieldName' => 'phone', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Case ID',
            'Record Type',
            'Record Date',
            'Photo',
            'Name',
            'Mother/Guardian',
            'Phone',
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

            if ($data['record_type'] === 'Birth') {
                $data['name'] = $data['child_name'] ?? null;
                $data['record_date'] = $data['birth_date'] ?? null;
            } else {
                $data['name'] = $data['patient_name'] ?? null;
                $data['record_date'] = $data['death_date'] ?? null;
            }

            foreach (['child_photo', 'mother_photo', 'father_photo'] as $imageField) {
                if ($request->hasFile($imageField)) {
                    $data[$imageField] = $this->imageUpload($request->file($imageField), 'birthdeathrecords');
                }
            }

            foreach (['attachment', 'report_attachment'] as $fileField) {
                if ($request->hasFile($fileField)) {
                    $data[$fileField] = $this->fileUpload($request->file($fileField), 'birthdeathrecords');
                }
            }

            if (!empty($data['child_photo']) && empty($data['photo'])) {
                $data['photo'] = $data['child_photo'];
            }
            if (!empty($data['attachment']) && empty($data['photo'])) {
                $data['photo'] = $data['attachment'];
            }


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

            if ($data['record_type'] === 'Birth') {
                $data['name'] = $data['child_name'] ?? null;
                $data['record_date'] = $data['birth_date'] ?? null;
            } else {
                $data['name'] = $data['patient_name'] ?? null;
                $data['record_date'] = $data['death_date'] ?? null;
            }

            foreach (['child_photo', 'mother_photo', 'father_photo'] as $imageField) {
                if ($request->hasFile($imageField)) {
                    $data[$imageField] = $this->imageUpload($request->file($imageField), 'birthdeathrecords');
                } else {
                    $data[$imageField] = strstr((string) ($birthdeathrecord->{$imageField} ?? ''), 'birthdeathrecords');
                }
            }

            foreach (['attachment', 'report_attachment'] as $fileField) {
                if ($request->hasFile($fileField)) {
                    $data[$fileField] = $this->fileUpload($request->file($fileField), 'birthdeathrecords');
                } else {
                    $data[$fileField] = strstr((string) ($birthdeathrecord->{$fileField} ?? ''), 'birthdeathrecords');
                }
            }

            if (!empty($data['child_photo'])) {
                $data['photo'] = $data['child_photo'];
            } elseif (!empty($data['attachment'])) {
                $data['photo'] = $data['attachment'];
            } else {
                $data['photo'] = strstr((string) ($birthdeathrecord->photo ?? ''), 'birthdeathrecords');
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

    public function printCertificate($id)
    {
        $record = $this->birthdeathrecordService->find($id);

        if (!$record || $record->deleted_at) {
            return redirect()->back()->with('errorMessage', 'Record not found.');
        }

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', 'billing')->first();
        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }
        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

        $headerImage = $this->resolvePublicStorageImageDataUri((string) ($invoiceDesign?->header_photo_path ?? ''));
        $footerImage = $this->resolvePublicStorageImageDataUri((string) ($invoiceDesign?->footer_photo_path ?? ''));
        $footerContent = trim((string) ($invoiceDesign?->footer_content ?? ''));

        return view('backend.birthdeathrecord.certificate_print', [
            'record' => $record,
            'webSetting' => get_cached_web_setting(),
            'header_image' => $headerImage,
            'footer_image' => $footerImage,
            'footer_content' => $footerContent,
            'autoPrint' => request()->boolean('auto_print', true),
        ]);
    }

    private function resolvePublicStorageImageDataUri(?string $path): string
    {
        $rawPath = trim((string) $path);
        if ($rawPath === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $rawPath);
        $normalized = ltrim($normalized, '/');

        $candidates = array_values(array_unique(array_filter([
            $normalized,
            preg_replace('#^storage/#i', '', $normalized),
            preg_replace('#^public/#i', '', $normalized),
            preg_replace('#^public/storage/#i', '', $normalized),
        ])));

        $resolvedPath = null;
        foreach ($candidates as $candidate) {
            $fullPath = storage_path('app/public/' . ltrim($candidate, '/'));
            if (file_exists($fullPath)) {
                $resolvedPath = $fullPath;
                break;
            }
        }

        if ($resolvedPath === null) {
            return '';
        }

        $extension = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($resolvedPath));
    }
}