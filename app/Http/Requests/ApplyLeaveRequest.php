<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyLeaveRequest extends FormRequest
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
                return [
                    'apply_date' => 'required',
					'leave_type_id' => '',
                    'employee_id' => '',
					'from' => 'required',
					'to' => 'required',
					'reason' => 'required|string',
					'attachment' => 'required'
                ];
                break;

            case 'PATCH':
            case 'PUT':
                return [
                    'apply_date' => 'required',
					'leave_type_id' => '',
                    'employee_id' => '',
					'from' => 'required',
					'to' => 'required',
					'reason' => 'required|string',
					'attachment' => ''
                ];
                break;
        }
    }

    public function messages()
    {
        return [
            'apply_date.required' => 'The Apply date field is required.',
 			'leave_type_id.required' => 'The Leave Type field is required.',
 			'employee_id.required' => 'The Employee Name field is required.',
 			'from.required' => 'The From field is required.',
 			'to.required' => 'The To field is required.',
 			'reason.required' => 'The Reason field is required.',
 			'attachment.required' => 'The Attachment field is required.',


        ];
    }
}
