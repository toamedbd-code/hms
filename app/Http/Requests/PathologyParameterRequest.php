<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PathologyParameterRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'referance_from' => 'required|string|max:255',
                    'referance_to' => 'required|string|max:255',
                    'pathology_unit_id' => 'required',
                    'description' => 'nullable|string',
                ];
                break;

            case 'PUT':
            case 'PATCH':
                return [
                    'name' => 'required|string|max:255',
                    'referance_from' => 'required|string|max:255',
                    'referance_to' => 'required|string|max:255',
                    'pathology_unit_id' => 'required',
                    'description' => 'nullable|string',
                ];
                break;

            default:
                return [];
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
            'name.required' => __('The parameter name field is required.'),
            'referance_from.required' => __('The reference range from field is required.'),
            'referance_to.required' => __('The reference range to field is required.'),
            'pathology_unit_id.required' => __('The unit field is required.'),
            'pathology_unit_id.exists' => __('The selected unit is invalid.'),
        ];
    }
}