<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicineInventoryRequest extends FormRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'supplier_id' => 'required',
                    'medicine_category_id' => 'required',
                    'medicines' => 'required|array|min:1',
                    'medicines.*.medicine_name' => 'required|string|max:255',
                    'medicines.*.medicine_unit_purchase_price' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
                    'medicines.*.medicine_unit_selling_price' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
                    'medicines.*.medicine_total_purchase_price' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
                    'medicines.*.medicine_total_selling_price' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
                    'medicines.*.medicine_quantity' => 'required|integer|min:1',
                    'medicines.*.remarks' => 'nullable|string|max:500',
                ];
                break;

            case 'PUT':
                return [
                    'supplier_id' => 'required',
                    'medicine_category_id' => 'required',
                    'medicine_name' => 'required|string|max:255',
                    'medicine_unit_purchase_price' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
                    'medicine_unit_selling_price' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
                    'medicine_total_purchase_price' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
                    'medicine_total_selling_price' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
                    'medicine_quantity' => 'required|integer|min:1',
                    'remarks' => 'nullable|string|max:500',
                ];
                break;
            case 'PATCH':
                // Add PATCH rules if needed
                break;
        }
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, mixed>
     */
    public function messages()
    {
        return [
            'supplier_id.required' => __('The supplier field is required.'),
            'supplier_id.exists' => __('The selected supplier is invalid.'),
            'medicine_category_id.required' => __('The medicine category field is required.'),
            'medicine_category_id.exists' => __('The selected medicine category is invalid.'),
            'medicines.required' => __('At least one medicine is required.'),
            'medicines.min' => __('At least one medicine must be added.'),
            
            // Multiple medicines validation messages
            'medicines.*.medicine_name.required' => __('Medicine name is required for all medicines.'),
            'medicines.*.medicine_name.string' => __('Medicine name must be a valid string.'),
            'medicines.*.medicine_name.max' => __('Medicine name cannot exceed 255 characters.'),
            
            'medicines.*.medicine_unit_purchase_price.required' => __('Unit purchase price is required for all medicines.'),
            'medicines.*.medicine_unit_purchase_price.numeric' => __('Unit purchase price must be a valid number.'),
            'medicines.*.medicine_unit_purchase_price.min' => __('Unit purchase price cannot be negative.'),
            'medicines.*.medicine_unit_purchase_price.regex' => __('Unit purchase price format is invalid. Use format: 999.99'),
            
            'medicines.*.medicine_unit_selling_price.required' => __('Unit selling price is required for all medicines.'),
            'medicines.*.medicine_unit_selling_price.numeric' => __('Unit selling price must be a valid number.'),
            'medicines.*.medicine_unit_selling_price.min' => __('Unit selling price cannot be negative.'),
            'medicines.*.medicine_unit_selling_price.regex' => __('Unit selling price format is invalid. Use format: 999.99'),
            
            'medicines.*.medicine_total_purchase_price.required' => __('Total purchase price is required for all medicines.'),
            'medicines.*.medicine_total_purchase_price.numeric' => __('Total purchase price must be a valid number.'),
            'medicines.*.medicine_total_purchase_price.min' => __('Total purchase price cannot be negative.'),
            'medicines.*.medicine_total_purchase_price.regex' => __('Total purchase price format is invalid. Use format: 999.99'),
            
            'medicines.*.medicine_total_selling_price.required' => __('Total selling price is required for all medicines.'),
            'medicines.*.medicine_total_selling_price.numeric' => __('Total selling price must be a valid number.'),
            'medicines.*.medicine_total_selling_price.min' => __('Total selling price cannot be negative.'),
            'medicines.*.medicine_total_selling_price.regex' => __('Total selling price format is invalid. Use format: 999.99'),
            
            'medicines.*.medicine_quantity.required' => __('Quantity is required for all medicines.'),
            'medicines.*.medicine_quantity.integer' => __('Quantity must be a valid integer.'),
            'medicines.*.medicine_quantity.min' => __('Quantity must be at least 1.'),
            'medicines.*.remarks.string' => __('Remarks must be a valid string.'),
            'medicines.*.remarks.max' => __('Remarks cannot exceed 500 characters.'),

            // For single medicine update (PUT request)
            'medicine_name.required' => __('The medicine name field is required.'),
            'medicine_unit_purchase_price.required' => __('The unit purchase price field is required.'),
            'medicine_unit_selling_price.required' => __('The unit selling price field is required.'),
            'medicine_total_purchase_price.required' => __('The total purchase price field is required.'),
            'medicine_total_selling_price.required' => __('The total selling price field is required.'),
            'medicine_quantity.required' => __('The quantity field is required.'),
        ];
    }
}