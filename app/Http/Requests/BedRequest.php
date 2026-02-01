<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BedRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'bed_type_id' => 'required|exists:bedtypes,id',
                    'bed_group_id' => 'required|exists:bedgroups,id',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required|string|max:255',
                    'bed_type_id' => 'required|exists:bedtypes,id',
                    'bed_group_id' => 'required|exists:bedgroups,id',
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
            'name.required' => 'The bed name is required',
            'name.string' => 'The bed name must be a valid text',
            'name.max' => 'The bed name cannot exceed 255 characters',

            'bed_type_id.required' => 'Please select a bed type',
            'bed_type_id.exists' => 'The selected bed type is invalid',

            'bed_group_id.required' => 'Please select a bed group',
            'bed_group_id.exists' => 'The selected bed group is invalid',
        ];
    }
}
