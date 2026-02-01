<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BedGroupRequest extends FormRequest
{
 public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'floor_id' => 'required',
                    'description' => 'nullable',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required|string|max:255',
                    'floor_id' => 'required',
                    'description' => 'nullable',
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
            'floor_id.required' => __('The floor field is required.'),
        ];
    }
}