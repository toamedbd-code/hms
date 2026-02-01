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
            'staff_id.required' => 'The staff_id field is required.',
 			'name.required' => 'The name field is required.',
 			'role_id.required' => 'The role field is required.',
 			'attendance_date.required' => 'The attendance date field is required.',
 			'attendance_status.required' => 'The attendance field is required.',
 			'in_time.required' => 'The in time field is required.',
 			'out_time.required' => 'The out time field is required.',
 			'note.required' => 'The note field is required.',
 		
        ];
    }
}
