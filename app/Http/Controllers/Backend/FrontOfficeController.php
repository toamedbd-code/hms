<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontOfficeRequest;
use Illuminate\Support\Facades\DB;
use App\Services\FrontOfficeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Carbon\Carbon;
use Exception;

class FrontOfficeController extends Controller
{
    use SystemTrait;

    protected $frontofficeService;

    public function __construct(FrontOfficeService $frontofficeService)
    {
        $this->frontofficeService = $frontofficeService;
        $this->middleware('auth:admin');
        $this->middleware('permission:frontoffice-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:frontoffice-create', ['only' => ['create', 'store', 'import']]);
        $this->middleware('permission:frontoffice-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:frontoffice-delete', ['only' => ['destroy']]);
        $this->middleware('permission:frontoffice-status', ['only' => ['changeStatus']]);
    }



    public function index()
    {
        return Inertia::render(
            'Backend/FrontOffice/Index',
            [
                'pageTitle' => fn () => 'Visitor List',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Visitor Manage'],
                    ['link' => route('backend.frontoffice.index'), 'title' => 'Visitor List'],
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
            $customData->purpose = $data->purpose;
            $customData->name = $data->name;
            $customData->visit_to = $data->visit_to;
            $customData->phone = $data->phone;
            $customData->date_in = $data->date_in;
            $customData->time_in = $data->time_in;
            $customData->time_out = $data->time_out;
            $customData->status = getStatusText($data->status);
            $customData->status_text = $data->status;

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
            ['fieldName' => 'purpose', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'visit_to', 'class' => 'text-center'],
            ['fieldName' => 'phone', 'class' => 'text-center'],
            ['fieldName' => 'date_in', 'class' => 'text-center'],
            ['fieldName' => 'time_in', 'class' => 'text-center'],
            ['fieldName' => 'time_out', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }
    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Purpose',
            'Name',
            'Visit To',
            'Phone Number',
            'Date In',
            'Time In',
            'Time Out',
            'Status',
            'Action',
        ];
    }

    public function create()
    {
        return Inertia::render(
            'Backend/FrontOffice/Form',
            [
                'pageTitle' => fn () => 'Visitor Create',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Visitor Manage'],
                    ['link' => route('backend.frontoffice.create'), 'title' => 'Visitor Create'],
                ],
            ]
        );
    }


    public function store(FrontOfficeRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('photo')) {
                $data['photo'] = $this->imageUpload($request->file('photo'), 'frontoffices');
            }


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
                'pageTitle' => fn () => 'Visitor Edit',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'Visitor Manage'],
                    ['link' => route('backend.frontoffice.edit', $id), 'title' => 'Visitor Edit'],
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

            if ($request->hasFile('photo')) {
                $data['photo'] = $this->imageUpload($request->file('photo'), 'frontoffices');
            } else {
                $data['photo'] = strstr((string) ($frontoffice->photo ?? ''), 'frontoffices');
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

    public function import(Request $request)
    {
        $rows = $request->input('rows', []);

        if (!is_array($rows) || empty($rows)) {
            return redirect()
                ->back()
                ->with('errorMessage', 'No valid rows found in uploaded file.');
        }

        DB::beginTransaction();

        try {
            $createdCount = 0;
            $skippedCount = 0;
            $errorMessages = [];

            foreach ($rows as $index => $row) {
                $payload = [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'purpose' => trim((string) ($row['purpose'] ?? '')),
                    'visit_to' => trim((string) ($row['visit_to'] ?? '')),
                    'phone' => trim((string) ($row['phone'] ?? '')),
                    'date_in' => trim((string) ($row['date_in'] ?? '')),
                    'time_in' => trim((string) ($row['time_in'] ?? '')),
                    'time_out' => trim((string) ($row['time_out'] ?? '')),
                ];

                $validator = Validator::make($payload, [
                    'name' => 'required|string|max:255',
                    'purpose' => 'required|string|max:255',
                    'visit_to' => 'required|string|max:255',
                    'phone' => 'required|string|max:30',
                    'date_in' => 'required',
                    'time_in' => 'required',
                    'time_out' => 'nullable',
                ]);

                if ($validator->fails()) {
                    $skippedCount++;
                    if (count($errorMessages) < 5) {
                        $errorMessages[] = 'Row ' . ($index + 2) . ': ' . implode(', ', $validator->errors()->all());
                    }
                    continue;
                }

                try {
                    $payload['date_in'] = Carbon::parse($payload['date_in'])->format('Y-m-d');
                    $payload['time_in'] = Carbon::parse($payload['time_in'])->format('H:i');
                    $payload['time_out'] = $payload['time_out'] !== ''
                        ? Carbon::parse($payload['time_out'])->format('H:i')
                        : null;

                    $dataInfo = $this->frontofficeService->create($payload);

                    if ($dataInfo) {
                        $createdCount++;
                    } else {
                        $skippedCount++;
                        if (count($errorMessages) < 5) {
                            $errorMessages[] = 'Row ' . ($index + 2) . ': failed to create visitor.';
                        }
                    }
                } catch (Exception $rowException) {
                    $skippedCount++;
                    if (count($errorMessages) < 5) {
                        $errorMessages[] = 'Row ' . ($index + 2) . ': invalid date/time format.';
                    }
                }
            }

            if ($createdCount <= 0) {
                DB::rollBack();

                $message = 'Import failed. No rows were created.';
                if (!empty($errorMessages)) {
                    $message .= ' ' . implode(' | ', $errorMessages);
                }

                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }

            DB::commit();

            $message = 'Visitor import completed. Created: ' . $createdCount . ', Skipped: ' . $skippedCount . '.';
            if (!empty($errorMessages)) {
                $message .= ' Issues: ' . implode(' | ', $errorMessages);
            }

            return redirect()
                ->back()
                ->with('successMessage', $message);
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'FrontOfficeController', 'import', substr($err->getMessage(), 0, 1000));
            DB::commit();

            return redirect()
                ->back()
                ->with('errorMessage', 'Server Errors Occur. Please Try Again.');
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