<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillingDoctorRequest;
use Illuminate\Support\Facades\DB;
use App\Services\BillingDoctorService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use App\Support\DefaultDeveloperManager;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class BillingDoctorController extends Controller
{
    use SystemTrait;

    protected $billingdoctorService;

    public function __construct(BillingDoctorService $billingdoctorService)
    {
        $this->billingdoctorService = $billingdoctorService;

        $this->middleware('auth:admin');

        $this->middleware('permission:billing-doctor-list', ['only' => ['index']]);
        $this->middleware('permission:billing-doctor-list-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:billing-doctor-list-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:billing-doctor-list-delete', ['only' => ['destroy']]);
        $this->middleware('permission:billing-doctor-list-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/BillingDoctor/Index',
            [
                'pageTitle' => fn() => 'Billing Doctor List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->dataFields(),
                'datas' => fn() => $this->getDatas(),
                'isDeveloper' => fn() => DefaultDeveloperManager::isDeveloper(auth('admin')->user()),
            ]
        );
    }

    public function downloadSampleCsv()
    {
        $this->ensureDeveloperAccess();

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Name', 'Status']);
            fclose($output);
        }, 'billing-doctors-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportCsv()
    {
        $this->ensureDeveloperAccess();

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Name', 'Status']);

            foreach ($this->billingdoctorService->all() as $doctor) {
                fputcsv($output, [$doctor->name, $doctor->status]);
            }

            fclose($output);
        }, 'billing-doctors-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importCsv(Request $request)
    {
        $this->ensureDeveloperAccess();

        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($validated['csv_file']->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors(['csv_file' => 'The uploaded CSV file could not be opened.']);
        }

        $headers = fgetcsv($handle);
        if (!is_array($headers)) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'The CSV file is empty or invalid.']);
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $headerIndexes = [];
        foreach ($headers as $index => $header) {
            $headerIndexes[strtolower(trim((string) $header))] = $index;
        }

        if (!array_key_exists('name', $headerIndexes)) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV must contain a Name column. Download the sample CSV for the correct format.']);
        }

        $rows = [];
        $rowErrors = [];
        $lineNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if (count(array_filter($row, static fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $name = trim((string) ($row[$headerIndexes['name']] ?? ''));
            $statusIndex = $headerIndexes['status'] ?? null;
            $status = $statusIndex !== null ? trim((string) ($row[$statusIndex] ?? 'Active')) : 'Active';
            $status = $status === '' ? 'Active' : ucfirst(strtolower($status));

            $validator = \Illuminate\Support\Facades\Validator::make(
                ['name' => $name, 'status' => $status],
                ['name' => ['required', 'string', 'max:255'], 'status' => ['required', 'in:Active,Inactive']]
            );

            if ($validator->fails()) {
                $rowErrors[] = 'Row ' . $lineNumber . ': ' . implode(' ', $validator->errors()->all());
                continue;
            }

            $rows[] = ['name' => $name, 'status' => $status];
        }
        fclose($handle);

        if ($rowErrors !== []) {
            return back()->withErrors(['csv_file' => implode(' ', $rowErrors)]);
        }
        if ($rows === []) {
            return back()->withErrors(['csv_file' => 'The CSV file contains no doctor rows.']);
        }

        $existingNames = $this->billingdoctorService->all()
            ->pluck('name')
            ->map(static fn ($name) => mb_strtolower(trim((string) $name)))
            ->all();
        $seenNames = array_fill_keys($existingNames, true);
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, &$seenNames, &$imported, &$skipped) {
            foreach ($rows as $row) {
                $normalizedName = mb_strtolower($row['name']);
                if (isset($seenNames[$normalizedName])) {
                    $skipped++;
                    continue;
                }

                $this->billingdoctorService->create($row);
                $seenNames[$normalizedName] = true;
                $imported++;
            }
        });

        return back()->with('successMessage', "Imported {$imported} billing doctors; skipped {$skipped} duplicates.");
    }

    private function ensureDeveloperAccess(): void
    {
        abort_unless(DefaultDeveloperManager::isDeveloper(auth('admin')->user()), 403);
    }

    private function getDatas()
    {
        $query = $this->billingdoctorService->list();

        if (request()->filled('name'))
            $query->where('name', 'like', '%' . request()->name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $user = auth('admin')->user();

        $formatedDatas = $datas->map(function ($data, $index) use ($user) {
            $customData = new \stdClass();
            $customData->index = $index + 1;
            $customData->name = $data->name;
            $customData->status = getStatusText($data->status);

            $customData->links = [];

            if ($user->can('billing-doctor-list-status')) {
                $customData->links[] = [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.billingdoctor.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ];
            }

            if ($user->can('billing-doctor-list-edit')) {
                $customData->links[] = [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.billingdoctor.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ];
            }

            if ($user->can('billing-doctor-list-delete')) {
                $customData->links[] = [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.billingdoctor.destroy', $data->id),
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
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Name',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/BillingDoctor/Form',
            [
                'pageTitle' => fn() => 'BillingDoctor Create',
                
            ]
        );
    }


    public function store(BillingDoctorRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('image'))
                $data['image'] = $this->imageUpload($request->file('image'), 'billingdoctors');

            if ($request->hasFile('file'))
                $data['file'] = $this->fileUpload($request->file('file'), 'billingdoctors');


            $dataInfo = $this->billingdoctorService->create($data);

            if ($dataInfo) {
                $message = 'BillingDoctor created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'billingdoctors', $message);

                // Log billing doctor creation
                ActivityLogService::logCreate(
                    module: 'BillingDoctor',
                    recordId: $dataInfo->id,
                    recordName: $dataInfo->name,
                    data: $request->validated()
                );

                DB::commit();

                if (($request->ajax() || $request->wantsJson()) && !$request->header('X-Inertia')) {
                    return response()->json(['successMessage' => $message]);
                }

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create BillingDoctor.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingDoctorController', 'store', substr($err->getMessage(), 0, 1000));

            $debugInfo =  config('app.debug') ? ' Details: ' . $err->getMessage() : '';
            DB::commit();
            $message = "Server Errors Occur. Please Try Again." . $debugInfo;
            if (($request->ajax() || $request->wantsJson()) && !$request->header('X-Inertia')) {
                return response()->json(['errorMessage' => $message], 500);
            }
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function edit($id)
    {
        $billingdoctor = $this->billingdoctorService->find($id);

        return Inertia::render(
            'Backend/BillingDoctor/Form',
            [
                'pageTitle' => fn() => 'BillingDoctor Edit',
                
                'billingdoctor' => fn() => $billingdoctor,
                'id' => fn() => $id,
            ]
        );
    }

    public function update(BillingDoctorRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $billingdoctor = $this->billingdoctorService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'billingdoctors');
                $path = strstr($billingdoctor->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($billingdoctor->image ?? '', 'billingdoctors');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'billingdoctors/');
                $path = strstr($billingdoctor->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($billingdoctor->file ?? '', 'billingdoctors/');
            }

            $dataInfo = $this->billingdoctorService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'BillingDoctor updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'billingdoctors', $message);

                DB::commit();

                if (($request->ajax() || $request->wantsJson()) && !$request->header('X-Inertia')) {
                    return response()->json(['successMessage' => $message]);
                }

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update billingdoctors.";
                if (($request->ajax() || $request->wantsJson()) && !$request->header('X-Inertia')) {
                    return response()->json(['errorMessage' => $message], 500);
                }
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingDoctorController', 'update', substr($err->getMessage(), 0, 1000));

            $debugInfo =  config('app.debug') ? ' Details: ' . $err->getMessage() : '';
            DB::commit();
            $message = "Server Errors Occur. Please Try Again." . $debugInfo;
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function destroy($id)
    {

        DB::beginTransaction();

        try {

            if ($this->billingdoctorService->delete($id)) {
                $message = 'BillingDoctor deleted successfully';
                $this->storeAdminWorkLog($id, 'billingdoctors', $message);

                DB::commit();

                if ((request()->ajax() || request()->wantsJson()) && !request()->header('X-Inertia')) {
                    return response()->json(['successMessage' => $message]);
                }

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete BillingDoctor.";
                if ((request()->ajax() || request()->wantsJson()) && !request()->header('X-Inertia')) {
                    return response()->json(['errorMessage' => $message], 500);
                }
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingDoctorController', 'destroy', substr($err->getMessage(), 0, 1000));

            $debugInfo =  config('app.debug') ? ' Details: ' . $err->getMessage() : '';
            DB::commit();
            $message = "Server Errors Occur. Please Try Again." . $debugInfo;
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function changeStatus(Request $request, $id, $status)
    {
        DB::beginTransaction();

        try {

            $dataInfo = $this->billingdoctorService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'BillingDoctor ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'billingdoctors', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "BillingDoctor.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'BillingDoctorController', 'changeStatus', substr($err->getMessage(), 0, 1000));

            $debugInfo =  config('app.debug') ? ' Details: ' . $err->getMessage() : '';
            DB::commit();
            $message = "Server Errors Occur. Please Try Again." . $debugInfo;
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
}
