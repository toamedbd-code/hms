<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChargeTaxCategoryRequest extends FormRequest
{
 public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'percentage' => 'required',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required|string|max:255',
                    'percentage' => 'required',
                ];
                break;
            case 'PATCH':

                break;
        }
    }
    
    public function messages()
    {

        return [
            'name.required' => __('The name field is required.'),
            'percentage.required' => __('The percentage field is required.'),
        ];
    }
}