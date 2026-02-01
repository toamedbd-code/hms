<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnnualCalendarRequest;
use Illuminate\Support\Facades\DB;
use App\Services\AnnualCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class AnnualCalendarController extends Controller
{
    use SystemTrait;

    protected $annualcalendarService;

    public function __construct(AnnualCalendarService $annualcalendarService)
    {
        $this->annualcalendarService = $annualcalendarService;
    }



    public function index()
    {
        return Inertia::render(
            'Backend/AnnualCalendar/Index',
            [
                'pageTitle' => fn () => 'AnnualCalendar List',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'AnnualCalendar Manage'],
                    ['link' => route('backend.annualcalendar.index'), 'title' => 'AnnualCalendar List'],
                ],
                'tableHeaders' => fn () => $this->getTableHeaders(),
                'dataFields' => fn () => $this->dataFields(),
                'datas' => fn () => $this->getDatas(),
            ]
        );
    }

    private function getDatas()
    {
        $query = $this->annualcalendarService->list();

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
                    'link' => route('backend.annualcalendar.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ],
                [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.annualcalendar.edit',  $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ],
                [
                    'linkClass' => 'deleteButton bg-red-500 text-white semi-bold',
                    'link' => route('backend.annualcalendar.destroy', $data->id),
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
            'Backend/AnnualCalendar/Form',
            [
                'pageTitle' => fn () => 'AnnualCalendar Create',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'AnnualCalendar Manage'],
                    ['link' => route('backend.annualcalendar.create'), 'title' => 'AnnualCalendar Create'],
                ],
            ]
        );
    }


    public function store(AnnualCalendarRequest $request)
    {

        DB::beginTransaction();
        try {

            $data = $request->validated();

            if ($request->hasFile('image'))
                $data['image'] = $this->imageUpload($request->file('image'), 'annualcalendars');

            if ($request->hasFile('file'))
                $data['file'] = $this->fileUpload($request->file('file'), 'annualcalendars');


            $dataInfo = $this->annualcalendarService->create($data);

            if ($dataInfo) {
                $message = 'AnnualCalendar created successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'annualcalendars', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To create AnnualCalendar.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            //   dd($err);
            DB::rollBack();
            $this->storeSystemError('Backend', 'AnnualCalendarController', 'store', substr($err->getMessage(), 0, 1000));
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
        $annualcalendar = $this->annualcalendarService->find($id);

        return Inertia::render(
            'Backend/AnnualCalendar/Form',
            [
                'pageTitle' => fn () => 'AnnualCalendar Edit',
                'breadcrumbs' => fn () => [
                    ['link' => null, 'title' => 'AnnualCalendar Manage'],
                    ['link' => route('backend.annualcalendar.edit', $id), 'title' => 'AnnualCalendar Edit'],
                ],
                'annualcalendar' => fn () => $annualcalendar,
                'id' => fn () => $id,
            ]
        );
    }

    public function update(AnnualCalendarRequest $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            $annualcalendar = $this->annualcalendarService->find($id);

            if ($request->hasFile('image')) {
                $data['image'] = $this->imageUpload($request->file('image'), 'annualcalendars');
                $path = strstr($annualcalendar->image, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['image'] = strstr($annualcalendar->image ?? '', 'annualcalendars');
            }

            if ($request->hasFile('file')) {
                $data['file'] = $this->fileUpload($request->file('file'), 'annualcalendars/');
                $path = strstr($annualcalendar->file, 'storage/');
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {

                $data['file'] = strstr($annualcalendar->file ?? '', 'annualcalendars/');
            }

            $dataInfo = $this->annualcalendarService->update($data, $id);

            if ($dataInfo->save()) {
                $message = 'AnnualCalendar updated successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'annualcalendars', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To update annualcalendars.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AnnualCalendarController', 'update', substr($err->getMessage(), 0, 1000));
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

            if ($this->annualcalendarService->delete($id)) {
                $message = 'AnnualCalendar deleted successfully';
                $this->storeAdminWorkLog($id, 'annualcalendars', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete AnnualCalendar.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AnnualCalendarController', 'destroy', substr($err->getMessage(), 0, 1000));
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

            $dataInfo = $this->annualcalendarService->changeStatus($id, $status);

            if ($dataInfo->wasChanged()) {
                $message = 'AnnualCalendar ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'annualcalendars', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . "AnnualCalendar.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'AnnualCalendarController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }
        }