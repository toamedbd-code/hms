<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Salary;
use App\Models\Attendance;
use App\Models\Payslip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

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

        // total days in the period (inclusive)
        $totalDays = $start->diffInDays($end) + 1;

        $gross = bcadd((string)$basic, (string)$allowances, 2);

        $deductions = $salaryDeductions;

        // Absence-based prorated deduction (optional, configurable)
        if (Config::get('payroll.absence_deduction', false) && $totalDays > 0) {
            $daysAbsent = max(0, $totalDays - $daysPresent);
            $perDay = $totalDays > 0 ? (float) $basic / $totalDays : 0;
            $absenceDeduction = round($perDay * $daysAbsent, 2);
            $deductions = bcadd((string)$deductions, (string)$absenceDeduction, 2);
        }

        // Tax (configurable rate applied on gross)
        $taxRate = (float) Config::get('payroll.tax_rate', 0.0);
        if ($taxRate > 0) {
            $tax = round($gross * $taxRate, 2);
            $deductions = bcadd((string)$deductions, (string)$tax, 2);
        }

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
