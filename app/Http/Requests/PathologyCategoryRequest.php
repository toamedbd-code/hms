<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PathologyCategoryRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'name' => ['required', 'string', 'max:255', Rule::unique('testcategories', 'name')->whereNull('deleted_at')],
                    'parent_id' => 'nullable|integer|exists:testcategories,id',
                    'is_main_category' => 'nullable|boolean',
                ];
                break;

            case 'PUT':
                return [
                    'name' => ['required', 'string', 'max:255', Rule::unique('testcategories', 'name')->whereNull('deleted_at')->ignore($this->route('pathologycategory'))],
                    'parent_id' => 'nullable|integer|exists:testcategories,id',
                    'is_main_category' => 'nullable|boolean',
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
