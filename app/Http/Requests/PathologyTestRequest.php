<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PathologyTestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'category_type' => 'required|string|max:255',
                    'test_name' => 'required|string|max:255',
                    'test_short_name' => 'nullable|string',
                    'test_type' => 'nullable|string',
                    'test_category_id' => 'required',
                    'test_sub_category_id' => 'nullable',
                    'method' => 'nullable|string|max:255',
                    'report_days' => 'nullable|integer|min:0',
                    'charge_id' => 'nullable',
                    'charge_category_id' => 'nullable',
                    'charge_name' => 'nullable|string|max:255',
                    'tax' => 'nullable|string|max:50',
                    'standard_charge' => 'nullable|numeric|min:0',
                    'amount' => 'nullable|numeric|min:0',
                    'parameters' => 'required|array|min:1',
                    'parameters.*.test_parameter_id' => 'nullable',
                    'parameters.*.name' => 'nullable|string|max:255',
                    'parameters.*.referance_from' => 'nullable|string',
                    'parameters.*.referance_to' => 'nullable|string',
                    'parameters.*.pathology_unit_id' => 'nullable',
                ];

            case 'PUT':
                return [
                    'category_type' => 'required|string|max:255',
                    'test_name' => 'required|string|max:255',
                    'test_short_name' => 'nullable|string',
                    'test_type' => 'nullable|string',
                    'test_category_id' => 'required',
                    'test_sub_category_id' => 'nullable',
                    'method' => 'nullable|string|max:255',
                    'report_days' => 'nullable|integer|min:0',
                    'charge_id' => 'nullable',
                    'charge_category_id' => 'nullable',
                    'charge_name' => 'nullable|string|max:255',
                    'tax' => 'nullable|string|max:50',
                    'standard_charge' => 'nullable|numeric|min:0',
                    'amount' => 'nullable|numeric|min:0',
                    'parameters' => 'required|array|min:1',
                    'parameters.*.test_parameter_id' => 'nullable',
                    'parameters.*.name' => 'nullable|string|max:255',
                    'parameters.*.referance_from' => 'nullable|string',
                    'parameters.*.referance_to' => 'nullable|string',
                    'parameters.*.pathology_unit_id' => 'nullable',
                ];

            default:
                return [];
        }
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_type.required' => __('The category type field is required.'),
            'test_name.required' => 'The test name field is required.',
            'test_category_id.required' => 'The category field is required.',
            'test_sub_category_id.required' => 'The sub category field is required.',
            'test_category_id.exists' => 'The selected category is invalid.',
            'charge_id.exists' => 'The selected charge is invalid.',
            'charge_category_id.exists' => 'The selected charge category is invalid.',
            'parameters.required' => 'At least one parameter is required.',
            'parameters.*.test_parameter_id.exists' => 'The selected parameter is invalid.',
            'parameters.*.pathology_unit_id.exists' => 'The selected unit is invalid.',
            'standard_charge.numeric' => 'The standard charge must be a valid number.',
            'amount.numeric' => 'The amount must be a valid number.',
            'report_days.integer' => 'The report days must be a valid integer.',
        ];
    }
}
