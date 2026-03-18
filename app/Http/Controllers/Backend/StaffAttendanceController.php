<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffAttendanceRequest;
use App\Models\Admin;
use App\Models\ApplyLeave;
use App\Models\Attendance;
use App\Models\AdminDetail;
use App\Models\DutyRoster;
use App\Models\StaffAttendance;
use App\Models\WebSetting;
use App\Services\AdminService;
use App\Services\RoleService;
use Illuminate\Support\Facades\DB;
use App\Services\StaffAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Models\SalaryPayment;
use Illuminate\Support\Facades\Auth;
use App\Traits\SystemTrait;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
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
        // Require explicit permission to perform salary payments
        $this->middleware('permission:salary-sheet-pay')->only('salaryPay');
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
        // added route link for duty roster menu usage
        $websetting = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();
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
                'websetting' => fn() => [
                    'attendance_device_options' => $websetting?->attendance_device_options,
                ],
            ]
        );
    }

    public function salarySheet(Request $request)
    {
        $websetting = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();
        $monthInput = (string) $request->input('month', now()->format('Y-m'));

        try {
            $monthDate = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable $th) {
            $monthDate = now()->startOfMonth();
            $monthInput = $monthDate->format('Y-m');
        }

        $startDate = $monthDate->copy()->startOfMonth()->toDateString();
        $endDate = $monthDate->copy()->endOfMonth()->toDateString();
        $totalDaysInMonth = $monthDate->daysInMonth;

        $staffList = Admin::query()
            ->with(['role:id,name', 'details.department:id,department_name', 'details.designation:id,designation_name'])
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $staffIds = $staffList->pluck('id')->map(fn($id) => (int) $id)->all();

        $rosterRows = DutyRoster::query()
            ->select('staff_id', 'date')
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('staff_id', $staffIds)
            ->get()
            ->groupBy('staff_id');

        $attendanceRows = StaffAttendance::query()
            ->select('staff_id', 'attendance_date', 'attendance_status')
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->whereIn('staff_id', $staffList->pluck('id')->all())
            ->get()
            ->groupBy('staff_id');

        $approvedLeaves = ApplyLeave::query()
            ->select('employee_id', 'from', 'to')
            ->where('status', 'Approved')
            ->whereIn('employee_id', $staffIds)
            ->where(function ($query) use ($startDate, $endDate) {
                $query
                    ->whereBetween('from', [$startDate, $endDate])
                    ->orWhereBetween('to', [$startDate, $endDate])
                    ->orWhere(function ($nested) use ($startDate, $endDate) {
                        $nested->where('from', '<=', $startDate)
                            ->where('to', '>=', $endDate);
                    });
            })
            ->get()
            ->groupBy('employee_id');

        $staffCodeToAdminId = AdminDetail::query()
            ->whereIn('admin_id', $staffIds)
            ->whereNotNull('staff_id')
            ->pluck('admin_id', 'staff_id');

        $attendanceFinancials = collect();
        if (!empty($staffIds)) {
            $attendanceRowsRaw = Attendance::query()
                ->select('employee_code', 'deduction_amount', 'overtime_amount')
                ->whereDate('recorded_at', '>=', $startDate)
                ->whereDate('recorded_at', '<=', $endDate)
                ->whereNotNull('recorded_out')
                ->get();

            $attendanceFinancials = $attendanceRowsRaw
                ->map(function ($row) use ($staffCodeToAdminId, $staffIds) {
                    $employeeCode = (string) ($row->employee_code ?? '');
                    $adminId = null;

                    if ($employeeCode !== '' && ctype_digit($employeeCode)) {
                        $numericId = (int) $employeeCode;
                        if (in_array($numericId, $staffIds, true)) {
                            $adminId = $numericId;
                        }
                    }

                    if ($adminId === null && $employeeCode !== '' && $staffCodeToAdminId->has($employeeCode)) {
                        $adminId = (int) $staffCodeToAdminId->get($employeeCode);
                    }

                    if ($adminId === null) {
                        return null;
                    }

                    return [
                        'staff_id' => $adminId,
                        'deduction' => (float) ($row->deduction_amount ?? 0),
                        'overtime' => (float) ($row->overtime_amount ?? 0),
                    ];
                })
                ->filter()
                ->groupBy('staff_id')
                ->map(function ($items) {
                    return [
                        'deduction' => (float) $items->sum('deduction'),
                        'overtime' => (float) $items->sum('overtime'),
                    ];
                });
        }

        $advanceByStaff = SalaryPayment::query()
            ->select('staff_id', DB::raw('SUM(amount) as total_advance'))
            ->where('month', $monthInput)
            ->where('is_advance', true)
            ->groupBy('staff_id')
            ->pluck('total_advance', 'staff_id');

        $rows = $staffList->map(function ($staff, $index) use ($attendanceRows, $approvedLeaves, $monthDate, $totalDaysInMonth, $rosterRows, $attendanceFinancials, $advanceByStaff) {
            $staffAttendances = collect($attendanceRows->get($staff->id, []));
            $attendanceMap = $staffAttendances
                ->keyBy(fn($row) => Carbon::parse($row->attendance_date)->toDateString());

            $rosterDates = collect($rosterRows->get($staff->id, []))
                ->map(function ($row) {
                    return Carbon::parse($row->date)->toDateString();
                })
                ->unique()
                ->values();

            $approvedLeaveDates = collect($approvedLeaves->get($staff->id, []))
                ->flatMap(function ($leave) use ($monthDate) {
                    $leaveStart = Carbon::parse($leave->from)->startOfDay();
                    $leaveEnd = Carbon::parse($leave->to)->startOfDay();

                    $rangeStart = $leaveStart->greaterThan($monthDate->copy()->startOfMonth())
                        ? $leaveStart
                        : $monthDate->copy()->startOfMonth();
                    $rangeEnd = $leaveEnd->lessThan($monthDate->copy()->endOfMonth())
                        ? $leaveEnd
                        : $monthDate->copy()->endOfMonth();

                    if ($rangeStart->greaterThan($rangeEnd)) {
                        return [];
                    }

                    return collect(CarbonPeriod::create($rangeStart, $rangeEnd))
                        ->map(function ($date) {
                            return Carbon::parse((string) $date)->toDateString();
                        })
                        ->all();
                })
                ->unique();

            $evaluationDates = $rosterDates->isNotEmpty()
                ? $rosterDates
                : collect(range(1, $totalDaysInMonth))->map(function ($day) use ($monthDate) {
                    return $monthDate->copy()->day($day)->toDateString();
                });

            $totalWorkableDays = $evaluationDates->count();

            $present = 0;
            $late = 0;
            $holiday = 0;
            $absent = 0;
            $approvedLeaveCount = 0;

            foreach ($evaluationDates as $dateKey) {
                $record = $attendanceMap->get($dateKey);

                if ($record) {
                    $status = (string) $record->attendance_status;
                    if ($status === 'Present') {
                        $present++;
                        continue;
                    }
                    if ($status === 'Late') {
                        $late++;
                        continue;
                    }
                    if ($status === 'Holiday') {
                        $holiday++;
                        continue;
                    }
                    if ($status === 'Absent') {
                        if ($approvedLeaveDates->contains($dateKey)) {
                            $approvedLeaveCount++;
                        } else {
                            $absent++;
                        }
                        continue;
                    }
                }

                if ($approvedLeaveDates->contains($dateKey)) {
                    $approvedLeaveCount++;
                } else {
                    $absent++;
                }
            }

            $paidDays = $present + $late + $holiday + $approvedLeaveCount;
            $unpaidDays = max($totalWorkableDays - $paidDays, 0);

            $basicSalary = (float) ($staff->details->basic_salary ?? 0);
            $dailyRate = $totalWorkableDays > 0 ? ($basicSalary / $totalWorkableDays) : 0;
            $basePayable = round($dailyRate * $paidDays, 2);

            $financials = $attendanceFinancials->get($staff->id, ['deduction' => 0, 'overtime' => 0]);
            $biometricDeduction = round((float) ($financials['deduction'] ?? 0), 2);
            $overtimeBonus = round((float) ($financials['overtime'] ?? 0), 2);
            $grossPayable = round(max($basePayable - $biometricDeduction + $overtimeBonus, 0), 2);

            $advancePaid = round((float) ($advanceByStaff[$staff->id] ?? 0), 2);
            $payableSalary = round(max($grossPayable - $advancePaid, 0), 2);
            $deduction = round(max(($basicSalary - $basePayable) + $biometricDeduction, 0), 2);

            return [
                'sl' => $index + 1,
                'staff_id' => $staff->details->staff_id ?? (string) $staff->id,
                'staff_admin_id' => (int) $staff->id,
                'name' => trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')),
                'role' => $staff->role->name ?? 'N/A',
                'department' => $staff->details->department->department_name ?? 'N/A',
                'designation' => $staff->details->designation->designation_name ?? 'N/A',
                'basic_salary' => round($basicSalary, 2),
                'total_days' => $totalDaysInMonth,
                'workable_days' => $totalWorkableDays,
                'present' => $present,
                'late' => $late,
                'holiday' => $holiday,
                'approved_leaves' => $approvedLeaveCount,
                'absent' => $absent,
                'paid_days' => $paidDays,
                'unpaid_days' => $unpaidDays,
                'biometric_deduction' => $biometricDeduction,
                'overtime_bonus' => $overtimeBonus,
                'gross_payable' => $grossPayable,
                'advance_paid' => $advancePaid,
                'deduction' => max($deduction, 0),
                'payable_salary' => max($payableSalary, 0),
            ];
        })->values();

        $totals = [
            'staff_count' => $rows->count(),
            'basic_salary' => round($rows->sum('basic_salary'), 2),
            'deduction' => round($rows->sum('deduction'), 2),
            'payable_salary' => round($rows->sum('payable_salary'), 2),
        ];

        return Inertia::render('Backend/StaffAttendance/SalarySheet', [
            'pageTitle' => 'Salary Sheet',
            'websetting' => [
                'company_name' => $websetting?->company_name ?? config('app.name', 'Hospital'),
                'address' => $websetting?->address ?? $websetting?->report_title ?? 'N/A',
                'attendance_device_options' => $websetting?->attendance_device_options,
            ],
            'filters' => [
                'month' => $monthInput,
            ],
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    public function salaryPay(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:admins,id',
            'month' => 'required|string|size:7',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string',
            'note' => 'nullable|string',
            'is_advance' => 'sometimes|boolean',
        ]);

        $payment = SalaryPayment::create([
            'staff_id' => $validated['staff_id'],
            'month' => $validated['month'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'] ?? null,
            'note' => $validated['note'] ?? null,
            'is_advance' => $request->boolean('is_advance'),
            'paid_at' => now(),
            'admin_id' => Auth::guard('admin')->id() ?? null,
            'status' => 'Paid',
        ]);

        return back()->with('successMessage', 'Salary payment recorded.');
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
        $websetting = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();
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
                'websetting' => fn() => [
                    'company_name' => $websetting?->company_name ?? config('app.name', 'Hospital'),
                    'address' => $websetting?->address ?? $websetting?->report_title ?? 'N/A',
                ],
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
