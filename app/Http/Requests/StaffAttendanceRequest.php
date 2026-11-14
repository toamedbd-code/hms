<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StaffAttendanceRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
            case 'PATCH':
            case 'PUT':
                return [
                    'attendance_date' => 'required|date',
                    'records' => 'required|array',
                    'records.*.staff_id' => '',
                    'records.*.name' => 'required|string',
                    'records.*.attendance_status' => 'nullable|string',
                    'records.*.in_time' => 'nullable',
                    'records.*.out_time' => 'nullable',
                    'records.*.note' => 'nullable|string|max:255',
                ];
        }
    }

    public function messages()
    {
        return [
            'staff_id.required' => 'Staff ID is required.',
            'name.required' => 'Name is required.',
            'records.*.name.required' => 'Staff name is required.',
            'role_id.required' => 'Role is required.',
            'attendance_date.required' => 'Attendance date is required.',
            'attendance_status.required' => 'Attendance status is required.',
            'in_time.required' => 'In time is required.',
            'out_time.required' => 'Out time is required.',
            'note.required' => 'Note is required.',
        ];
    }
}
