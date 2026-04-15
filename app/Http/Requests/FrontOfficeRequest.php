<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FrontOfficeRequest extends FormRequest
{
 public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'purpose' => 'required|string|max:255',
                    'visit_to' => 'required|string|max:255',
                    'phone' => 'required|string|max:30',
                    'date_in' => 'required|date',
                    'time_in' => 'required|date_format:H:i',
                    'time_out' => 'nullable|date_format:H:i',
                    'photo' => 'nullable|file|mimes:png,jpg,jpeg|max:25048',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required|string|max:255',
                    'purpose' => 'required|string|max:255',
                    'visit_to' => 'required|string|max:255',
                    'phone' => 'required|string|max:30',
                    'date_in' => 'required|date',
                    'time_in' => 'required|date_format:H:i',
                    'time_out' => 'nullable|date_format:H:i',
                    'photo' => 'nullable|file|mimes:png,jpg,jpeg|max:25048',
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
            'name.required' => __('The name field is required.'),
            'purpose.required' => __('The purpose field is required.'),
            'visit_to.required' => __('The visit to field is required.'),
            'phone.required' => __('The phone number field is required.'),
            'date_in.required' => __('The date in field is required.'),
            'time_in.required' => __('The time in field is required.'),
            'photo.file' => __('The photo must be a file.'),
            'photo.mimes' => __('The photo must be a file of type: png, jpg, jpeg.'),
            'photo.max' => __('The photo may not be greater than :max kilobytes.'),
        ];
    }
}