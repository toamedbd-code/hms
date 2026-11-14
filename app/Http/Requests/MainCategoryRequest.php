<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MainCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('maincategory');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('main_categories', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
        ];
    }
}
