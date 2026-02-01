<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PathologyCategoryRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => 'required|string|max:255',
                    'parent_id' => 'nullable',
                ];
                break;

            case 'PUT':
                return [
                    'name' => 'required|string|max:255',
                    'parent_id' => 'nullable',
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
        ];
    }
}
