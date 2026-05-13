<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CurrencyRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $isCreate = $this->isMethod('post');
        return [
            'code' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:10'],
            'name' => ['nullable', 'string', 'max:191'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'is_base' => ['nullable', 'boolean'],
        ];
    }
}
