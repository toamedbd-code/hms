<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Http\Requests\EmployeeRequest;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $list = Employee::orderBy('id', 'desc')->paginate($perPage);
        return response()->json($list);
    }

    public function store(EmployeeRequest $request)
    {
        $data = $request->validated();
        $employee = Employee::create($data);
        return response()->json($employee, 201);
    }

    public function show(Employee $employee)
    {
        return response()->json($employee);
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());
        return response()->json($employee);
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return response()->json(null, 204);
    }
}
