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
        $isCreate = $this->isMethod('post');

        return [
            'employee_id' => array_filter([
                $isCreate ? 'required' : 'sometimes',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_id')->ignore($id),
            ]),
            'first_name' => $isCreate ? ['required', 'string', 'max:255'] : ['sometimes', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => array_filter([
                $isCreate ? 'nullable' : 'sometimes',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->ignore($id),
            ]),
            'phone' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'integer'],
            'hired_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
