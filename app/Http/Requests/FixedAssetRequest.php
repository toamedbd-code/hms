<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FixedAssetRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $isCreate = $this->isMethod('post');

        return [
            'asset_tag' => ['nullable', 'string', 'max:191'],
            'name' => $isCreate ? ['required', 'string', 'max:255'] : ['sometimes', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer'],
            'purchase_date' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric'],
            'salvage_value' => ['nullable', 'numeric'],
            'useful_life_months' => ['nullable', 'integer'],
            'depreciation_method' => ['nullable', 'string'],
        ];
    }
}
