<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReferralRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'billing_id' => 'required|exists:billings,id',
            'payee_id' => 'required|exists:referralpeople,id',
            'date' => 'required|date',
            'status' => 'sometimes|in:Active,Inactive,Deleted',
            'remarks' => 'nullable|string|max:1000',
        ];

        return $rules;
    }

    public function messages()
    {
        return [
            'billing_id.required' => 'The bill number is required',
            'billing_id.exists' => 'The selected bill number is invalid',
            'payee_id.required' => 'The payee is required',
            'payee_id.exists' => 'The selected payee is invalid',
            'date.required' => 'Date is required',
            'date.date' => 'Please enter a valid date',
        ];
    }

    protected function prepareForValidation()
    {
        // Handle multiselect object format
        if (is_array($this->billing_id) && isset($this->billing_id['id'])) {
            $this->merge(['billing_id' => $this->billing_id['id']]);
        }
        
        if (is_array($this->payee_id) && isset($this->payee_id['id'])) {
            $this->merge(['payee_id' => $this->payee_id['id']]);
        }
    }
}