<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BirthDeathRecordRequest extends FormRequest
{
 public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'record_type' => 'required|in:Birth,Death',
                    'child_name' => 'required_if:record_type,Birth|nullable|string|max:255',
                    'gender' => 'required_if:record_type,Birth|nullable|in:Male,Female,Other',
                    'weight' => 'required_if:record_type,Birth|nullable|string|max:60',
                    'birth_date' => 'required_if:record_type,Birth|nullable|date',
                    'phone' => 'nullable|string|max:30',
                    'address' => 'nullable|string|max:1000',
                    'case_id' => 'required_if:record_type,Death|nullable|string|max:120',
                    'mother_name' => 'required_if:record_type,Birth|nullable|string|max:255',
                    'father_name' => 'nullable|string|max:255',
                    'patient_name' => 'required_if:record_type,Death|nullable|string|max:255',
                    'death_date' => 'required_if:record_type,Death|nullable|date',
                    'guardian_name' => 'required_if:record_type,Death|nullable|string|max:255',
                    'report' => 'nullable|string|max:3000',
                    'child_photo' => 'nullable|file|mimes:png,jpg,jpeg|max:25048',
                    'mother_photo' => 'nullable|file|mimes:png,jpg,jpeg|max:25048',
                    'father_photo' => 'nullable|file|mimes:png,jpg,jpeg|max:25048',
                    'attachment' => 'required_if:record_type,Death|nullable|file|mimes:png,jpg,jpeg,pdf,doc,docx|max:30048',
                    'report_attachment' => 'nullable|file|mimes:png,jpg,jpeg,pdf,doc,docx|max:30048',
                ];
                break;

            case 'PUT':
                return [
                    'record_type' => 'required|in:Birth,Death',
                    'child_name' => 'required_if:record_type,Birth|nullable|string|max:255',
                    'gender' => 'required_if:record_type,Birth|nullable|in:Male,Female,Other',
                    'weight' => 'required_if:record_type,Birth|nullable|string|max:60',
                    'birth_date' => 'required_if:record_type,Birth|nullable|date',
                    'phone' => 'nullable|string|max:30',
                    'address' => 'nullable|string|max:1000',
                    'case_id' => 'required_if:record_type,Death|nullable|string|max:120',
                    'mother_name' => 'required_if:record_type,Birth|nullable|string|max:255',
                    'father_name' => 'nullable|string|max:255',
                    'patient_name' => 'required_if:record_type,Death|nullable|string|max:255',
                    'death_date' => 'required_if:record_type,Death|nullable|date',
                    'guardian_name' => 'required_if:record_type,Death|nullable|string|max:255',
                    'report' => 'nullable|string|max:3000',
                    'child_photo' => 'nullable|file|mimes:png,jpg,jpeg|max:25048',
                    'mother_photo' => 'nullable|file|mimes:png,jpg,jpeg|max:25048',
                    'father_photo' => 'nullable|file|mimes:png,jpg,jpeg|max:25048',
                    'attachment' => 'nullable|file|mimes:png,jpg,jpeg,pdf,doc,docx|max:30048',
                    'report_attachment' => 'nullable|file|mimes:png,jpg,jpeg,pdf,doc,docx|max:30048',
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
            'child_name.required_if' => __('The child name field is required for birth record.'),
            'patient_name.required_if' => __('The patient name field is required for death record.'),
            'gender.required_if' => __('The gender field is required for birth record.'),
            'weight.required_if' => __('The weight field is required for birth record.'),
            'birth_date.required_if' => __('The birth date field is required for birth record.'),
            'case_id.required_if' => __('The case ID field is required for death record.'),
            'mother_name.required_if' => __('The mother name field is required for birth record.'),
            'death_date.required_if' => __('The death date field is required for death record.'),
            'guardian_name.required_if' => __('The guardian name field is required for death record.'),
            'attachment.required_if' => __('The attachment field is required for death record.'),
        ];
    }
}