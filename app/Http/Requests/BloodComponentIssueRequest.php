<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BloodComponentIssueRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'patient_id' => 'required|exists:patients,id',
                    'case_id' => 'nullable',
                    'issue_date' => 'required|date',
                    'doctor_id' => 'required|exists:admins,id',
                    'reference_name' => 'required',
                    'technician' => 'nullable',
                    'blood_group' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
                    'bag' => 'required',
                    'charge_category' => 'required',
                    'charge_name' => 'required',
                    'standard_charge' => 'required|numeric|min:0',
                    'note' => 'nullable|string',
                    'discount_percent' => 'nullable|numeric|between:0,100',
                    'tax_percent' => 'nullable|numeric|between:0,100',
                    'payment_mode' => 'required|in:Cash,Card,Cheque,Insurance,TPA',
                    'payment_amount' => 'required|numeric|min:0',
                    'apply_tpa' => 'boolean',
                    'total' => 'required|numeric|min:0',
                    'discount' => 'nullable|numeric|min:0',
                    'tax' => 'nullable|numeric|min:0',
                    'net_amount' => 'required|numeric|min:0',
                ];
                break;

            case 'PUT':
                return [
                    'patient_id' => 'required|exists:patients,id',
                    'case_id' => 'nullable',
                    'issue_date' => 'required|date',
                    'doctor_id' => 'required|exists:admins,id',
                    'reference_name' => 'required',
                    'technician' => 'nullable',
                    'blood_group' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
                    'bag' => 'required',
                    'charge_category' => 'required',
                    'charge_name' => 'required',
                    'standard_charge' => 'required|numeric|min:0',
                    'note' => 'nullable|string',
                    'discount_percent' => 'nullable|numeric|between:0,100',
                    'tax_percent' => 'nullable|numeric|between:0,100',
                    'payment_mode' => 'required|in:Cash,Card,Cheque,Insurance,TPA',
                    'payment_amount' => 'required|numeric|min:0',
                    'apply_tpa' => 'boolean',
                    'total' => 'required|numeric|min:0',
                    'discount' => 'nullable|numeric|min:0',
                    'tax' => 'nullable|numeric|min:0',
                    'net_amount' => 'required|numeric|min:0',
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
            'patient_id.required' => 'Please select a patient',
            'patient_id.exists' => 'The selected patient does not exist',
            'issue_date.required' => 'Issue date is required',
            'issue_date.date' => 'Please enter a valid date',
            'doctor_id.required' => 'Please select a doctor',
            'doctor_id.exists' => 'The selected doctor does not exist',
            'reference_name.required' => 'Reference name is required',
            'blood_group.required' => 'Blood group is required',
            'blood_group.in' => 'Please select a valid blood group',
            'bag.required' => 'Bag number is required',
            'charge_category.required' => 'Charge category is required',
            'charge_name.required' => 'Charge name is required',
            'standard_charge.required' => 'Standard charge is required',
            'standard_charge.numeric' => 'Standard charge must be a number',
            'standard_charge.min' => 'Standard charge cannot be negative',
            'payment_mode.required' => 'Payment mode is required',
            'payment_mode.in' => 'Please select a valid payment mode',
            'payment_amount.required' => 'Payment amount is required',
            'payment_amount.numeric' => 'Payment amount must be a number',
            'payment_amount.min' => 'Payment amount cannot be negative',
            'total.required' => 'Total amount is required',
            'total.numeric' => 'Total amount must be a number',
            'total.min' => 'Total amount cannot be negative',
            'net_amount.required' => 'Net amount is required',
            'net_amount.numeric' => 'Net amount must be a number',
            'net_amount.min' => 'Net amount cannot be negative',
        ];
    }
}
