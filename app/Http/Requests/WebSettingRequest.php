<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebSettingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'company_name' => 'required|string|max:255',
                    'company_short_name' => 'required|string|max:100',
                    'phone' => 'required|string|max:255',
                    'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                    'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,ico|max:1024',
                    'report_title' => 'required|string|max:255',
                ];
                break;

            case 'PATCH':
            case 'PUT':
                return [
                    'company_name' => 'required|string|max:255',
                    'company_short_name' => 'required|string|max:100',
                    'phone' => 'required|string|max:255',
                    'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                    'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,ico|max:1024',
                    'report_title' => 'required|string|max:255',
                ];
                break;
        }
    }

    public function messages()
    {
        return [
            'company_name.required' => 'The company name field is required.',
            'company_name.string' => 'The company name must be a string.',
            'company_name.max' => 'The company name may not be greater than 255 characters.',
            
            'company_short_name.required' => 'The company short name field is required.',
            'company_short_name.string' => 'The company short name must be a string.',
            'company_short_name.max' => 'The company short name may not be greater than 100 characters.',
            
            'phone.required' => 'The phone field is required.',
            'phone.string' => 'The phone must be a string.',
            'phone.max' => 'The phone may not be greater than 255 characters.',
            
            'logo.image' => 'The logo must be an image.',
            'logo.mimes' => 'The logo must be a file of type: jpeg, png, jpg, gif, svg.',
            'logo.max' => 'The logo may not be greater than 2MB.',
            
            'icon.image' => 'The icon must be an image.',
            'icon.mimes' => 'The icon must be a file of type: jpeg, png, jpg, gif, svg, ico.',
            'icon.max' => 'The icon may not be greater than 1MB.',
            
            'report_title.required' => 'The report title field is required.',
            'report_title.string' => 'The report title must be a string.',
            'report_title.max' => 'The report title may not be greater than 255 characters.',
        ];
    }
}