<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IpdPatientRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'patient_id' => 'required',
                    'consultant_doctor_id' => 'required',

                    'symptom_type' => 'nullable|string|max:255',
                    'symptom_title' => 'nullable|string|max:255',
                    'symptom_description' => 'nullable|string',
                    'note' => 'nullable|string',

                    'admission_date' => 'required',
                    'case' => 'nullable|string|max:255',
                    'tpa' => 'nullable|string|max:255',
                    'casualty' => 'required',
                    'old_patient' => 'required',
                    'credit_limit' => 'nullable|numeric',
                    'advance_amount' => 'nullable|numeric',
                    'reference' => 'nullable|string|max:255',

                    'bed_group_id' => 'required',
                    'bed_id' => 'required',

                    'live_consultation' => 'required',
                ];
                break;

            case 'PUT':
                return [
                    'patient_id' => 'required',
                    'consultant_doctor_id' => 'required',

                    'symptom_type' => 'nullable|string|max:255',
                    'symptom_title' => 'nullable|string|max:255',
                    'symptom_description' => 'nullable|string',
                    'note' => 'nullable|string',

                    'admission_date' => 'required',
                    'case' => 'nullable|string|max:255',
                    'tpa' => 'nullable|string|max:255',
                    'casualty' => 'required',
                    'old_patient' => 'required',
                    'credit_limit' => 'nullable|numeric',
                    'reference' => 'nullable|string|max:255',

                    'bed_group_id' => 'required',
                    'bed_id' => 'required',

                    'live_consultation' => 'required',
                ];
                break;
            case 'PATCH':

                break;
        }
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
            'casualty.required' => 'Please specify if this is a casualty case',
            'old_patient.required' => 'Please specify if this is an old patient',
            'live_consultation.required' => 'Please specify if live consultation is needed',
            'credit_limit.numeric' => 'Credit limit must be a number',
        ];
    }
}
