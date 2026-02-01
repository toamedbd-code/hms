<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TpaRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'code' => 'required',
                    'contact_number' => 'required',
                    'address' => 'nullable',
                    'contact_person_name' => 'nullable',
                    'contact_person_phone' => 'nullable',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required|string|max:255',
                    'code' => 'required',
                    'contact_number' => 'required',
                    'address' => 'nullable',
                    'contact_person_name' => 'nullable',
                    'contact_person_phone' => 'nullable',
                ];
                break;
            case 'PATCH':

                break;
        }
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, mixed>
     */
    public function messages()
    {

        return [
            'name.required' => __('The name field is required.'),
            'code.required' => __('The code field is required.'),
            'contact_number.required' => __('The contact number field is required.'),            
        ];
    }
}
