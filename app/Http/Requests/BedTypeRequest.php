<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BedTypeRequest extends FormRequest
{
 public function rules()
    {
        switch ($this->method()) {
                        case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'room_rent_rate_per_day' => 'nullable|numeric|min:0',
                    'bed_charge_rate_per_day' => 'nullable|numeric|min:0',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required|string|max:255',
                    'room_rent_rate_per_day' => 'nullable|numeric|min:0',
                    'bed_charge_rate_per_day' => 'nullable|numeric|min:0',
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
            'room_rent_rate_per_day.numeric' => __('Room rent must be a number.'),
            'bed_charge_rate_per_day.numeric' => __('Bed charge must be a number.'),
        ];

    }
}