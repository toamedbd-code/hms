<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillOfMaterialRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $isCreate = $this->isMethod('post');

        return [
            'code' => ['nullable', 'string', 'max:191'],
            'product_id' => ['nullable', 'integer'],
            'name' => $isCreate ? ['required', 'string', 'max:255'] : ['sometimes', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric'],
            'unit_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.component_id' => ['required_with:items', 'integer'],
            'items.*.quantity' => ['nullable', 'numeric'],
        ];
    }
}
