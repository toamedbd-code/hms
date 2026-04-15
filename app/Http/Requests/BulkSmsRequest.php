<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('admin')->check();
    }

    public function rules(): array
    {
        return [
            'recipient_scope' => 'required|in:all_active,selected',
            'patient_ids' => 'nullable|required_if:recipient_scope,selected|string|max:20000',
            'message' => 'required|string|min:2|max:1000',
        ];
    }
}
