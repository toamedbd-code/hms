<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class ReferralPersonRequest extends FormRequest
{
    public function rules()
    {
        $baseRules = [
            'name' => 'required|string|max:255',
            'phone' => 'required',
            'contact_person_name' => 'nullable|string|max:255',
            'contact_person_phone' => 'nullable|string|max:20',
            'category_id' => 'required',
            'address' => 'nullable|string',
            'standard_commission' => 'nullable',
            'opd_commission' => [
                'required_without_all:ipd_commission,pharmacy_commission,pathology_commission,radiology_commission,blood_bank_commission,ambulance_commission',
                'nullable',
                'numeric',
                'min:0.01',
                'max:100'
            ],
            'ipd_commission' => [
                'required_without_all:opd_commission,pharmacy_commission,pathology_commission,radiology_commission,blood_bank_commission,ambulance_commission',
                'nullable',
                'numeric',
                'min:0.01',
                'max:100'
            ],
            'pharmacy_commission' => [
                'required_without_all:opd_commission,ipd_commission,pathology_commission,radiology_commission,blood_bank_commission,ambulance_commission',
                'nullable',
                'numeric',
                'min:0.01',
                'max:100'
            ],
            'pathology_commission' => [
                'required_without_all:opd_commission,ipd_commission,pharmacy_commission,radiology_commission,blood_bank_commission,ambulance_commission',
                'nullable',
                'numeric',
                'min:0.01',
                'max:100'
            ],
            'radiology_commission' => [
                'required_without_all:opd_commission,ipd_commission,pharmacy_commission,pathology_commission,blood_bank_commission,ambulance_commission',
                'nullable',
                'numeric',
                'min:0.01',
                'max:100'
            ],
            'blood_bank_commission' => [
                'required_without_all:opd_commission,ipd_commission,pharmacy_commission,pathology_commission,radiology_commission,ambulance_commission',
                'nullable',
                'numeric',
                'min:0.01',
                'max:100'
            ],
            'ambulance_commission' => [
                'required_without_all:opd_commission,ipd_commission,pharmacy_commission,pathology_commission,radiology_commission,blood_bank_commission',
                'nullable',
                'numeric',
                'min:0.01',
                'max:100'
            ],
            'apply_to_all' => 'sometimes|boolean',
        ];

        switch ($this->method()) {
            case 'POST':
            case 'PUT':
                return $baseRules;
            case 'PATCH':
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
            'name.required' => __('The referrer name is required.'),
            'phone.required' => __('The referrer phone is required.'),
            'category_id.required' => __('Please select a category.'),
            'category_id.exists' => __('Selected category does not exist.'),
            
            // Commission required messages
            'standard_commission.required_without_all' => __('At least one commission field must be filled.'),
            'opd_commission.required_without_all' => __('At least one commission field must be filled.'),
            'ipd_commission.required_without_all' => __('At least one commission field must be filled.'),
            'pharmacy_commission.required_without_all' => __('At least one commission field must be filled.'),
            'pathology_commission.required_without_all' => __('At least one commission field must be filled.'),
            'radiology_commission.required_without_all' => __('At least one commission field must be filled.'),
            'blood_bank_commission.required_without_all' => __('At least one commission field must be filled.'),
            'ambulance_commission.required_without_all' => __('At least one commission field must be filled.'),
            
            // Commission numeric messages
            'standard_commission.numeric' => __('Standard commission must be a number.'),
            'standard_commission.min' => __('Standard commission must be at least 0.01.'),
            'opd_commission.numeric' => __('OPD commission must be a number.'),
            'opd_commission.min' => __('OPD commission must be at least 0.01.'),
            'ipd_commission.numeric' => __('IPD commission must be a number.'),
            'ipd_commission.min' => __('IPD commission must be at least 0.01.'),
            'pharmacy_commission.numeric' => __('Pharmacy commission must be a number.'),
            'pharmacy_commission.min' => __('Pharmacy commission must be at least 0.01.'),
            'pathology_commission.numeric' => __('Pathology commission must be a number.'),
            'pathology_commission.min' => __('Pathology commission must be at least 0.01.'),
            'radiology_commission.numeric' => __('Radiology commission must be a number.'),
            'radiology_commission.min' => __('Radiology commission must be at least 0.01.'),
            'blood_bank_commission.numeric' => __('Blood bank commission must be a number.'),
            'blood_bank_commission.min' => __('Blood bank commission must be at least 0.01.'),
            'ambulance_commission.numeric' => __('Ambulance commission must be a number.'),
            'ambulance_commission.min' => __('Ambulance commission must be at least 0.01.'),
        ];
    }
}