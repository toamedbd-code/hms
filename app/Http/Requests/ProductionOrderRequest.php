<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'bom_id' => ['required', 'integer'],
            'qty_planned' => ['required', 'integer', 'min:1'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
