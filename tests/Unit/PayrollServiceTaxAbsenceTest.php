<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Employee;
use App\Models\Salary;
use App\Models\Attendance;
use App\Services\PayrollService;
use Illuminate\Support\Facades\Config;

class PayrollServiceTaxAbsenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_absence_and_tax_are_applied_when_enabled()
    {
        Config::set('payroll.absence_deduction', true);
        Config::set('payroll.tax_rate', 0.10); // 10%

        $employee = Employee::create([
            'employee_id' => 'EMP002',
            'first_name' => 'TaxTest',
        ]);

        // Monthly basic 3000
        Salary::create([
            'employee_id' => $employee->id,
            'basic' => 3000,
            'allowances' => 0,
            'deductions' => 0,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();
        $totalDays = $periodStart->diffInDays($periodEnd) + 1;

        // Mark only first 20 days as present to create 10 absent (if month has 30 days)
        $presentDays = min(20, $totalDays);
        for ($i = 0; $i < $presentDays; $i++) {
            Attendance::create([
                'employee_id' => $employee->id,
                'date' => $periodStart->copy()->addDays($i)->toDateString(),
                'status' => 'present',
            ]);
        }

        $service = new PayrollService();
        $payslip = $service->runForEmployee($employee, $periodStart->toDateString(), $periodEnd->toDateString());

        $gross = 3000.00;
        $daysAbsent = max(0, $totalDays - $presentDays);
        $perDay = $totalDays > 0 ? 3000 / $totalDays : 0;
        $absenceDeduction = round($perDay * $daysAbsent, 2);
        $tax = round($gross * 0.10, 2);
        $expectedDeductions = round($absenceDeduction + $tax, 2);
        $expectedNet = round($gross - $expectedDeductions, 2);

        $this->assertEquals($gross, (float) $payslip->gross);
        $this->assertEquals($expectedDeductions, (float) $payslip->deductions);
        $this->assertEquals($expectedNet, (float) $payslip->net);
    }
}
