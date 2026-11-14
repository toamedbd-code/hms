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
            'type' => 'in',
            'recorded_at' => now()->startOfDay()->addHours(9),
        ]);

        $service = new PayrollService();
        $payslip = $service->runForEmployee($employee, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertEquals(1200.00, (float) $payslip->gross);
        $this->assertEquals(50.00, (float) $payslip->deductions);
        $this->assertEquals(1150.00, (float) $payslip->net);
        $this->assertSame(1, (int) $payslip->days_present);
    }

    public function test_repeated_device_punches_on_one_day_count_as_a_single_present_day()
    {
        $employee = Employee::create([
            'employee_id' => 'EMP003',
            'first_name' => 'Punch',
        ]);

        Salary::create([
            'employee_id' => $employee->id,
            'basic' => 1000,
            'allowances' => 0,
            'deductions' => 0,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        foreach ([9, 13, 17] as $hour) {
            Attendance::create([
                'employee_id' => $employee->id,
                'type' => 'in',
                'recorded_at' => now()->startOfDay()->addHours($hour),
            ]);
        }

        // Punch-outs are not presence markers on their own.
        Attendance::create([
            'employee_id' => $employee->id,
            'type' => 'out',
            'recorded_at' => now()->startOfDay()->addHours(18),
        ]);

        $service = new PayrollService();
        $payslip = $service->runForEmployee($employee, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertSame(1, (int) $payslip->days_present);
    }

    public function test_punches_matched_by_employee_code_are_counted()
    {
        $employee = Employee::create([
            'employee_id' => 'EMP004',
            'first_name' => 'Coded',
        ]);

        Salary::create([
            'employee_id' => $employee->id,
            'basic' => 1000,
            'allowances' => 0,
            'deductions' => 0,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        // Device feeds often only know the code, not the employee row id.
        Attendance::create([
            'employee_code' => 'EMP004',
            'type' => 'in',
            'recorded_at' => now()->startOfDay()->addHours(9),
        ]);

        $service = new PayrollService();
        $payslip = $service->runForEmployee($employee, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertSame(1, (int) $payslip->days_present);
    }
}
