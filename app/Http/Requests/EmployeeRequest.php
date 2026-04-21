<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $employee = $this->route('employee');
        $id = $employee ? $employee->id : null;

        return [
            'employee_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_id')->ignore($id),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->ignore($id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'integer'],
            'hired_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
