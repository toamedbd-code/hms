<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $q = Employee::query();
        if ($request->filled('search')) {
            $q->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%');
        }

        $employees = $q->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Backend/Employees/Index', [
            'pageTitle' => 'Employees',
            'employees' => $employees,
            'filters' => $request->only('search', 'per_page'),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('Backend/Employees/Form', [
            'pageTitle' => 'Create Employee',
        ]);
    }

    public function store(EmployeeRequest $request)
    {
        $this->authorize('create', Employee::class);

        $data = $request->validated();
        Employee::create($data);

        return redirect()->route('employee.index')->with('success', 'Employee created.');
    }

    public function edit(Employee $employee)
    {
        $this->authorize('update', $employee);

        return Inertia::render('Backend/Employees/Form', [
            'pageTitle' => 'Edit Employee',
            'employee' => $employee,
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $employee->update($request->validated());

        return redirect()->route('employee.index')->with('success', 'Employee updated.');
    }

    public function destroy(Employee $employee)
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return redirect()->route('employee.index')->with('success', 'Employee deleted.');
    }
}
