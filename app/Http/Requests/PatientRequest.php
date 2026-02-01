<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientRequest extends FormRequest
{
 public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'guardian_name' => 'nullable',
                    'gender' => 'required',
                    'dob' => 'nullable',
                    'blood_group' => 'nullable',
                    'marital_status' => 'nullable',
                    'photo' => 'nullable',
                    'phone' => 'required',
                    'email' => 'nullable',
                    'address' => 'nullable',
                    'remarks' => 'nullable',
                    'any_known_allergies' => 'nullable',
                    'tpa_id' => 'nullable',
                    'tpa_code' => 'nullable',
                    'tpa_validity' => 'nullable',
                    'tpa_nid' => 'nullable',
                    'age' => 'nullable',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required|string|max:255',
                    'guardian_name' => 'nullable',
                    'gender' => 'required',
                    'dob' => 'nullable',
                    'blood_group' => 'nullable',
                    'marital_status' => 'nullable',
                    'photo' => 'nullable',
                    'phone' => 'required',
                    'email' => 'nullable',
                    'address' => 'nullable',
                    'remarks' => 'nullable',
                    'any_known_allergies' => 'nullable',
                    'tpa_id' => 'nullable',
                    'tpa_code' => 'nullable',
                    'tpa_validity' => 'nullable',
                    'tpa_nid' => 'nullable',
                    'age' => 'nullable',
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
            'gender.required' => __('The gender field is required.'),
            'dob.required' => __('The date of birth field is required.'),
            'blood_group.required' => __('The blood group field is required.'),
            'marital_status.required' => __('The marital status field is required.'),
            'phone.required' => __('The phone field is required.'),
            'address.required' => __('The address field is required.'),
            'remarks.required' => __('The remarks field is required.'),
            'age.required' => __('The age field is required.'),
            
        ];
    }
}