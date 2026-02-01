<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicineSupplierRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required',
                    'phone' => 'required',
                    'contact_person_name' => 'required',
                    'contact_person_phone' => 'required',
                    'drug_lisence_no' => 'required',
                    'address' => 'required',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required',
                    'phone' => 'required',
                    'contact_person_name' => 'required',
                    'contact_person_phone' => 'required',
                    'drug_lisence_no' => 'required',
                    'address' => 'required',
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
            'phone.required' => __('The phone field is required.'),
            'contact_person_name.required' => __('The contact person name field is required.'),
            'contact_person_phone.required' => __('The contact person phone field is required.'),
            'drug_lisence_no.required' => __('The drug lisence no field is required.'),
            'address.required' => __('The address field is required.'),
            
        ];
    }
}
