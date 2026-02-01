<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeaveTypeRequest extends FormRequest
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
                    'type_name' => 'required|string',
					'days' => 'required|numeric'
                ];
                break;

            case 'PATCH':
            case 'PUT':
                return [
                    'type_name' => 'required|string',
					'days' => 'required|numeric'
                ];
                break;
        }
    }

    public function messages()
    {
        return [
            'type_name.required' => 'The type name field is required.',
 			'days.required' => 'The days field is required.',
 			

        ];
    }
}
