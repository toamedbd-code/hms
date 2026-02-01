<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PathologyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

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
                    'bill_no' => 'required|string|unique:pathologies,bill_no,' . $this->id,
                    'case_id' => 'required|string',
                    'patient_id' => 'required',
                    'doctor_id' => 'nullable|exists:admins,id',
                    'apply_tpa' => 'nullable|boolean',
                    'date' => 'required|date',
                    'payee_id' => '',
                    'commission_percentage' => '',
                    'commission_amount' => '',
                    'note' => 'nullable|string|max:1000',

                    // Test validation
                    'tests' => 'required|array|min:1',

                    // Calculation fields
                    'subtotal' => 'required|numeric|min:0',
                    'discount_percentage' => 'required|numeric|min:0|max:100',
                    'discount_amount' => 'required|numeric|min:0',
                    'vat_percentage' => 'required|numeric|min:0|max:100',
                    'vat_amount' => 'required|numeric|min:0',
                    'tax_percentage' => 'required|numeric|min:0|max:100',
                    'tax_amount' => 'required|numeric|min:0',
                    'extra_vat_percentage' => 'required|numeric|min:0|max:100',
                    'extra_vat_amount' => 'required|numeric|min:0',
                    'extra_discount' => 'required|numeric|min:0',
                    'net_amount' => 'required|numeric|min:0',

                    // Payment fields
                    'payment_mode' => 'required|string|in:Cash,Card,Bank Transfer',
                    'payment_amount' => 'required|numeric|min:0',
                    'doctor_name' => 'nullable|string|max:255',
                ];

            case 'PUT':
            case 'PATCH':
                return [
                    'patient_id' => 'required|exists:patients,id',
                    'doctor_id' => 'nullable|exists:admins,id',
                    'apply_tpa' => 'nullable|boolean',
                    'date' => 'required|date',
                    'payee_id' => '',
                    'commission_percentage' => '',
                    'commission_amount' => '',
                    'note' => 'nullable|string|max:1000',

                    // Test validation
                    'tests' => 'required|array|min:1',

                    // Calculation fields
                    'subtotal' => 'required|numeric|min:0',
                    'discount_percentage' => 'required|numeric|min:0|max:100',
                    'discount_amount' => 'required|numeric|min:0',
                    'vat_percentage' => 'required|numeric|min:0|max:100',
                    'vat_amount' => 'required|numeric|min:0',
                    'tax_percentage' => 'required|numeric|min:0|max:100',
                    'tax_amount' => 'required|numeric|min:0',
                    'extra_vat_percentage' => 'required|numeric|min:0|max:100',
                    'extra_vat_amount' => 'required|numeric|min:0',
                    'extra_discount' => 'required|numeric|min:0',
                    'net_amount' => 'required|numeric|min:0',

                    // Payment fields
                    'payment_mode' => 'required|string|in:Cash,Card,Bank Transfer',
                    'payment_amount' => 'required|numeric|min:0',
                    'doctor_name' => 'nullable|string|max:255',
                ];

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
            'bill_no.required' => 'Bill number is required',
            'bill_no.unique' => 'This bill number already exists',
            'case_id.required' => 'Case ID is required',
            'patient_id.exists' => 'Selected patient does not exist',
            'doctor_id.exists' => 'Selected doctor does not exist',
            'tests.required' => 'At least one test is required',
            'tests.min' => 'At least one test is required',
            'tests.*.testName.required' => 'Test name is required',
            'tests.*.reportDate.required' => 'Report date is required',
            'tests.*.reportDate.after_or_equal' => 'Report date cannot be in the past',
            'tests.*.amount.required' => 'Amount is required for each test',
            'tests.*.amount.numeric' => 'Amount must be a number',
            'tests.*.amount.min' => 'Amount cannot be negative',
            'payee.required' => 'Payee selection is required',
            'commission_percentage.required' => 'Commission percentage is required',
            'commission_amount.required' => 'Commission amount is required',
            'paymentMode.required' => 'Payment mode is required',
            'paymentAmount.required' => 'Payment amount is required',
            'paymentAmount.lte' => 'Payment amount cannot exceed net amount',

            // Calculation field messages
            'subtotal.required' => 'Subtotal calculation is required',
            'netAmount.required' => 'Net amount calculation is required',
        ];
    }
}
