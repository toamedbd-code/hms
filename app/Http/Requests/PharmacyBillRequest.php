<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PharmacyBillRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'pharmacy_no' => 'required|string|max:255',
            'bill_no' => 'required|string|max:255',
            'case_id' => 'nullable|string|max:255',
            'date' => 'required|date',
            'patient_id' => 'nullable|exists:patients,id',
            'doctor_id' => 'nullable|exists:admins,id',
            'products' => 'required|array',
            'products.*.productId' => 'required',
            'products.*.productName' => 'required|string',
            'products.*.medicineCategory' => 'required|string',
            'products.*.batchNo' => 'nullable|string',
            'products.*.expiryDate' => 'nullable|date',
            'products.*.quantity' => 'required|numeric|min:1',
            'products.*.availableQty' => 'nullable|numeric',
            'products.*.rate' => 'required|numeric|min:0',
            'products.*.tax' => 'nullable|numeric|min:0|max:100',
            'products.*.amount' => 'required|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'vat_percentage' => 'nullable|numeric|min:0|max:100',
            'vat_amount' => 'nullable|numeric|min:0',
            'extra_discount' => 'nullable|numeric|min:0',
            'net_amount' => 'required|numeric|min:0',
            'payment_mode' => 'required|in:Cash,Card,Bank Transfer',
            'payment_amount' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ];

        if ($this->route('id')) {
            $id = $this->route('id');
            $rules['pharmacy_no'] = 'required';
            $rules['bill_no'] = 'required';
        } else {
            $rules['pharmacy_no'] = 'required';
            $rules['bill_no'] = 'required';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'products.required' => 'At least one medicine product is required.',
            'products.*.productId.required' => 'Medicine selection is required.',
            'products.*.productId.exists' => 'Selected medicine does not exist.',
            'products.*.quantity.required' => 'Quantity is required.',
            'products.*.quantity.min' => 'Quantity must be at least 1.',
            'products.*.rate.required' => 'Sale price is required.',
            'products.*.rate.min' => 'Sale price must be positive.',
            'products.*.amount.required' => 'Amount is required.',
            'products.*.amount.min' => 'Amount must be positive.',
        ];
    }

    protected function prepareForValidation()
    {
        // Ensure numeric fields are properly cast
        $this->merge([
            'subtotal' => (float) $this->subtotal,
            'discount_percentage' => (float) $this->discount_percentage,
            'discount_amount' => (float) $this->discount_amount,
            'vat_percentage' => (float) $this->vat_percentage,
            'vat_amount' => (float) $this->vat_amount,
            'extra_discount' => (float) $this->extra_discount,
            'net_amount' => (float) $this->net_amount,
            'payment_amount' => (float) $this->payment_amount,
        ]);
    }
}