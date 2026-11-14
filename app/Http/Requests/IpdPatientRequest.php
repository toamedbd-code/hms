<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IpdPatientRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return array_merge($this->commonRules(), [
                    'advance_amount' => 'nullable|numeric|min:0',
                ]);

            case 'PUT':
                return $this->commonRules();

            case 'PATCH':
                return [];
        }

        return [];
    }

    protected function commonRules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'consultant_doctor_id' => 'required|exists:admins,id',

            'symptom_type' => 'nullable|string|max:255',
            'symptom_title' => 'nullable|string|max:255',
            'symptom_description' => 'nullable|string',
            'note' => 'nullable|string',

            'admission_date' => 'required|date',
            'case' => 'nullable|string|max:255',
            'tpa' => 'nullable|string|max:255',
            'casualty' => 'required|in:yes,no',
            'old_patient' => 'required|in:yes,no',
            'credit_limit' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:255',

            'bed_group_id' => 'required|exists:bedgroups,id',
            'bed_id' => 'required|exists:beds,id',

            'live_consultation' => 'required|in:yes,no',
            'hospital_charge_items' => 'nullable|array',
            'hospital_charge_items.*.item_name' => 'nullable|string|max:255',
            'hospital_charge_items.*.unit_price' => 'nullable|numeric|min:0',
            'hospital_charge_items.*.quantity' => 'nullable|integer|min:1',
        ];
    }

    public function messages()
    {

        return [
            'patient_id.required' => 'Please select a patient',
            'patient_id.exists' => 'The selected patient does not exist',
            'consultant_doctor_id.required' => 'Please select a consultant doctor',
            'consultant_doctor_id.exists' => 'The selected doctor does not exist',
            'admission_date.required' => 'Admission date is required',
            'admission_date.date' => 'Please enter a valid date',
            'bed_group_id.required' => 'Please select a bed group',
            'bed_group_id.exists' => 'The selected bed group does not exist',
            'bed_id.required' => 'Bed number is required',
            'bed_id.exists' => 'The selected bed does not exist',
            'casualty.required' => 'Please specify if this is a casualty case',
            'old_patient.required' => 'Please specify if this is an old patient',
            'live_consultation.required' => 'Please specify if live consultation is needed',
            'credit_limit.numeric' => 'Credit limit must be a number',
            'advance_amount.numeric' => 'Advance amount must be a number',
        ];
    }
}
