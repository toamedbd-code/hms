<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'production_order_id' => ['required', 'integer'],
            'operation' => ['nullable', 'string'],
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date'],
        ];
    }
}
