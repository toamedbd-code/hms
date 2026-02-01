<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DefineLeaveRequest extends FormRequest
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
                    'role_id' => '',
					'type_id' => '',
					'days' => 'required|numeric'
                ];
                break;

            case 'PATCH':
            case 'PUT':
                return [
                    'role_id' => '',
					'type_id' => '',
					'days' => 'required|numeric'
                ];
                break;
        }
    }

    public function messages()
    {
        return [
            'role_id.required' => 'The role field is required.',
 			'type_id.required' => 'The type field is required.',
 			'days.required' => 'The days field is required.',
 			

        ];
    }
}
