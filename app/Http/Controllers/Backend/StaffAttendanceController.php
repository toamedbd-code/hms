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
use App\Models\SalarySheetLock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
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
        $this->middleware('permission:salary-sheet-pay')->only(['salaryPay', 'lockSalarySheet']);
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
        $salaryData = $this->buildSalarySheetData($request);

        return Inertia::render('Backend/StaffAttendance/SalarySheet', [
            'pageTitle' => 'Salary Sheet',
            'websetting' => [
                'company_name' => $salaryData['websetting']?->company_name ?? config('app.name', 'Hospital'),
                'address' => $salaryData['websetting']?->address ?? $salaryData['websetting']?->report_title ?? 'N/A',
                'attendance_device_options' => $salaryData['websetting']?->attendance_device_options,
            ],
            'printUrl' => route('backend.staffattendance.salary-sheet.print'),
            'filters' => [
                'month' => $salaryData['month_input'],
                'late_fee_per_late' => $salaryData['late_fee_per_late'],
                'overtime_multiplier' => $salaryData['overtime_multiplier'],
                'late_grace_days' => $salaryData['late_grace_days'],
                'late_deduction_rate' => $salaryData['late_deduction_rate'],
                'late_highlight_limit' => $salaryData['late_highlight_limit'],
                'unpaid_highlight_limit' => $salaryData['unpaid_highlight_limit'],
                'waive_short_late' => $salaryData['waive_short_late'],
                'short_late_limit_minutes' => $salaryData['short_late_limit_minutes'],
            ],
            'rows' => $salaryData['rows'],
            'totals' => $salaryData['totals'],
            'lockState' => $salaryData['lock_state'],
            'holidayAuditUrl' => route('backend.staffattendance.salary-sheet.holiday-audit'),
            'breakdownPrintUrl' => route('backend.staffattendance.salary-sheet.breakdown-print'),
            'breakdownPdfUrl' => route('backend.staffattendance.salary-sheet.breakdown-pdf'),
            'salaryPdfUrl' => route('backend.staffattendance.salary-sheet.pdf'),
            'lockUrl' => route('backend.staffattendance.salary-sheet.lock'),
            'saveSettingsUrl' => route('backend.staffattendance.salary-sheet.settings.save'),
        ]);
    }

    public function saveSalarySheetSettings(Request $request)
    {
        $validated = $request->validate([
            'late_fee_per_late' => 'nullable|numeric|min:0',
            'overtime_multiplier' => 'nullable|numeric|min:0',
            'late_grace_days' => 'nullable|integer|min:0',
            'late_deduction_rate' => 'nullable|numeric|min:0',
            'late_highlight_limit' => 'nullable|integer|min:0',
            'unpaid_highlight_limit' => 'nullable|integer|min:0',
            'waive_short_late' => 'nullable|boolean',
            'short_late_limit_minutes' => 'nullable|integer|min:0',
        ]);

        $setting = WebSetting::query()->where('status', 'Active')->orderByDesc('id')->first();
        if (!$setting) {
            $setting = WebSetting::query()->orderByDesc('id')->first();
        }

        if (!$setting) {
            $setting = WebSetting::create([
                'status' => 'Active',
                'company_name' => config('app.name', 'Hospital'),
            ]);
        }

        $options = is_array($setting->attendance_device_options)
            ? $setting->attendance_device_options
            : [];

        $payroll = data_get($options, 'payroll', []);
        if (!is_array($payroll)) {
            $payroll = [];
        }

        $salarySheetDefaults = [
            'late_fee_per_late' => max((float) ($validated['late_fee_per_late'] ?? 0), 0),
            'overtime_multiplier' => max((float) ($validated['overtime_multiplier'] ?? 1), 0),
            'late_grace_days' => max((int) ($validated['late_grace_days'] ?? 3), 0),
            'late_deduction_rate' => max((float) ($validated['late_deduction_rate'] ?? 0.25), 0),
            'late_highlight_limit' => max((int) ($validated['late_highlight_limit'] ?? 3), 0),
            'unpaid_highlight_limit' => max((int) ($validated['unpaid_highlight_limit'] ?? 2), 0),
            'waive_short_late' => (bool) ($validated['waive_short_late'] ?? false),
            'short_late_limit_minutes' => max((int) ($validated['short_late_limit_minutes'] ?? 15), 0),
        ];

        $payroll['salary_sheet'] = $salarySheetDefaults;
        $options['payroll'] = $payroll;

        $setting->attendance_device_options = $options;
        $setting->save();

        return response()->json([
            'message' => 'Salary sheet settings saved successfully.',
            'settings' => $salarySheetDefaults,
        ]);
    }

    public function salarySheetPrint(Request $request)
    {
        $salaryData = $this->buildSalarySheetData($request);

        return view('backend.staffattendance.salary_sheet_print', [
            'pageTitle' => 'Salary Sheet Print',
            'websetting' => $salaryData['websetting'],
            'monthInput' => $salaryData['month_input'],
            'monthLabel' => $salaryData['month_label'],
            'lateFeePerLate' => $salaryData['late_fee_per_late'],
            'overtimeMultiplier' => $salaryData['overtime_multiplier'],
            'lateGraceDays' => $salaryData['late_grace_days'],
            'lateDeductionRate' => $salaryData['late_deduction_rate'],
            'lateHighlightLimit' => $salaryData['late_highlight_limit'],
            'unpaidHighlightLimit' => $salaryData['unpaid_highlight_limit'],
            'waiveShortLate' => $salaryData['waive_short_late'],
            'shortLateLimitMinutes' => $salaryData['short_late_limit_minutes'],
            'rows' => $salaryData['rows'],
            'totals' => $salaryData['totals'],
            'lockState' => $salaryData['lock_state'],
            'generatedAt' => now(),
        ]);
    }

    public function downloadSalarySheetPdf(Request $request)
    {
        $salaryData = $this->buildSalarySheetData($request);

        $pdf = Pdf::loadView('backend.staffattendance.salary_sheet_print', [
            'pageTitle' => 'Salary Sheet PDF',
            'websetting' => $salaryData['websetting'],
            'monthInput' => $salaryData['month_input'],
            'monthLabel' => $salaryData['month_label'],
            'lateFeePerLate' => $salaryData['late_fee_per_late'],
            'overtimeMultiplier' => $salaryData['overtime_multiplier'],
            'lateGraceDays' => $salaryData['late_grace_days'],
            'lateDeductionRate' => $salaryData['late_deduction_rate'],
            'lateHighlightLimit' => $salaryData['late_highlight_limit'],
            'unpaidHighlightLimit' => $salaryData['unpaid_highlight_limit'],
            'waiveShortLate' => $salaryData['waive_short_late'],
            'shortLateLimitMinutes' => $salaryData['short_late_limit_minutes'],
            'rows' => $salaryData['rows'],
            'totals' => $salaryData['totals'],
            'lockState' => $salaryData['lock_state'],
            'generatedAt' => now(),
            'isPdf' => true,
        ])->setPaper('a4', 'landscape');

        $fileName = 'salary-sheet-' . $salaryData['month_input'] . '.pdf';

        return $pdf->download($fileName);
    }

    public function lockSalarySheet(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|string|size:7',
            'note' => 'nullable|string|max:255',
        ]);

        try {
            Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Invalid month format.'], 422);
        }

        $lock = DB::transaction(function () use ($validated) {
            $lock = SalarySheetLock::query()
                ->where('month', $validated['month'])
                ->lockForUpdate()
                ->first();

            if ($lock && $lock->is_locked) {
                return $lock;
            }

            if (!$lock) {
                $lock = new SalarySheetLock();
                $lock->month = $validated['month'];
            }

            $lock->is_locked = true;
            $lock->locked_at = now();
            $lock->locked_by = Auth::guard('admin')->id() ?? null;
            $lock->lock_note = $validated['note'] ?? null;
            $lock->save();

            return $lock;
        });

        return response()->json([
            'message' => 'Salary sheet locked successfully.',
            'lock_state' => [
                'is_locked' => (bool) $lock->is_locked,
                'month' => $lock->month,
                'locked_at' => optional($lock->locked_at)->toDateTimeString(),
                'locked_by' => $lock->locked_by,
                'lock_note' => $lock->lock_note,
            ],
        ]);
    }

    public function downloadHolidayAudit(Request $request)
    {
        $monthInput = (string) $request->input('month', now()->format('Y-m'));
        $country = strtolower((string) $request->input('country', config('attendance.google_holidays.country', 'bd')));
        $withWeekly = filter_var($request->input('with_weekly', true), FILTER_VALIDATE_BOOL);

        try {
            $monthDate = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
            $month = $monthDate->format('Y-m');
        } catch (\Throwable $th) {
            $month = now()->format('Y-m');
        }

        Artisan::call('attendance:holiday-audit', [
            '--month' => $month,
            '--country' => $country,
            '--with-weekly' => $withWeekly,
        ]);

        $relativePath = 'attendance/reports/holiday-audit-' . $month . '.csv';
        if (!Storage::disk('local')->exists($relativePath)) {
            return response()->json([
                'message' => 'Holiday audit file could not be generated.',
            ], 422);
        }

        return response()->download(
            storage_path('app/' . $relativePath),
            'holiday-audit-' . $month . '.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function downloadBreakdownPdf(Request $request)
    {
        $resolved = $this->resolveSalaryBreakdownRow($request);
        if (isset($resolved['error'])) {
            return response()->json(['message' => $resolved['error']], $resolved['status']);
        }

        $row = $resolved['row'];
        $salaryData = $resolved['salary_data'];
        $monthInput = $resolved['month_input'];

        $pdf = Pdf::loadView('backend.staffattendance.salary_breakdown_print', [
            'pageTitle' => 'Attendance Breakdown',
            'monthInput' => $monthInput,
            'monthLabel' => $salaryData['month_label'] ?? $monthInput,
            'websetting' => $salaryData['websetting'],
            'row' => $row,
            'totals' => [
                'duration_minutes' => (int) collect($row['attendance_breakdown'] ?? [])->sum('duration_minutes'),
                'late_minutes' => (int) collect($row['attendance_breakdown'] ?? [])->sum('late_minutes'),
                'overtime_minutes' => (int) collect($row['attendance_breakdown'] ?? [])->sum('overtime_minutes'),
                'deduction_amount' => round((float) collect($row['attendance_breakdown'] ?? [])->sum('deduction_amount'), 2),
                'overtime_amount' => round((float) collect($row['attendance_breakdown'] ?? [])->sum('overtime_amount'), 2),
            ],
            'generatedAt' => now(),
            'autoPrint' => false,
        ])->setPaper('a4', 'portrait');

        $safeStaff = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($row['staff_id'] ?? ($row['staff_admin_id'] ?? 'staff')));
        $fileName = 'attendance-breakdown-' . $safeStaff . '-' . $monthInput . '.pdf';

        return $pdf->download($fileName);
    }

    public function salaryBreakdownPrint(Request $request)
    {
        $resolved = $this->resolveSalaryBreakdownRow($request);
        if (isset($resolved['error'])) {
            return response()->json(['message' => $resolved['error']], $resolved['status']);
        }

        $row = $resolved['row'];
        $salaryData = $resolved['salary_data'];
        $monthInput = $resolved['month_input'];

        return view('backend.staffattendance.salary_breakdown_print', [
            'pageTitle' => 'Attendance Breakdown',
            'monthInput' => $monthInput,
            'monthLabel' => $salaryData['month_label'] ?? $monthInput,
            'websetting' => $salaryData['websetting'],
            'row' => $row,
            'totals' => [
                'duration_minutes' => (int) collect($row['attendance_breakdown'] ?? [])->sum('duration_minutes'),
                'late_minutes' => (int) collect($row['attendance_breakdown'] ?? [])->sum('late_minutes'),
                'overtime_minutes' => (int) collect($row['attendance_breakdown'] ?? [])->sum('overtime_minutes'),
                'deduction_amount' => round((float) collect($row['attendance_breakdown'] ?? [])->sum('deduction_amount'), 2),
                'overtime_amount' => round((float) collect($row['attendance_breakdown'] ?? [])->sum('overtime_amount'), 2),
            ],
            'generatedAt' => now(),
            'autoPrint' => true,
        ]);
    }

    private function resolveSalaryBreakdownRow(Request $request): array
    {
        $monthInput = (string) $request->input('month', now()->format('Y-m'));
        $staffAdminId = (int) $request->input('staff_id', 0);

        if ($staffAdminId <= 0) {
            return [
                'error' => 'Invalid staff id for breakdown.',
                'status' => 422,
            ];
        }

        $salaryData = $this->buildSalarySheetData($request);

        $row = collect($salaryData['rows'])->first(function ($item) use ($staffAdminId) {
            return (int) ($item['staff_admin_id'] ?? 0) === $staffAdminId;
        });

        if (!$row) {
            return [
                'error' => 'No salary breakdown found for selected staff and month.',
                'status' => 404,
            ];
        }

        return [
            'row' => $row,
            'salary_data' => $salaryData,
            'month_input' => $monthInput,
        ];
    }

    private function buildSalarySheetData(Request $request): array
    {
        $websetting = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();
        $monthInput = (string) $request->input('month', now()->format('Y-m'));

        $attendanceOptions = is_array($websetting?->attendance_device_options)
            ? $websetting->attendance_device_options
            : [];
        $payrollDefaults = data_get($attendanceOptions, 'payroll.salary_sheet', []);
        if (!is_array($payrollDefaults)) {
            $payrollDefaults = [];
        }

        try {
            $monthDate = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable $th) {
            $monthDate = now()->startOfMonth();
            $monthInput = $monthDate->format('Y-m');
        }

        $lateFeePerLate = max((float) $request->input('late_fee_per_late', (float) ($payrollDefaults['late_fee_per_late'] ?? 0)), 0);
        $overtimeMultiplier = max((float) $request->input('overtime_multiplier', (float) ($payrollDefaults['overtime_multiplier'] ?? 1)), 0);
        $lateGraceDays = max((int) $request->input('late_grace_days', (int) ($payrollDefaults['late_grace_days'] ?? 3)), 0);
        $lateDeductionRate = max((float) $request->input('late_deduction_rate', (float) ($payrollDefaults['late_deduction_rate'] ?? 0.25)), 0);
        $lateHighlightLimit = max((int) $request->input('late_highlight_limit', (int) ($payrollDefaults['late_highlight_limit'] ?? 3)), 0);
        $unpaidHighlightLimit = max((int) $request->input('unpaid_highlight_limit', (int) ($payrollDefaults['unpaid_highlight_limit'] ?? 2)), 0);
        $waiveShortLate = $request->has('waive_short_late')
            ? filter_var($request->input('waive_short_late'), FILTER_VALIDATE_BOOL)
            : (bool) ($payrollDefaults['waive_short_late'] ?? false);
        $shortLateLimitMinutes = max(
            (int) $request->input('short_late_limit_minutes', (int) ($payrollDefaults['short_late_limit_minutes'] ?? 15)),
            0
        );

        $startDate = $monthDate->copy()->startOfMonth()->toDateString();
        $endDate = $monthDate->copy()->endOfMonth()->toDateString();
        $totalDaysInMonth = $monthDate->daysInMonth;

        $staffList = Admin::query()
            ->with(['role:id,name', 'details.department:id,name', 'details.designation:id,name'])
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $staffIds = $staffList->pluck('id')->map(fn($id) => (int) $id)->all();

        $rosterRows = DutyRoster::query()
            ->select('staff_id', 'date', 'start_time', 'end_time')
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

        $resolveStaffIdFromEmployeeCode = function (string $employeeCode) use ($staffCodeToAdminId, $staffIds): ?int {
            if ($employeeCode !== '' && ctype_digit($employeeCode)) {
                $numericId = (int) $employeeCode;
                if (in_array($numericId, $staffIds, true)) {
                    return $numericId;
                }
            }

            if ($employeeCode !== '' && $staffCodeToAdminId->has($employeeCode)) {
                return (int) $staffCodeToAdminId->get($employeeCode);
            }

            return null;
        };

        $attendanceFinancials = collect();
        $attendanceBreakdown = collect();
        if (!empty($staffIds)) {
            $attendanceRowsRaw = Attendance::query()
                ->select('employee_code', 'recorded_at', 'recorded_out', 'late_minutes', 'overtime_minutes', 'duration_minutes', 'deduction_amount', 'overtime_amount')
                ->where('type', 'in')
                ->whereDate('recorded_at', '>=', $startDate)
                ->whereDate('recorded_at', '<=', $endDate)
                ->whereNotNull('recorded_out')
                ->get();

            // Biometric feeds may store cumulative snapshots multiple times per day.
            // Keep only the latest row per staff/date to prevent inflated minute sums.
            $attendanceRowsNormalized = $attendanceRowsRaw
                ->map(function ($row) use ($resolveStaffIdFromEmployeeCode) {
                    $employeeCode = (string) ($row->employee_code ?? '');
                    $adminId = $resolveStaffIdFromEmployeeCode($employeeCode);

                    if ($adminId === null || empty($row->recorded_at)) {
                        return null;
                    }

                    $recordedAt = Carbon::parse($row->recorded_at);
                    $recordedOut = !empty($row->recorded_out)
                        ? Carbon::parse($row->recorded_out)
                        : null;

                    return [
                        'staff_id' => $adminId,
                        'date' => $recordedAt->toDateString(),
                        'in_time' => $recordedAt->format('h:i A'),
                        'out_time' => $recordedOut?->format('h:i A'),
                        'recorded_at_ts' => $recordedAt->timestamp,
                        'recorded_out_ts' => $recordedOut?->timestamp ?? $recordedAt->timestamp,
                        'duration_minutes' => max((int) ($row->duration_minutes ?? 0), 0),
                        'late_minutes' => max((int) ($row->late_minutes ?? 0), 0),
                        'overtime_minutes' => max((int) ($row->overtime_minutes ?? 0), 0),
                        'deduction_amount' => round(max((float) ($row->deduction_amount ?? 0), 0), 2),
                        'overtime_amount' => round(max((float) ($row->overtime_amount ?? 0), 0), 2),
                    ];
                })
                ->filter();

            $attendanceRowsCollapsed = $attendanceRowsNormalized
                ->groupBy(function ($row) {
                    return $row['staff_id'] . '|' . $row['date'];
                })
                ->map(function ($items) {
                    return $items
                        ->sortByDesc('recorded_out_ts')
                        ->first();
                })
                ->values();

            $attendanceFinancials = $attendanceRowsCollapsed
                ->groupBy('staff_id')
                ->map(function ($items) {
                    return [
                        'late_minutes' => (int) $items->sum('late_minutes'),
                        'overtime_minutes' => (int) $items->sum('overtime_minutes'),
                        'deduction' => (float) $items->sum('deduction_amount'),
                        'overtime' => (float) $items->sum('overtime_amount'),
                    ];
                });

            $attendanceBreakdown = $attendanceRowsCollapsed
                ->groupBy('staff_id')
                ->map(function ($items) {
                    return $items
                        ->sortByDesc('date')
                        ->values()
                        ->map(function ($row) {
                            unset($row['recorded_at_ts'], $row['recorded_out_ts']);
                            return $row;
                        })
                        ->values()
                        ->all();
                });
        }

        $advanceByStaff = SalaryPayment::query()
            ->select('staff_id', DB::raw('SUM(amount) as total_advance'))
            ->where('month', $monthInput)
            ->where('is_advance', true)
            ->groupBy('staff_id')
            ->pluck('total_advance', 'staff_id');

        $lockRecord = SalarySheetLock::query()
            ->where('month', $monthInput)
            ->first();

        $lockState = [
            'is_locked' => (bool) ($lockRecord->is_locked ?? false),
            'month' => $monthInput,
            'locked_at' => optional($lockRecord?->locked_at)->toDateTimeString(),
            'locked_by' => $lockRecord->locked_by ?? null,
            'lock_note' => $lockRecord->lock_note ?? null,
        ];

        $rows = $staffList->map(function ($staff, $index) use ($attendanceRows, $approvedLeaves, $monthDate, $totalDaysInMonth, $rosterRows, $attendanceFinancials, $attendanceBreakdown, $advanceByStaff, $lateFeePerLate, $overtimeMultiplier, $lateGraceDays, $lateDeductionRate, $waiveShortLate, $shortLateLimitMinutes) {
            $staffAttendances = collect($attendanceRows->get($staff->id, []));
            $attendanceMap = $staffAttendances
                ->keyBy(fn($row) => Carbon::parse($row->attendance_date)->toDateString());

            $rosterForStaff = collect($rosterRows->get($staff->id, []));
            $rosterByDate = $rosterForStaff->keyBy(function ($row) {
                return Carbon::parse($row->date)->toDateString();
            });

            $rosterDates = $rosterForStaff
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

            $attendanceDates = $attendanceMap->keys()->values();

            $evaluationDates = $rosterDates->isNotEmpty()
                ? $rosterDates
                    ->merge($attendanceDates)
                    ->merge($approvedLeaveDates)
                    ->unique()
                    ->sort()
                    ->values()
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
            // Monthly base should be pro-rated by month days to avoid full salary on sparse roster rows.
            $dailyRate = $totalDaysInMonth > 0 ? ($basicSalary / $totalDaysInMonth) : 0;
            $hourlyRate = $dailyRate / 8;
            $basePayable = round($dailyRate * $paidDays, 2);

            $financials = $attendanceFinancials->get($staff->id, [
                'late_minutes' => 0,
                'overtime_minutes' => 0,
                'deduction' => 0,
                'overtime' => 0,
            ]);

            $staffBreakdown = collect($attendanceBreakdown->get($staff->id, []));
            $waivedBreakdown = $staffBreakdown->filter(function ($item) use ($waiveShortLate, $shortLateLimitMinutes) {
                if (!$waiveShortLate) {
                    return false;
                }

                $lateMinutes = (int) ($item['late_minutes'] ?? 0);
                return $lateMinutes > 0 && $lateMinutes <= $shortLateLimitMinutes;
            });

            $waivedShortLateMinutes = (int) $waivedBreakdown->sum(function ($item) {
                return (int) ($item['late_minutes'] ?? 0);
            });
            $waivedShortLateDays = $waivedBreakdown
                ->pluck('date')
                ->filter()
                ->unique()
                ->count();

            $lateMinutesTotal = max((int) ($financials['late_minutes'] ?? 0), 0);
            $lateMinutesForDeduction = max($lateMinutesTotal - $waivedShortLateMinutes, 0);
            $overtimeMinutesTotal = max((int) ($financials['overtime_minutes'] ?? 0), 0);
            $lateDaysForDeduction = max($late - $waivedShortLateDays, 0);

            $lateDeductionByHour = round(($lateMinutesForDeduction / 60) * $hourlyRate, 2);
            $rosterOt = $this->calculateRosterOvertimeBonus(
                $staffBreakdown,
                $rosterByDate,
                $hourlyRate,
                $overtimeMultiplier
            );

            $effectiveOvertimeMinutes = max($overtimeMinutesTotal, (int) ($rosterOt['ot_minutes'] ?? 0));
            $overtimeBonusByHour = round(($effectiveOvertimeMinutes / 60) * $hourlyRate * $overtimeMultiplier, 2);

            // Avoid double counting: prefer stored biometric amounts, fallback to calculated hourly amounts.
            $attendanceDeduction = round(max((float) ($financials['deduction'] ?? 0), 0), 2);
            $attendanceOvertime = round(max((float) ($financials['overtime'] ?? 0), 0), 2);

            $biometricDeduction = $attendanceDeduction > 0
                ? $attendanceDeduction
                : $lateDeductionByHour;

            $overtimeBonus = $attendanceOvertime > 0
                ? $attendanceOvertime
                : max($overtimeBonusByHour, round((float) ($rosterOt['ot_bonus'] ?? 0), 2));

            $extraLateDays = max($lateDaysForDeduction - $lateGraceDays, 0);
            $latePolicyDeduction = round($extraLateDays * $dailyRate * $lateDeductionRate, 2);

            $lateFee = round($lateDaysForDeduction * $lateFeePerLate, 2);
            $grossPayable = round(max($basePayable - $biometricDeduction - $lateFee - $latePolicyDeduction + $overtimeBonus, 0), 2);

            $advancePaid = round((float) ($advanceByStaff[$staff->id] ?? 0), 2);
            $payableSalary = round(max($grossPayable - $advancePaid, 0), 2);
            $deduction = round(max(($basicSalary - $basePayable) + $biometricDeduction + $lateFee + $latePolicyDeduction, 0), 2);

            return [
                'sl' => $index + 1,
                'staff_id' => $staff->details->staff_id ?? (string) $staff->id,
                'staff_admin_id' => (int) $staff->id,
                'name' => trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')),
                'role' => $staff->role->name ?? 'N/A',
                'department' => $staff->details->department->name ?? 'N/A',
                'designation' => $staff->details->designation->name ?? 'N/A',
                'attendance_breakdown' => $attendanceBreakdown->get($staff->id, []),
                'basic_salary' => round($basicSalary, 2),
                'total_days' => $totalDaysInMonth,
                'workable_days' => $totalWorkableDays,
                'present' => $present,
                'late' => $late,
                'late_minutes' => $lateMinutesTotal,
                'late_minutes_for_deduction' => $lateMinutesForDeduction,
                'holiday' => $holiday,
                'approved_leaves' => $approvedLeaveCount,
                'absent' => $absent,
                'overtime_minutes' => $overtimeMinutesTotal,
                'paid_days' => $paidDays,
                'unpaid_days' => $unpaidDays,
                'waived_short_late_minutes' => $waivedShortLateMinutes,
                'waived_short_late_days' => $waivedShortLateDays,
                'late_for_deduction' => $lateDaysForDeduction,
                'late_policy_extra_days' => $extraLateDays,
                'late_policy_deduction' => $latePolicyDeduction,
                'hourly_rate' => round($hourlyRate, 2),
                'late_deduction_hourly' => $lateDeductionByHour,
                'overtime_bonus_hourly' => $overtimeBonusByHour,
                'late_fee' => $lateFee,
                'biometric_deduction' => $biometricDeduction,
                'overtime_base' => $attendanceOvertime,
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
            'late_minutes' => (int) $rows->sum('late_minutes'),
            'late_minutes_for_deduction' => (int) $rows->sum('late_minutes_for_deduction'),
            'overtime_minutes' => (int) $rows->sum('overtime_minutes'),
            'waived_short_late_minutes' => (int) $rows->sum('waived_short_late_minutes'),
            'waived_short_late_days' => (int) $rows->sum('waived_short_late_days'),
            'late_deduction_hourly' => round($rows->sum('late_deduction_hourly'), 2),
            'overtime_bonus_hourly' => round($rows->sum('overtime_bonus_hourly'), 2),
            'biometric_deduction' => round($rows->sum('biometric_deduction'), 2),
            'late_fee' => round($rows->sum('late_fee'), 2),
            'late_policy_deduction' => round($rows->sum('late_policy_deduction'), 2),
            'overtime_bonus' => round($rows->sum('overtime_bonus'), 2),
            'deduction' => round($rows->sum('deduction'), 2),
            'payable_salary' => round($rows->sum('payable_salary'), 2),
        ];

        return [
            'websetting' => $websetting,
            'month_input' => $monthInput,
            'month_label' => $monthDate->format('F Y'),
            'late_fee_per_late' => $lateFeePerLate,
            'overtime_multiplier' => $overtimeMultiplier,
            'late_grace_days' => $lateGraceDays,
            'late_deduction_rate' => $lateDeductionRate,
            'late_highlight_limit' => $lateHighlightLimit,
            'unpaid_highlight_limit' => $unpaidHighlightLimit,
            'waive_short_late' => $waiveShortLate,
            'short_late_limit_minutes' => $shortLateLimitMinutes,
            'rows' => $rows,
            'totals' => $totals,
            'lock_state' => $lockState,
        ];
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

        $lockRecord = SalarySheetLock::query()
            ->where('month', $validated['month'])
            ->first();

        if ($lockRecord && $lockRecord->is_locked) {
            return response()->json([
                'message' => 'Salary sheet is locked for this month. Payment is disabled.',
            ], 423);
        }

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

    private function calculateRosterOvertimeBonus($attendanceBreakdown, $rosterByDate, float $hourlyRate, float $overtimeMultiplier): array
    {
        $otMinutes = 0;

        foreach ($attendanceBreakdown as $item) {
            $date = (string) ($item['date'] ?? '');
            $outTime = (string) ($item['out_time'] ?? '');

            if ($date === '' || $outTime === '' || $outTime === '-') {
                continue;
            }

            $roster = $rosterByDate->get($date);
            if (!$roster || empty($roster->end_time)) {
                continue;
            }

            try {
                $outAt = Carbon::createFromFormat('Y-m-d h:i A', $date . ' ' . $outTime);
            } catch (\Throwable $th) {
                continue;
            }

            try {
                $shiftEnd = Carbon::parse($date . ' ' . $roster->end_time);
            } catch (\Throwable $th) {
                continue;
            }

            if ($shiftEnd->lessThanOrEqualTo($outAt->copy()->startOfDay())) {
                $shiftEnd = $shiftEnd->addDay();
            }

            if ($outAt->greaterThan($shiftEnd)) {
                $otMinutes += $shiftEnd->diffInMinutes($outAt);
            }
        }

        $otHours = round($otMinutes / 60, 2);
        $otBonus = round($otHours * max($hourlyRate, 0) * max($overtimeMultiplier, 0), 2);

        return [
            'ot_minutes' => max($otMinutes, 0),
            'ot_hours' => max($otHours, 0),
            'ot_bonus' => max($otBonus, 0),
        ];
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
