<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicineDosageRequest extends FormRequest
{
 public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'medicine_category_id' => 'required',
                    'dose' => 'required',
                    'medicine_unit_id' => 'required',
                ];
                break;

            case 'PUT':
                return [
                    'medicine_category_id' => 'required',
                    'dose' => 'required',
                    'medicine_unit_id' => 'required',
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
            'medicine_category_id.required' => __('The medicine category name field is required.'),
            'dose.required' => __('The dose field is required.'),
            'medicine_unit_id.required' => __('The unit field is required.'),

        ];
    }
}