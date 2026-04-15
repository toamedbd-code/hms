<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificateRequest extends FormRequest
{
 public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'certificate_type' => 'required|string|max:255',
                    'issue_date' => 'required|date',
                    'reference_no' => 'nullable|string|max:120',
                    'email' => 'nullable|email|unique:certificates,email|max:255',
                    'details' => 'nullable|string|max:2000',
                    'photo' => 'nullable|file|mimes:png,jpg,jpeg|max:25048',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required|string|max:255',
                    'certificate_type' => 'required|string|max:255',
                    'issue_date' => 'required|date',
                    'reference_no' => 'nullable|string|max:120',
                    'email' => 'nullable|email|max:255|unique:certificates,email,' . $this->id,
                    'details' => 'nullable|string|max:2000',
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
            'name.required' => __('The first name field is required.'),
            'email.required' => __('The email field is required.'),
            'email.email' => __('Please enter a valid email address.'),
            'email.unique' => __('This email address is already taken.'),
            'photo.file' => __('The photo must be a file.'),
            'photo.mimes' => __('The photo must be a file of type: png, jpg, jpeg.'),
            'photo.max' => __('The photo may not be greater than :max kilobytes.'),
        ];
    }
}