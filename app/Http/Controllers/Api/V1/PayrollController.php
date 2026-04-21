<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PayrollService;
use App\Models\Employee;

class PayrollController extends Controller
{
    protected PayrollService $service;

    public function __construct(PayrollService $service)
    {
        $this->service = $service;
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $createdBy = optional($request->user())->id ?? null;

        if (!empty($data['employee_id'])) {
            $employee = Employee::findOrFail($data['employee_id']);
            $payslip = $this->service->runForEmployee($employee, $data['period_start'], $data['period_end'], $createdBy);
            return response()->json($payslip, 201);
        }

        $results = $this->service->runForAll($data['period_start'], $data['period_end'], $createdBy);
        return response()->json($results, 201);
    }
}
