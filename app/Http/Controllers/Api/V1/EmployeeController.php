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
        $this->authorize('viewAny', Employee::class);
        $perPage = (int) $request->get('per_page', 15);
        $list = Employee::orderBy('id', 'desc')->paginate($perPage);
        return response()->json($list);
    }

    public function store(EmployeeRequest $request)
    {
        $this->authorize('create', Employee::class);
        $data = $request->validated();
        $employee = Employee::create($data);
        return response()->json($employee, 201);
    }

    public function show(Employee $employee)
    {
        $this->authorize('view', $employee);
        return response()->json($employee);
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        $this->authorize('update', $employee);
        $employee->update($request->validated());
        return response()->json($employee);
    }

    public function destroy(Employee $employee)
    {
        $this->authorize('delete', $employee);
        $employee->delete();
        return response()->json(null, 204);
    }
}
