<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Salary;
use App\Models\Attendance;
use App\Models\Payslip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function runForEmployee(Employee $employee, string $periodStart, string $periodEnd, $createdBy = null): Payslip
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        $salary = Salary::where('employee_id', $employee->id)
            ->where(function ($q) use ($start, $end) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start->toDateString());
            })
            ->orderBy('effective_from', 'desc')
            ->first();

        $basic = $salary ? $salary->basic : 0;
        $allowances = $salary ? $salary->allowances : 0;
        $salaryDeductions = $salary ? $salary->deductions : 0;

        $daysPresent = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'present')
            ->count();

        $gross = bcadd((string)$basic, (string)$allowances, 2);
        $deductions = $salaryDeductions;
        $net = bcsub((string)$gross, (string)$deductions, 2);

        $payslip = Payslip::create([
            'employee_id' => $employee->id,
            'salary_id' => $salary ? $salary->id : null,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'days_present' => $daysPresent,
            'gross' => $gross,
            'deductions' => $deductions,
            'net' => $net,
            'created_by' => $createdBy,
        ]);

        return $payslip;
    }

    public function runForAll(string $periodStart, string $periodEnd, $createdBy = null)
    {
        $employees = Employee::all();
        $results = [];
        DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                $results[] = $this->runForEmployee($employee, $periodStart, $periodEnd, $createdBy);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }
}
