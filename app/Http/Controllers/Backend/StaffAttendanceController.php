<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffAttendanceRequest;
use App\Models\StaffAttendance;
use App\Services\AdminService;
use App\Services\RoleService;
use Illuminate\Support\Facades\DB;
use App\Services\StaffAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Carbon\Carbon;
use Exception;
use Ramsey\Uuid\Type\Decimal;
use Barryvdh\DomPDF\Facade\Pdf;

class StaffAttendanceController extends Controller
{
    use SystemTrait;

    protected $staffAttendanceService, $RoleService, $adminService;

    public function __construct(StaffAttendanceService $staffAttendanceService, RoleService $RoleService, AdminService $adminService)
    {
        $this->staffAttendanceService = $staffAttendanceService;
        $this->RoleService = $RoleService;
        $this->adminService = $adminService;


        $this->middleware('auth:admin');
        $this->middleware('permission:staff-attendance-list');
    }

    public function index()
    {
        return Inertia::render(
            'Backend/StaffAttendance/Index',
            [
                'pageTitle' => fn() => 'Staff Attendance List',
                'tableHeaders' => fn() => $this->getTableHeaders(),
                'dataFields' => fn() => $this->getDataFields(),
                'datas' => fn() => $this->getDatas(),
            ]
        );
    }

