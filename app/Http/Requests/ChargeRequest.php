<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChargeRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'charge_type_id' => 'required',
                    'charge_category_id' => 'required',
                    'unit_type_id' => 'required',
                    'tax_category_id' => 'required',
                    'tax' => 'nullable|numeric',
                    'standard_charge' => 'required|numeric',
                    'description' => 'nullable|string',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required|string|max:255',
                    'charge_type_id' => 'required',
                    'charge_category_id' => 'required',
                    'unit_type_id' => 'required',
                    'tax_category_id' => 'required',
                    'tax' => 'nullable|numeric',
                    'standard_charge' => 'required|numeric',
                    'description' => 'nullable|string',
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
            'name.required' => __('The charge name field is required.'),
            'charge_type_id.required' => __('The charge type field is required.'),
            'charge_category_id.required' => __('The charge category field is required.'),
            'unit_type_id.required' => __('The unit type field is required.'),
            'tax_category_id.required' => __('The tax category field is required.'),
            'standard_charge.numeric' => __('The standard charge field must be a number.'),
            'standard_charge.min' => __('The standard charge field must be at least 0.'),
            'description.max' => __('The description field must not be greater than 1000 characters.'),

        ];
    }
}
