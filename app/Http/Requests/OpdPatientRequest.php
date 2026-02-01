<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpdPatientRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'patient_id' => 'required|exists:patients,id',
                    'symptom_type' => 'nullable|string|max:255',
                    'symptom_title' => 'nullable|string|max:255',
                    'symptom_description' => 'nullable|string',
                    'note' => 'nullable|string',
                    'allergies' => 'nullable|string',
                    'appointment_date' => 'required|date',
                    'case' => 'required|in:new,followup,emergency',
                    'casualty' => 'required|in:yes,no',
                    'old_patient' => 'required|in:yes,no',
                    'reference' => 'nullable|string|max:255',
                    'consultant_doctor_id' => 'required|exists:admins,id',
                    'apply_tpa' => 'boolean',
                    'tpa_details' => 'nullable|string|max:255',
                    'charge_id' => 'required',
                    'charge_type_id' => 'required',
                    'applied_charge' => 'nullable|numeric|min:0',
                    'standard_charge' => 'nullable|numeric|min:0',
                    'tax' => 'nullable|numeric|min:0|max:100',
                    'discount' => 'nullable|numeric|min:0|max:100',
                    'payment_mode' => 'required|in:cash,card,online,insurance',
                    'amount' => 'nullable|numeric|min:0',
                    'paid_amount' => 'nullable|numeric|min:0',
                    'balance_amount' => 'nullable|numeric|min:0',
                    'live_consultation' => 'required|in:yes,no',
                    'consultation_type' => 'nullable|string|max:255',
                ];
                break;

            case 'PUT':
                return [
                    'patient_id' => 'required|exists:patients,id',
                    'symptom_type' => 'nullable|string|max:255',
                    'symptom_title' => 'nullable|string|max:255',
                    'symptom_description' => 'nullable|string',
                    'note' => 'nullable|string',
                    'allergies' => 'nullable|string',
                    'appointment_date' => 'required|date',
                    'case' => 'required|in:new,followup,emergency',
                    'casualty' => 'required|in:yes,no',
                    'old_patient' => 'required|in:yes,no',
                    'reference' => 'nullable|string|max:255',
                    'consultant_doctor_id' => 'required|exists:admins,id',
                    'apply_tpa' => 'boolean',
                    'tpa_details' => 'nullable|string|max:255',
                    'charge_id' => 'required',
                    'charge_type_id' => 'required',
                    'applied_charge' => 'nullable|numeric|min:0',
                    'standard_charge' => 'nullable|numeric|min:0',
                    'tax' => 'nullable|numeric|min:0|max:100',
                    'discount' => 'nullable|numeric|min:0|max:100',
                    'payment_mode' => 'required|in:cash,card,online,insurance',
                    'amount' => 'nullable|numeric|min:0',
                    'paid_amount' => 'nullable|numeric|min:0',
                    'balance_amount' => 'nullable|numeric|min:0',
                    'live_consultation' => 'required|in:yes,no',
                    'consultation_type' => 'nullable|string|max:255',
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
            'patient_id.required' => __('The patient is required.'),
            'patient_id.exists' => __('The selected patient is invalid.'),
            'appointment_date.required' => __('The appointment date is required.'),
            'appointment_date.date' => __('The appointment date must be a valid date.'),
            'appointment_date.after_or_equal' => __('The appointment date cannot be in the past.'),
            'case.in' => __('The case type must be one of: new, followup, emergency.'),
            'casualty.in' => __('Casualty must be yes or no.'),
            'old_patient.in' => __('Old patient must be yes or no.'),
            'consultant_doctor_id.required' => __('A consultant doctor is required.'),
            'consultant_doctor_id.exists' => __('The selected doctor is invalid.'),
            'payment_mode.in' => __('Select a valid payment mode.'),
            'live_consultation.in' => __('Live consultation must be yes or no.'),
        ];
    }
}
