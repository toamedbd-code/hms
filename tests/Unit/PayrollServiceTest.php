<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Employee;
use App\Models\Salary;
use App\Models\Attendance;
use App\Services\PayrollService;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_runs_payroll_for_employee()
    {
        $employee = Employee::create([
            'employee_id' => 'EMP001',
            'first_name' => 'Test',
        ]);

        Salary::create([
            'employee_id' => $employee->id,
            'basic' => 1000,
            'allowances' => 200,
            'deductions' => 50,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        Attendance::create([
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $service = new PayrollService();
        $payslip = $service->runForEmployee($employee, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertEquals(1200.00, (float) $payslip->gross);
        $this->assertEquals(50.00, (float) $payslip->deductions);
        $this->assertEquals(1150.00, (float) $payslip->net);
    }
}
