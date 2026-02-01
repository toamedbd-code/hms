<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RadiologyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'case_id' => 'nullable|string|max:255',
                    'patient_id' => 'required|exists:patients,id',
                    'referral_doctor_id' => 'nullable|exists:admins,id',
                    'doctor_name' => 'nullable|string|max:255',
                    'note' => 'nullable|string',

                    // Test validation
                    'tests' => 'required|array|min:1',
                    'tests.*.testId' => 'required|exists:tests,id',
                    'tests.*.testName' => 'nullable|string',
                    'tests.*.reportDays' => 'nullable|integer|min:0',
                    'tests.*.reportDate' => 'nullable|date',
                    'tests.*.tax' => 'nullable|numeric|min:0|max:100',
                    'tests.*.amount' => 'required|numeric|min:0',

                    // Financial validation
                    'total_amount' => 'required|numeric|min:0',
                    'tax_amount' => 'required|numeric|min:0',
                    'discount_amount' => 'nullable|numeric|min:0',
                    'discount_percentage' => 'nullable|numeric|min:0|max:100',
                    'net_amount' => 'required|numeric|min:0',

                    // Payment validation
                    'payment_mode' => 'required|in:Cash,Card,Bank Transfer,Mobile Banking',
                    'payment_amount' => 'required|numeric|min:0',
                ];

            case 'PUT':
            case 'PATCH':
                return [
                    'case_id' => 'nullable|string|max:255',
                    'patient_id' => 'required|exists:patients,id',
                    'referral_doctor_id' => 'nullable|exists:admins,id',
                    'doctor_name' => 'nullable|string|max:255',
                    'note' => 'nullable|string',

                    // Test validation
                    'tests' => 'required|array|min:1',
                    'tests.*.testId' => 'required|exists:tests,id',
                    'tests.*.testName' => 'nullable|string',
                    'tests.*.reportDays' => 'nullable|integer|min:0',
                    'tests.*.reportDate' => 'nullable|date',
                    'tests.*.tax' => 'nullable|numeric|min:0|max:100',
                    'tests.*.amount' => 'required|numeric|min:0',

                    // Financial validation
                    'total_amount' => 'required|numeric|min:0',
                    'tax_amount' => 'required|numeric|min:0',
                    'discount_amount' => 'nullable|numeric|min:0',
                    'discount_percentage' => 'nullable|numeric|min:0|max:100',
                    'net_amount' => 'required|numeric|min:0',

                    // Payment validation
                    'payment_mode' => 'required|in:Cash,Card,Bank Transfer,Mobile Banking',
                    'payment_amount' => 'required|numeric|min:0',
                ];

            default:
                return [];
        }
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'patient_id.required' => 'Patient selection is required.',
            'patient_id.exists' => 'Selected patient does not exist.',
            
            'referral_doctor_id.exists' => 'Selected referral doctor does not exist.',
            
            'tests.required' => 'At least one test must be selected.',
            'tests.min' => 'At least one test must be selected.',
            'tests.*.testId.required' => 'Test selection is required for each row.',
            'tests.*.testId.exists' => 'Selected test does not exist.',
            'tests.*.amount.required' => 'Amount is required for each test.',
            'tests.*.amount.numeric' => 'Test amount must be a valid number.',
            'tests.*.amount.min' => 'Test amount must be greater than 0.',
            'tests.*.tax.numeric' => 'Tax percentage must be a valid number.',
            'tests.*.tax.max' => 'Tax percentage cannot exceed 100%.',
            'tests.*.reportDays.integer' => 'Report days must be a valid number.',
            'tests.*.reportDate.date' => 'Report date must be a valid date.',
            
            'total_amount.required' => 'Total amount is required.',
            'total_amount.numeric' => 'Total amount must be a valid number.',
            'total_amount.min' => 'Total amount must be greater than or equal to 0.',
            
            'tax_amount.required' => 'Tax amount is required.',
            'tax_amount.numeric' => 'Tax amount must be a valid number.',
            'tax_amount.min' => 'Tax amount must be greater than or equal to 0.',
            
            'discount_amount.numeric' => 'Discount amount must be a valid number.',
            'discount_amount.min' => 'Discount amount must be greater than or equal to 0.',
            
            'discount_percentage.numeric' => 'Discount percentage must be a valid number.',
            'discount_percentage.min' => 'Discount percentage must be greater than or equal to 0.',
            'discount_percentage.max' => 'Discount percentage cannot exceed 100%.',
            
            'net_amount.required' => 'Net amount is required.',
            'net_amount.numeric' => 'Net amount must be a valid number.',
            'net_amount.min' => 'Net amount must be greater than or equal to 0.',
            
            'payment_mode.required' => 'Payment mode is required.',
            'payment_mode.in' => 'Invalid payment mode selected. Please choose from: Cash, Card, Bank Transfer, or Mobile Banking.',
            
            'payment_amount.required' => 'Payment amount is required.',
            'payment_amount.numeric' => 'Payment amount must be a valid number.',
            'payment_amount.min' => 'Payment amount must be greater than or equal to 0.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Ensure numeric fields are properly formatted
        $this->merge([
            'total_amount' => (float) ($this->total_amount ?? 0),
            'tax_amount' => (float) ($this->tax_amount ?? 0),
            'discount_amount' => (float) ($this->discount_amount ?? 0),
            'discount_percentage' => (float) ($this->discount_percentage ?? 0),
            'net_amount' => (float) ($this->net_amount ?? 0),
            'payment_amount' => (float) ($this->payment_amount ?? 0),
        ]);

        // Ensure tests array has proper numeric values
        if ($this->has('tests') && is_array($this->tests)) {
            $tests = [];
            foreach ($this->tests as $test) {
                $tests[] = [
                    'testId' => $test['testId'] ?? null,
                    'testName' => $test['testName'] ?? null,
                    'reportDays' => (int) ($test['reportDays'] ?? 0),
                    'reportDate' => $test['reportDate'] ?? null,
                    'tax' => (float) ($test['tax'] ?? 0),
                    'amount' => (float) ($test['amount'] ?? 0),
                ];
            }
            $this->merge(['tests' => $tests]);
        }
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Custom validation: ensure discount percentage and amount are not both set
            if ($this->discount_percentage > 0 && $this->discount_amount > 0) {
                $validator->errors()->add('discount_amount', 'Cannot set both discount percentage and discount amount. Please use only one.');
            }

            // Custom validation: ensure payment amount doesn't exceed net amount by too much
            if ($this->payment_amount > ($this->net_amount * 1.1)) { // Allow 10% overpayment for change
                $validator->errors()->add('payment_amount', 'Payment amount seems too high compared to net amount.');
            }

            // Custom validation: ensure at least one test has valid amount
            if ($this->has('tests') && is_array($this->tests)) {
                $hasValidTest = false;
                foreach ($this->tests as $test) {
                    if (isset($test['testId']) && isset($test['amount']) && $test['amount'] > 0) {
                        $hasValidTest = true;
                        break;
                    }
                }
                if (!$hasValidTest) {
                    $validator->errors()->add('tests', 'At least one test must have a valid amount greater than 0.');
                }
            }
        });
    }
}