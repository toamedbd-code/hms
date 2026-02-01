<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BloodIssueRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'case_id' => 'nullable|string|max:255',
                    'patient_id' => 'required|exists:patients,id',
                    'issue_date' => 'required|date',
                    'doctor_id' => 'nullable',
                    'reference_name' => 'required|string|max:255',
                    'technician' => 'nullable|string|max:255',
                    'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
                    'bag' => 'required|string|max:255',
                    'charge_category' => 'required|string|max:255',
                    'charge_name' => 'required|string|max:255',
                    'standard_charge' => 'required|numeric|min:0',
                    'note' => 'nullable|string',
                    'total' => 'nullable|numeric|min:0',
                    'discount' => 'nullable|numeric|min:0',
                    'discount_percent' => 'nullable|numeric|min:0|max:100',
                    'tax' => 'nullable|numeric|min:0',
                    'tax_percent' => 'nullable|numeric|min:0|max:100',
                    'net_amount' => 'nullable|numeric|min:0',
                    'payment_mode' => 'required|in:Cash,Card,Cheque',
                    'payment_amount' => 'required|numeric|min:0',
                    'apply_tpa' => 'nullable|boolean',
                ];
                break;

            case 'PUT':
                return [
                    'case_id' => 'nullable|string|max:255',
                    'patient_id' => 'required|exists:patients,id',
                    'issue_date' => 'required|date',
                    'doctor_id' => 'nullable',
                    'reference_name' => 'required|string|max:255',
                    'technician' => 'nullable|string|max:255',
                    'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
                    'bag' => 'required|string|max:255',
                    'charge_category' => 'required|string|max:255',
                    'charge_name' => 'required|string|max:255',
                    'standard_charge' => 'required|numeric|min:0',
                    'note' => 'nullable|string',
                    'total' => 'nullable|numeric|min:0',
                    'discount' => 'nullable|numeric|min:0',
                    'discount_percent' => 'nullable|numeric|min:0|max:100',
                    'tax' => 'nullable|numeric|min:0',
                    'tax_percent' => 'nullable|numeric|min:0|max:100',
                    'net_amount' => 'nullable|numeric|min:0',
                    'payment_mode' => 'required|in:Cash,Card,Cheque',
                    'payment_amount' => 'required|numeric|min:0',
                    'apply_tpa' => 'nullable|boolean',
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
            'patient_id.required' => __('The patient field is required.'),
            'patient_id.exists' => __('The selected patient does not exist.'),
            'issue_date.required' => __('The issue date field is required.'),
            'issue_date.date' => __('The issue date must be a valid date.'),
            'reference_name.required' => __('The reference name field is required.'),

            // Blood Information
            'blood_group.in' => __('The selected blood group is invalid.'),
            'bag.required' => __('The bag field is required.'),

            // Charge Information
            'charge_category.required' => __('The charge category field is required.'),
            'charge_name.required' => __('The charge name field is required.'),
            'standard_charge.required' => __('The standard charge field is required.'),
            'standard_charge.numeric' => __('The standard charge must be a number.'),
            'standard_charge.min' => __('The standard charge must be at least 0.'),

            // Financial Calculations
            'total.numeric' => __('The total must be a number.'),
            'total.min' => __('The total must be at least 0.'),
            'discount.numeric' => __('The discount must be a number.'),
            'discount.min' => __('The discount must be at least 0.'),
            'discount_percent.numeric' => __('The discount percent must be a number.'),
            'discount_percent.min' => __('The discount percent must be at least 0.'),
            'discount_percent.max' => __('The discount percent may not be greater than 100.'),
            'tax.numeric' => __('The tax must be a number.'),
            'tax.min' => __('The tax must be at least 0.'),
            'tax_percent.numeric' => __('The tax percent must be a number.'),
            'tax_percent.min' => __('The tax percent must be at least 0.'),
            'tax_percent.max' => __('The tax percent may not be greater than 100.'),
            'net_amount.numeric' => __('The net amount must be a number.'),
            'net_amount.min' => __('The net amount must be at least 0.'),

            // Payment Information
            'payment_mode.required' => __('The payment mode field is required.'),
            'payment_mode.in' => __('The selected payment mode is invalid.'),
            'payment_amount.required' => __('The payment amount field is required.'),
            'payment_amount.numeric' => __('The payment amount must be a number.'),
            'payment_amount.min' => __('The payment amount must be at least 0.'),
            'apply_tpa.boolean' => __('The apply TPA field must be true or false.'),
        ];
    }
}
