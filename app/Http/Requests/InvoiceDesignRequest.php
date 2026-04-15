<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceDesignRequest extends FormRequest
{
    public function rules()
    {
        $rules = [
            'footer_content' => 'nullable|string|max:2000',
            'module' => 'required|in:opd,ipd,pathology,radiology,pharmacy,appointment,billing,prescription',
            'header_height' => 'nullable|integer|min:0|max:1000',
            'footer_height' => 'nullable|integer|min:0|max:1000',
        ];

        // Common file rules
        $fileRules = 'nullable|file|mimes:png,jpg,jpeg|max:25048';

        // Different rules for create vs update
        if ($this->method() === 'POST') {
            $rules['headerPhoto'] = $fileRules;
            $rules['footerPhoto'] = $fileRules;
        } else {
            // PUT/PATCH (update)
            $rules['headerPhoto'] = $fileRules;
            $rules['footerPhoto'] = $fileRules;
        }

        return $rules;
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, mixed>
     */
    public function messages()
    {
        return [
            'footer_content.required' => __('The footer content field is required.'),
            'footer_content.string' => __('The footer content must be a string.'),
            'footer_content.max' => __('The footer content may not be greater than :max characters.'),
            'header_height.integer' => __('Header height must be an integer.'),
            'header_height.min' => __('Header height must be at least :min.'),
            'header_height.max' => __('Header height may not be greater than :max.'),
            'footer_height.integer' => __('Footer height must be an integer.'),
            'footer_height.min' => __('Footer height must be at least :min.'),
            'footer_height.max' => __('Footer height may not be greater than :max.'),
            
            'headerPhoto.file' => __('The header image must be a file.'),
            'headerPhoto.mimes' => __('The header image must be a file of type: png, jpg, jpeg.'),
            'headerPhoto.max' => __('The header image may not be greater than :max kilobytes.'),
            
            'footerPhoto.file' => __('The footer image must be a file.'),
            'footerPhoto.mimes' => __('The footer image must be a file of type: png, jpg, jpeg.'),
            'footerPhoto.max' => __('The footer image may not be greater than :max kilobytes.'),
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Only check for create operation
            if ($this->method() === 'POST') {
                $headerPhoto = $this->file('headerPhoto');
                $footerPhoto = $this->file('footerPhoto');
                
                if (!$headerPhoto && !$footerPhoto) {
                    $validator->errors()->add('headerPhoto', __('Either header image or footer image is required.'));
                    $validator->errors()->add('footerPhoto', __('Either header image or footer image is required.'));
                }
            }
        });
    }
}