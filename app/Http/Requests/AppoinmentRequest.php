<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppoinmentRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'patient_id' => 'required',
                    'doctor_id' => 'required',
                    'doctor_fee' => 'nullable|numeric|min:0',
                    'shift' => 'nullable|in:Morning,Evening,Night',
                    'appoinment_date' => 'required',
                    'slot' => 'nullable|in:Morning,Noon,Evening,Night',
                    'appointment_priority' => 'required|in:Normal,Urgent,Very Urgent,Low',
                    'payment_mode' => 'nullable|in:Cash,Cheque,Transfer to Bank Account,Upi,Online,Other',
                    'discount_percentage' => 'nullable|numeric|min:0|max:100',
                    'appoinment_status' => 'required|in:Pending,Approved,Cancelled,Completed',
                    'live_consultant' => 'required|in:Yes,No',
                    'message' => 'nullable|string',
                ];
                break;

            case 'PUT':
                return [
                    'patient_id' => 'required',
                    'doctor_id' => 'required',
                    'doctor_fee' => 'nullable|numeric|min:0',
                    'shift' => 'nullable|in:Morning,Evening,Night',
                    'appoinment_date' => 'required',
                    'slot' => 'nullable|in:Morning,Noon,Evening,Night',
                    'appointment_priority' => 'required|in:Normal,Urgent,Very Urgent,Low',
                    'payment_mode' => 'nullable|in:Cash,Cheque,Transfer to Bank Account,Upi,Online,Other',
                    'discount_percentage' => 'nullable|numeric|min:0|max:100',
                    'appoinment_status' => 'required|in:Pending,Approved,Cancelled,Completed',
                    'live_consultant' => 'required|in:Yes,No',
                    'message' => 'nullable|string',
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
            'patient_id.exists' => __('The selected patient is invalid.'),
            'doctor_id.required' => __('The doctor field is required.'),
            'doctor_id.exists' => __('The selected doctor is invalid.'),
            'doctor_fee.numeric' => __('The doctor fee must be a valid number.'),
            'appoinment_date.required' => __('The appointment date is required.'),
            'appoinment_date.date' => __('The appointment date must be a valid date.'),
            'appoinment_date.after_or_equal' => __('The appointment date cannot be in the past.'),
            'slot.in' => __('Select a valid time slot.'),
            'appointment_priority.in' => __('Select a valid priority.'),
            'payment_mode.in' => __('Select a valid payment mode.'),
            'discount_percentage.numeric' => __('Discount must be a number.'),
            'discount_percentage.max' => __('Discount cannot exceed 100%.'),
            'appoinment_status.in' => __('Select a valid appointment status.'),
            'live_consultant.in' => __('Select whether this is a live consultation.'),
        ];
    }
}