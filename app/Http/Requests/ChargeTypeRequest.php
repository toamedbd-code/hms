<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChargeTypeRequest extends FormRequest
{
 public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'modules' => 'required',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required|string|max:255',
                    'modules' => 'required',
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
            'modules.required' => __('The module field is required.'),
        ];
    }
}