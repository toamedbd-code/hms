<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChargeCategoryRequest extends FormRequest
{
 public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'charge_type_id' => 'required',
                    'name' => 'required|string|max:255',
                    'description' => 'required|string|max:255',
                    
                ];
                break;

            case 'PUT':
                return [
                    'charge_type_id' => 'required',
                    'name' => 'required|string|max:255',
                    'description' => 'required|string|max:255',
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
            'charge_type_id.required' => __('The charge type field is required.'),
            'name.required' => __('The name field is required.'),
            'description.required' => __('The description field is required.'),
            'email.unique' => __('This email address is already taken.'),
            'photo.file' => __('The photo must be a file.'),
            'photo.mimes' => __('The photo must be a file of type: png, jpg, jpeg.'),
            'photo.max' => __('The photo may not be greater than :max kilobytes.'),
        ];
    }
}