    private function getDataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'staff_id', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'attendance_date', 'class' => 'text-center'],
            ['fieldName' => 'attendance_status', 'class' => 'text-center'],
            ['fieldName' => 'in_time', 'class' => 'text-center'],
            ['fieldName' => 'out_time', 'class' => 'text-center'],
            ['fieldName' => 'note', 'class' => 'text-center'],
            ['fieldName' => 'status', 'class' => 'text-center'],
        ];
    }

    private function getTableHeaders()
    {
        return [
            'Sl/No',
            'Staff Id',
            'Name',
            'Attendance Date',
            'Attendance Status',
            'In Time',
            'Out Time',
            'Note',
            'Status',
            'Action'
        ];
    }

    private function getDatas()
    {
        $query = $this->staffAttendanceService->list();

        if (request()->filled('staff_id'))
            $query->where('staff_id', 'like', '%' . request()->staff_id . '%');

        if (request()->filled('name'))
            $query->where('name', 'like', '%' . request()->name . '%');

        if (request()->filled('from_date') && request()->filled('to_date')) {
            $query->whereBetween('attendance_date', [request()->from_date, request()->to_date]);
        } elseif (request()->filled('from_date')) {
            $query->whereDate('attendance_date', request()->from_date);
        } else {
            $query->whereDate('attendance_date', now());
        }

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;

            $customData->staff_id = $data->staff_id;
            $customData->name = $data->name;
            $customData->attendance_date = Carbon::parse($data->attendance_date)->format('m/d/Y');
            $customData->attendance_status = $data->attendance_status;
            $customData->in_time = Carbon::parse($data->in_time)->format('h:i A');
            $customData->out_time = Carbon::parse($data->out_time)->format('h:i A');
            $customData->note = $data->note;


            $customData->status = getStatusText($data->status);
            $customData->hasLink = true;
            $customData->links = [

                [
                    'linkClass' => 'semi-bold text-white statusChange ' . (($data->status == 'Active') ? "bg-gray-500" : "bg-green-500"),
                    'link' => route('backend.staffattendance.status.change', ['id' => $data->id, 'status' => $data->status == 'Active' ? 'Inactive' : 'Active']),
                    'linkLabel' => getLinkLabel((($data->status == 'Active') ? "Inactive" : "Active"), null, null)
                ],

                [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.staffattendance.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ],
            ];
            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    public function create()
    {
        $roleDetails = $this->RoleService->all();
        $users = $this->adminService->activeList();
        return Inertia::render(
            'Backend/StaffAttendance/Form',
            [
                'pageTitle' => fn() => 'Staff Attendance Create',
                "roleDetails" => fn() => $roleDetails,
                "users" => fn() => $users,
            ]
        );
    }

    public function fetchDate(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = $request->input('date');
        $records = $this->staffAttendanceService->getRecordsByDate($date);

        return response()->json(['records' => $records]);
    }

    public function store(StaffAttendanceRequest $request)
    {
        DB::beginTransaction();
        try {
            $validatedData = $request->validated();

            foreach ($validatedData['records'] as $record) {
                $existingRecord = $this->staffAttendanceService->findByDateAndStaffId(
                    $validatedData['attendance_date'],
                    $record['staff_id']
                );

                if ($existingRecord) {
                    $updatedRecord = $this->staffAttendanceService->update([
                        'attendance_status' => $record['attendance_status'],
                        'in_time' => $record['in_time'],
                        'out_time' => $record['out_time'],
                        'note' => $record['note'],
                    ], $existingRecord->id);

                    if ($updatedRecord) {
                        $message = 'StaffAttendance updated successfully for staff ID: ' . $record['staff_id'];
                        $this->storeAdminWorkLog($updatedRecord->id, 'staff_attendances', $message);
                    }
                } else {
                    $dataInfo = $this->staffAttendanceService->create([
                        'staff_id' => $record['staff_id'],
                        'name' => $record['name'],
                        'attendance_date' => $validatedData['attendance_date'],
                        'attendance_status' => $record['attendance_status'],
                        'in_time' => $record['in_time'],
                        'out_time' => $record['out_time'],
                        'note' => $record['note'],
                    ]);

                    if ($dataInfo) {
                        $message = 'StaffAttendance created successfully for staff ID: ' . $record['staff_id'];
                        $this->storeAdminWorkLog($dataInfo->id, 'staff_attendances', $message);
                    } else {
                        throw new Exception("Failed to create StaffAttendance for staff ID: " . $record['staff_id']);
                    }
                }
            }

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', 'All StaffAttendance records processed successfully.');
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'StaffAttendanceController', 'store', substr($err->getMessage(), 0, 1000));

            $message = "Server Errors Occurred. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }


    public function edit($id)
    {
        $staffattendance = $this->staffAttendanceService->find($id);
        $users = $this->adminService->find($staffattendance->staff_id); 
        return Inertia::render(
            'Backend/StaffAttendance/Form',
            [
                'pageTitle' => fn() => 'Staff Attendance Edit',
                'staffattendance' => fn() => $staffattendance,
                'id' => fn() => $id,
                "users" => fn() => [$users],
            ]
        );
    }

    public function update(StaffAttendanceRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $validatedData = $request->validated();
            $staffAttendance = $this->staffAttendanceService->find($id);

            foreach ($validatedData['records'] as $record) {

                $existingRecord = $this->staffAttendanceService->findByDateAndStaffId(
                    $validatedData['attendance_date'],
                    $record['staff_id']
                );

                if ($existingRecord) {
                    $updatedRecord = $this->staffAttendanceService->update([
                        'attendance_status' => $record['attendance_status'],
                        'in_time' => $record['in_time'],
                        'out_time' => $record['out_time'],
                        'note' => $record['note'],
                    ], $existingRecord->id);

                    if ($updatedRecord) {
                        $message = 'StaffAttendance updated successfully for staff ID: ' . $record['staff_id'];
                        $this->storeAdminWorkLog($updatedRecord->id, 'staff_attendances', $message);
                    }
                }
            }
            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', 'All StaffAttendance records processed successfully.');
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'StaffAttendanceController', 'update', substr($err->getMessage(), 0, 1000));

            $message = "Server Errors Occurred. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }


    public function destroy($id)
    {

        DB::beginTransaction();

        try {

            if ($this->staffAttendanceService->delete($id)) {
                $message = 'StaffAttendance deleted successfully';
                $this->storeAdminWorkLog($id, 'staff_attendances', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To Delete StaffAttendance.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'StaffAttendancecontroller', 'destroy', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->with('errorMessage', $message);
        }
    }

    public function changeStatus()
    {
        DB::beginTransaction();

        try {
            $dataInfo = $this->staffAttendanceService->changeStatus(request());

            if ($dataInfo->wasChanged()) {
                $message = 'StaffAttendance ' . request()->status . ' Successfully';
                $this->storeAdminWorkLog($dataInfo->id, 'staff_attendances', $message);

                DB::commit();

                return redirect()
                    ->back()
                    ->with('successMessage', $message);
            } else {
                DB::rollBack();

                $message = "Failed To " . request()->status . " StaffAttendance.";
                return redirect()
                    ->back()
                    ->with('errorMessage', $message);
            }
        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'StaffAttendanceController', 'changeStatus', substr($err->getMessage(), 0, 1000));
            DB::commit();
            $message = "Server Errors Occur. Please Try Again.";
            return redirect()
                ->back()
                ->withErrors(['error' => $message]);
        }
    }

    public function attendanceReport()
    {
        $users = $this->adminService->activeList();
        return Inertia::render(
            'Backend/StaffAttendance/Report',
            [
                'pageTitle' => fn() => 'Staff Attendance Report',
                'breadcrumbs' => fn() => [
                    ['link' => null, 'title' => 'Staff Attendance Report Manage'],
                    ['link' => route('backend.staffattendance.index'), 'title' => 'Staff Attendance Report'],
                ],
                'tableHeaders' => fn() => $this->getReportTableHeaders(),
                'dataFields' => fn() => $this->getReportDataFields(),
                'datas' => fn() => $this->getReportDatas(),
                'users' => fn() => $users,
            ]
        );
    }

    private function getReportTableHeaders()
    {
        return [
            'Sl/No',
            'Staff Id',
            'Name',
            'Role',
            'Mobile',
            'Action'
        ];
    }

    private function getReportDataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'staff_id', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'role', 'class' => 'text-center'],
            ['fieldName' => 'mobile', 'class' => 'text-center'],
        ];
    }

    private function getReportDatas()
    {
        $query = $this->adminService->list();

        if (request()->filled('staff_id'))
            $query->where('staff_id', 'like', '%' . request()->staff_id . '%');

        if (request()->filled('name'))
            $query->where('name', 'like', '%' . request()->name . '%');

        $datas = $query->paginate(request()->numOfData ?? 10)->withQueryString();

        $formatedDatas = $datas->map(function ($data, $index) {
            $customData = new \stdClass();
            $customData->index = $index + 1;

            $customData->staff_id = $data->id;
            $customData->name = $data->name;
            $customData->role = $data->role->name;
            $customData->mobile = $data->phone;


            $customData->status = getStatusText($data->status);
            $customData->hasLink = true;
            $customData->links = [

                [
                    'linkClass' => 'bg-green-400 text-black semi-bold',
                    'link' => route('backend.staffattendance.edit', $data->id),
                    'linkLabel' => getLinkLabel('Edit', null, null)
                ],
                // [
                //     'linkClass' => 'bg-gray-400 text-black semi-bold',
                //     'link' => route('backend.staff.payslip', $data->id),
                //     'linkLabel' => getLinkLabel('View Payslip', null, null)
                // ],
                [
                    'linkClass' => 'bg-yellow-400 text-black semi-bold',
                    'link' => route('backend.staffattendance.report.details', $data->id),
                    'linkLabel' => getLinkLabel('Attendance Report', null, null)
                ],
            ];
            return $customData;
        });

        return regeneratePagination($formatedDatas, $datas->total(), $datas->perPage(), $datas->currentPage());
    }

    public function attendanceReportDetails($id)
    {
        $user = $this->adminService->find($id);

        $attendanceInfo = $this->adminService->getAttendanceDataByStaffId($id);
        $attendanceData = $attendanceInfo['attendanceData'];
        $leaves = $attendanceInfo['leaves'];
        $totals = $attendanceInfo['totals'];

        return Inertia::render(
            'Backend/StaffAttendance/ReportDetails',
            [
                'pageTitle' => fn() => 'Staff Attendance Report Details',
                'breadcrumbs' => fn() => [
                    ['link' => null, 'title' => 'Staff Attendance Report Manage'],
                    ['link' => route('backend.staffattendance.report.details', ['id' => $id]), 'title' => 'Staff Attendance Report Details'],
                ],
                'tableHeaders' => fn() => $this->getReportInfoTableHeaders(),
                'dataFields' => fn() => $this->getReportInfoDataFields(),
                'datas' => fn() => $this->getReportInfoDatas($id, $attendanceData, $totals),
                'dateDatas' => fn() => ['attendanceData' => $attendanceData],
                'user' => fn() => $user,
                'staffId' => $id,
                'staffName' => $user?->name,
                'totals' => $totals,
                'leaves' => $leaves,
            ]
        );
    }

    private function getReportInfoTableHeaders()
    {
        return [
            'Sl/No',
            'Staff Id',
            'Name',
            'Present',
            'Late',
            'Absent',
            'Holiday',
        ];
    }

    private function getReportInfoDataFields()
    {
        return [
            ['fieldName' => 'index', 'class' => 'text-center'],
            ['fieldName' => 'staff_id', 'class' => 'text-center'],
            ['fieldName' => 'name', 'class' => 'text-center'],
            ['fieldName' => 'present', 'class' => 'text-center'],
            ['fieldName' => 'late', 'class' => 'text-center'],
            ['fieldName' => 'absent', 'class' => 'text-center'],
            ['fieldName' => 'holiday', 'class' => 'text-center'],
        ];
    }

    private function getReportInfoDatas($staffId, $attendanceData, $totals)
    {
        $attendanceRecord = $this->adminService->getAttendanceRecordsForStaff($staffId);

        if (!$attendanceRecord) {
            return [];
        }

        $customData = new \stdClass();
        $customData->index = 1;
        $customData->staff_id = $attendanceRecord->id;
        $customData->name = $attendanceRecord->name;
        $customData->present = $totals['present'];
        $customData->late = $totals['late'];
        $customData->absent = $totals['absent'];
        $customData->holiday = $totals['holiday'];

        return regeneratePagination([$customData], 1, 1, 1);
    }

    private function getDateReportInfoDatas($staffId)
    {
        $month = request()->input('month');
        $year = request()->input('year');

        $attendanceRecords = $this->staffAttendanceService->getAttendanceRecordsForMonth($staffId, $month, $year);

        if (!$attendanceRecords) {
            return [];
        }

        $attendanceData = [];
        foreach ($attendanceRecords as $record) {

            $attendanceData[] = [
                'date' => $record->attendance_date,
                'status' => $record->attendance_status,
            ];
        }

        $attendanceInfo = $this->adminService->getAttendanceDataByStaffId($staffId);
        $totals = $attendanceInfo['totals'];
        $staff = $this->adminService->getAttendanceRecordsForStaff($staffId);

        return [
            'attendanceData' => $attendanceData,
            'totals' => $totals,
            'staffName' => $staff->name,
            'staffId' => $staff->id,
        ];
    }

    public function getAttendanceRecordsForMonth($staffId, $month, $year)
    {
        return StaffAttendance::where('staff_id', $staffId)
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->get();
    }

    public function staffPaySlip($id)
    {
        $request = request();
        $monthParam = $request->input('month');
        $unpaidDays = (int)$request->input('unpaidDays');

        [$year, $month] = explode('-', $monthParam);
        $month = (int)$month;
        $year = (int)$year;

        $staffDetails = $this->adminService->find($id);

        $attendanceInfo = $this->adminService->getAttendanceDataByStaffId($id);

        $grossSalary = $this->calculateGrossSalary($staffDetails);

        $netSalary = $this->calculateNetSalary($month, $grossSalary, $unpaidDays);

        return Inertia::render(
            'Backend/StaffAttendance/PaySlip',
            [
                'pageTitle' => fn() => 'Staff Payslip',
                'breadcrumbs' => fn() => [
                    ['link' => null, 'title' => 'Staff Payslip'],
                    ['link' => route('backend.staff.payslip', ['id' => $id]), 'title' => 'Staff Payslip'],
                ],
                'staffDetails' => fn() => $staffDetails,
                'attendanceInfo' => fn() => $attendanceInfo,
                'grossSalary' => fn() => $grossSalary,
                'netSalary' => fn() => $netSalary,
            ]
        );
    }

    private function calculateGrossSalary($staffDetails)
    {
        $baseSalary = floatval($staffDetails->salary);
        $commissionPercentage = $staffDetails->commission;

        $commissionAmount = ($baseSalary * $commissionPercentage) / 100;
        $grossSalary = $baseSalary + $commissionAmount;

        return floatval($grossSalary);
    }


    private function calculateNetSalary($month, $grossSalary, $unpaidDays)
    {
        $totalWorkingDays = 30;

        $paidDays = $totalWorkingDays - $unpaidDays;

        $dailySalary = $grossSalary / $totalWorkingDays;

        $finalSalary = $dailySalary * $paidDays;

        return floatval($finalSalary);
    }

    public function download(Request $request)
    {
        $request->validate([
            'payslipData' => 'required|string',
        ]);

        $data = json_decode($request->input('payslipData'), true);

        $pdf = Pdf::loadView('backend.payslipPdf.slip', ['data' => $data]);

        return $pdf->stream('payslip.pdf');
    }
}
