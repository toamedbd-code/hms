<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExchangeRateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'from_currency_id' => ['required', 'integer'],
            'to_currency_id' => ['required', 'integer'],
            'rate' => ['required', 'numeric'],
            'date' => ['required', 'date'],
        ];
    }
}
