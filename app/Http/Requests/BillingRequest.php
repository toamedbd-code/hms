<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'patient_id' => 'nullable|integer',
            'ipd_patient_id' => 'nullable|integer|exists:ipdpatients,id',
            'ipd_prescription_id' => 'nullable|integer|exists:ipd_prescriptions,id',
            'doctor_id' => 'nullable',
            'doctor_type' => 'nullable',
            'doctor_name' => 'nullable',
            'patient_mobile' => 'nullable|string|max:20',
            'gender' => 'nullable|in:Male,Female,Others',

            'patient_name' => 'nullable|string|max:255',
            'patient_phone' => 'nullable|string|max:20',
            'patient_gender' => 'nullable|in:Male,Female,Others',
            'dob' => 'nullable',
            'patient_age' => 'nullable',

            'card_type' => 'required|string|max:50',
            'pay_mode' => 'required|string|in:Cash,Card,Bank,Mobile Banking,Bkash,Nagad',
            'card_number' => 'nullable|string|max:50|required_unless:pay_mode,Cash',

            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.department_id' => 'nullable|integer|exists:departments,id',
            'items.*.name' => 'required|string|max:255',
            'items.*.category' => 'required|string|max:100',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.total_amount' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_exempt' => 'nullable|boolean',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_rate_locked' => 'nullable|boolean',
            'items.*.rugound' => 'nullable|numeric',
            'items.*.net_amount' => 'required|numeric|min:0',
            'items.*.referral_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.room_no' => 'nullable|string|max:50',

            'total' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'required|in:percentage,flat',
            'discount_allocation' => 'nullable|in:equal,proportional',
            'payable_amount' => 'required|numeric|min:0',
            'paid_amt' => 'required|numeric|min:0',
            'change_amt' => 'nullable|numeric',
            'receiving_amt' => 'nullable|numeric|min:0',
            'return_amt' => 'nullable|numeric|min:0',
            'due_amount' => 'nullable|numeric|min:0',
            'extra_flat_discount' => 'nullable|numeric|min:0',

            'delivery_date' => 'nullable',
            'delivery_time' => 'nullable|string|max:10',
            // Allow frontend to provide backdated billing datetime
            'billing_date' => 'nullable|date_format:Y-m-d',
            'billing_time' => 'nullable|date_format:H:i:s',
            'remarks' => 'nullable|string|max:1000',

            'commission_total' => 'nullable|numeric|min:0',
            'physyst_amt' => 'nullable|numeric|min:0',
            'commission_slider' => 'nullable|numeric|min:0|max:100',
            'referrer_id' => 'nullable|integer',
            'doctor_id' => 'nullable|integer',
            'doctor_type' => 'nullable|string|max:50',
            'admin_id' => 'nullable|integer',
            'created_by' => 'nullable|integer',
            'updated_by' => 'nullable|integer',
            
            // Optional fields for API flow
            'print_token' => 'nullable|string|max:255',
            'backend_invoice' => 'nullable|boolean',
            'pharmacy_mode' => 'nullable|boolean',
        ];

        $rules['is_new_patient'] = 'sometimes|boolean';

        if ($this->input('is_new_patient', false) && !$this->input('patient_id')) {
            $rules['patient_name'] = 'required|string|max:255';
            $rules['patient_phone'] = 'required|string|max:20';
            $rules['patient_gender'] = 'required|in:Male,Female,Others';
        }

        if (!$this->input('is_new_patient', false) && $this->input('patient_id')) {
            $rules['patient_id'] = 'required|integer';
        }

        if ($this->input('discount_type') === 'percentage') {
            $rules['discount'] = 'nullable|numeric|min:0|max:100';
        }

        if ($this->input('pay_mode') !== 'Cash') {
            $rules['card_number'] = 'required|string|max:50';
        }

        return $rules;
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Patient Details
            'patient_id.integer' => 'The selected patient is invalid.',
            'patient_id.required' => 'Patient ID is required when selecting an existing patient.',
            'doctor_id.integer' => 'The selected doctor is invalid.',
            'patient_mobile.max' => 'Patient mobile number cannot exceed 20 characters.',
            'gender.in' => 'Please select a valid gender (Male, Female, or other).',

            // New Patient Fields
            'patient_name.required' => 'Patient name is required when creating a new patient.',
            'patient_name.max' => 'Patient name cannot exceed 255 characters.',
            'patient_phone.required' => 'Patient phone is required when creating a new patient.',
            'patient_phone.max' => 'Patient phone cannot exceed 20 characters.',
            'patient_gender.required' => 'Patient gender is required when creating a new patient.',
            'patient_gender.in' => 'Please select a valid gender for the new patient.',

            // Rest of your existing messages...
            'card_type.required' => 'Card type is required.',
            'pay_mode.required' => 'Payment mode is required.',
            'pay_mode.in' => 'Payment mode must be Cash, Card, Bank, Bkash, or Nagad.',
            'card_number.required_unless' => 'Card/Account number is required for non-cash payments.',

            // Items Validation
            'items.required' => 'Please add at least one item to the bill.',
            'items.min' => 'At least one item is required.',
            'items.*.id.required' => 'Item ID is required for each item.',
            'items.*.id.integer' => 'Item ID must be a valid number.',
            'items.*.name.required' => 'Item name is required for each item.',
            'items.*.category.required' => 'Item category is required.',
            'items.*.unit_price.required' => 'Unit price is required for each item.',
            'items.*.unit_price.numeric' => 'Unit price must be a valid number.',
            'items.*.unit_price.min' => 'Unit price cannot be negative.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.numeric' => 'Quantity must be a valid number.',
            'items.*.quantity.min' => 'Quantity must be greater than 0.',
            'items.*.total_amount.required' => 'Total amount is required for each item.',
            'items.*.total_amount.numeric' => 'Total amount must be a valid number.',
            'items.*.total_amount.min' => 'Total amount cannot be negative.',
            'items.*.discount.numeric' => 'Discount must be a valid number.',
            'items.*.discount.min' => 'Discount cannot be negative.',
            'items.*.net_amount.required' => 'Net amount is required for each item.',
            'items.*.net_amount.numeric' => 'Net amount must be a valid number.',
            'items.*.net_amount.min' => 'Net amount cannot be negative.',

            // Financial Summary
            'total.required' => 'Total amount is required.',
            'total.numeric' => 'Total amount must be a valid number.',
            'total.min' => 'Total amount cannot be negative.',
            'discount.numeric' => 'Discount must be a valid number.',
            'discount.min' => 'Discount cannot be negative.',
            'discount.max' => 'Discount percentage cannot exceed 100%.',
            'discount_type.required' => 'Discount type is required.',
            'discount_type.in' => 'Discount type must be either percentage or flat amount.',
            'payable_amount.required' => 'Payable amount is required.',
            'payable_amount.numeric' => 'Payable amount must be a valid number.',
            'payable_amount.min' => 'Payable amount cannot be negative.',
            'paid_amt.required' => 'Paid amount is required.',
            'paid_amt.numeric' => 'Paid amount must be a valid number.',
            'paid_amt.min' => 'Paid amount cannot be negative.',
            'change_amt.numeric' => 'Change amount must be a valid number.',
            'receiving_amt.numeric' => 'Receiving amount must be a valid number.',
            'receiving_amt.min' => 'Receiving amount cannot be negative.',
            'return_amt.numeric' => 'Return amount must be a valid number.',
            'return_amt.min' => 'Return amount cannot be negative.',
            'due_amount.numeric' => 'Due amount must be a valid number.',
            'due_amount.min' => 'Due amount cannot be negative.',
            'extra_flat_discount.numeric' => 'Extra flat discount must be a valid number.',
            'extra_flat_discount.min' => 'Extra flat discount cannot be negative.',

            // Commission
            'commission_total.numeric' => 'Commission total must be a valid number.',
            'commission_total.min' => 'Commission total cannot be negative.',
            'physyst_amt.numeric' => 'Physyst amount must be a valid number.',
            'physyst_amt.min' => 'Physyst amount cannot be negative.',
            'commission_slider.numeric' => 'Commission percentage must be a valid number.',
            'commission_slider.min' => 'Commission percentage cannot be negative.',
            'commission_slider.max' => 'Commission percentage cannot exceed 100%.',
            'referrer_id.integer' => 'The selected referrer is invalid.',

            // Optional Fields
            'delivery_date.max' => 'Delivery date cannot exceed 255 characters.',
            'remarks.max' => 'Remarks cannot exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $isNewPatient = $this->input('is_new_patient', false);
            $patientId = $this->input('patient_id');
            $patientName = $this->input('patient_name');
            $patientPhone = $this->input('patient_phone');
            $patientGender = $this->input('patient_gender');

            if ($isNewPatient && !$patientId) {
                if (!$patientName) {
                    $validator->errors()->add('patient_name', 'Patient name is required for new patients.');
                }
                if (!$patientPhone) {
                    $validator->errors()->add('patient_phone', 'Patient phone is required for new patients.');
                }
                if (!$patientGender) {
                    $validator->errors()->add('patient_gender', 'Patient gender is required for new patients.');
                }
            }

            if (!$isNewPatient && $patientId && !is_numeric($patientId)) {
                $validator->errors()->add('patient_id', 'Valid patient ID is required when selecting existing patient.');
            }

            $total = (float) $this->input('total', 0);
            if ($total <= 0) {
                return;
            }

            $maxDiscountPercent = $this->resolveMaxBillingDiscountPercent();
            $discountType = (string) $this->input('discount_type', 'percentage');
            $discountValue = (float) $this->input('discount', 0);
            $extraFlatDiscount = (float) $this->input('extra_flat_discount', 0);
            $items = $this->input('items', []);
            $itemDiscountCap = 0.0;

            if (is_array($items)) {
                foreach ($items as $item) {
                    if (!is_array($item) || ($item['discount_exempt'] ?? false)) {
                        continue;
                    }

                    $category = strtolower(trim((string) ($item['category'] ?? '')));
                    if (str_contains($category, 'dispos')) {
                        continue;
                    }

                    $itemTotal = max(0, (float) ($item['total_amount'] ?? 0));
                    $itemPercent = min(100, max(0, (float) ($item['discount_percentage'] ?? 0)));
                    $itemIsLocked = (bool) ($item['discount_rate_locked'] ?? false);
                    if ($itemIsLocked && $itemPercent <= 0) {
                        continue;
                    }
                    $effectivePercent = $itemIsLocked
                        ? $itemPercent
                        : ($itemPercent > 0 ? $itemPercent : $maxDiscountPercent);

                    $itemDiscountCap += ($itemTotal * $effectivePercent) / 100;
                }
            }

            $discountAmount = $discountType === 'percentage'
                ? ($total * $discountValue) / 100
                : min($discountValue, $itemDiscountCap);

            $totalDiscountAmount = max(0, $discountAmount) + max(0, $extraFlatDiscount);
            $appliedDiscountPercent = ($totalDiscountAmount / $total) * 100;

            if ($appliedDiscountPercent > $maxDiscountPercent) {
                $formattedMaxPercent = rtrim(rtrim(number_format($maxDiscountPercent, 2, '.', ''), '0'), '.');
                $validator->errors()->add(
                    'discount',
                    "Total discount cannot exceed {$formattedMaxPercent}% of total bill amount."
                );
            }

            $categoryCapsEnabled = $this->resolveCategoryDiscountCapEnabled();
            if ($categoryCapsEnabled && $discountType === 'percentage' && $discountValue > 0) {
                $items = $this->input('items', []);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (!is_array($item)) {
                            continue;
                        }

                        if ((bool) ($item['discount_exempt'] ?? false)) {
                            continue;
                        }

                        if ((bool) ($item['discount_rate_locked'] ?? false)
                            && (float) ($item['discount_percentage'] ?? 0) <= 0) {
                            continue;
                        }

                        if ((float) ($item['discount_percentage'] ?? 0) > 0) {
                            continue;
                        }

                        // Category caps are applied per item during billing calculation.
                        // Do not reject the whole mixed-category bill based on its lowest cap.
                    }
                }
            }
        });
    }

    private function resolveMaxBillingDiscountPercent(): float
    {
        $default = 100.0;

        if (!function_exists('get_cached_web_setting')) {
            return $default;
        }

        $setting = get_cached_web_setting();
        $configured = (float) ($setting?->max_billing_discount_percent ?? $default);

        return max(0, min(100, $configured));
    }

    private function resolveCategoryDiscountCapEnabled(): bool
    {
        if (!function_exists('get_cached_web_setting')) {
            return false;
        }

        $setting = get_cached_web_setting();
        $attendanceOptions = $setting?->attendance_device_options ?? [];

        if (is_string($attendanceOptions)) {
            $attendanceOptions = json_decode($attendanceOptions, true);
        }

        if (!is_array($attendanceOptions)) {
            return false;
        }

        return (bool) ($attendanceOptions['billing_discount_cap_by_category_enabled'] ?? false);
    }

    private function resolveCategoryDiscountCap(string $category): ?float
    {
        if (!function_exists('get_cached_web_setting')) {
            return null;
        }

        $setting = get_cached_web_setting();
        $attendanceOptions = $setting?->attendance_device_options ?? [];

        if (is_string($attendanceOptions)) {
            $attendanceOptions = json_decode($attendanceOptions, true);
        }

        if (!is_array($attendanceOptions)) {
            return null;
        }

        $caps = $attendanceOptions['billing_category_discount_caps'] ?? [];
        if (!is_array($caps)) {
            return null;
        }

        $normalizedCategory = trim($category);
        if ($normalizedCategory === '') {
            return null;
        }

        foreach ([$normalizedCategory, strtolower($normalizedCategory), ucfirst(strtolower($normalizedCategory))] as $candidate) {
            if (array_key_exists($candidate, $caps)) {
                $value = (float) $caps[$candidate];
                if (is_finite($value)) {
                    return max(0, min(100, $value));
                }
            }
        }

        foreach ($caps as $key => $value) {
            if (strcasecmp((string) $key, $normalizedCategory) === 0) {
                $numeric = (float) $value;
                if (is_finite($numeric)) {
                    return max(0, min(100, $numeric));
                }
            }
        }

        return null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'is_new_patient' => $this->input('is_new_patient', false),
            'paid_amt' => (float) $this->input('paid_amt', 0),
            'payable_amount' => (float) $this->input('payable_amount', 0),
            'change_amt' => (float) $this->input('change_amt', 0),
            'receiving_amt' => (float) $this->input('receiving_amt', 0),
            'return_amt' => (float) $this->input('return_amt', 0),
            'due_amount' => (float) $this->input('due_amount', 0),
        ]);
    }
}