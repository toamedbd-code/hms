<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebSettingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    'company_name' => 'required|string|max:255',
                    'company_short_name' => 'required|string|max:100',
                    'hospital_code' => 'nullable|string|max:100',
                    'address' => 'required|string|max:500',
                    'phone' => 'required|string|max:255',
                    'email' => 'required|email|max:255',
                    'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                    'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,ico|max:1024',
                    'language' => 'required|string|max:50',
                    'date_format' => 'required|string|max:50',
                    'time_zone' => 'required|string|max:100',
                    'currency' => 'required|string|max:20',
                    'currency_symbol' => 'required|string|max:20',
                    'credit_limit' => 'required|numeric|min:0',
                    'max_billing_discount_percent' => 'required|numeric|min:0|max:100',
                    'low_stock_threshold' => 'nullable|integer|min:0|max:100000',
                    'time_format' => 'required|string|max:20',
                    'mobile_app_api_url' => 'nullable|string|max:255',
                    'mobile_app_primary_color_code' => 'nullable|string|max:20',
                    'mobile_app_secondary_color_code' => 'nullable|string|max:20',
                    'mobile_app_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                    'doctor_restriction_mode' => 'nullable|boolean',
                    'superadmin_visibility' => 'nullable|boolean',
                    'patient_panel' => 'nullable|boolean',
                    'opd_invoice_header_footer' => 'nullable|boolean',
                    'ipd_invoice_header_footer' => 'nullable|boolean',
                    'opd_prescription_header_footer' => 'nullable|boolean',
                    'ipd_prescription_header_footer' => 'nullable|boolean',
                    'scan_type' => 'required|in:Barcode,QR Code',
                    'current_theme' => 'required|in:default,red,blue,gray,emerald,amber,rose,indigo',
                    // Bulk SMS settings
                    'sms_enabled' => 'nullable|boolean',
                    'sms_api_url' => 'nullable|url|max:255',
                    'sms_api_key' => 'nullable|string|max:255',
                    'sms_sender_id' => 'nullable|string|max:100',
                    'sms_route' => 'nullable|string|max:50',
                    'sms_is_unicode' => 'nullable|boolean',
                    'sms_additional_params' => 'nullable|string',
                    'personal_bkash_number' => 'nullable|string|max:30',
                    'personal_nagad_number' => 'nullable|string|max:30',
                    'ipd_no_prefix' => 'required|string|max:20',
                    'opd_no_prefix' => 'required|string|max:20',
                    'ipd_prescription_prefix' => 'required|string|max:20',
                    'opd_prescription_prefix' => 'required|string|max:20',
                    'appointment_prefix' => 'required|string|max:20',
                    'pharmacy_bill_prefix' => 'required|string|max:20',
                    'billing_bill_prefix' => 'required|string|max:20',
                    'operation_reference_no_prefix' => 'required|string|max:20',
                    'blood_bank_bill_prefix' => 'required|string|max:20',
                    'ambulance_call_bill_prefix' => 'required|string|max:20',
                    'radiology_bill_prefix' => 'required|string|max:20',
                    'pathology_bill_prefix' => 'required|string|max:20',
                    'opd_checkup_id_prefix' => 'required|string|max:20',
                    'pharmacy_purchase_no_prefix' => 'required|string|max:20',
                    'transaction_id_prefix' => 'required|string|max:20',
                    'birth_record_reference_no_prefix' => 'required|string|max:20',
                    'death_record_reference_no_prefix' => 'required|string|max:20',
                    'report_title' => 'required|string|max:255',
                    'website_hero_title' => 'nullable|string|max:255',
                    'website_hero_subtitle' => 'nullable|string',
                    'website_about_text' => 'nullable|string',
                    'website_emergency_phone' => 'nullable|string|max:100',
                    'website_enabled' => 'nullable|boolean',
                    'website_cta_text' => 'nullable|string|max:255',
                    'website_featured_doctors_json' => 'nullable|string',
                    'website_template' => 'nullable|string|max:100',
                    'website_featured_doctor_images' => 'nullable|array',
                    'website_featured_doctor_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                    'website_services_json' => 'nullable|string',
                    'website_facilities_json' => 'nullable|string',
                    'website_testimonials_en_json' => 'nullable|string',
                    'website_testimonials_bn_json' => 'nullable|string',
                        // Attendance device settings
                        'attendance_device_enabled' => 'nullable|boolean',
                        'attendance_device_type' => 'nullable|in:face,fingerprint,both',
                        'attendance_device_identifier' => 'nullable|string|max:255',
                        'attendance_device_ip' => 'nullable|ip',
                        'attendance_device_port' => 'nullable|string|max:20',
                        'attendance_device_secret' => 'nullable|string|max:255',
                        'attendance_device_options' => 'nullable|string',
                ];
                break;

            case 'PATCH':
            case 'PUT':
                return [
                    'company_name' => 'required|string|max:255',
                    'company_short_name' => 'required|string|max:100',
                    'hospital_code' => 'nullable|string|max:100',
                    'address' => 'required|string|max:500',
                    'phone' => 'required|string|max:255',
                    'email' => 'required|email|max:255',
                    'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                    'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,ico|max:1024',
                    'language' => 'required|string|max:50',
                    'date_format' => 'required|string|max:50',
                    'time_zone' => 'required|string|max:100',
                    'currency' => 'required|string|max:20',
                    'currency_symbol' => 'required|string|max:20',
                    'credit_limit' => 'required|numeric|min:0',
                    'max_billing_discount_percent' => 'required|numeric|min:0|max:100',
                    'low_stock_threshold' => 'nullable|integer|min:0|max:100000',
                    'time_format' => 'required|string|max:20',
                    'mobile_app_api_url' => 'nullable|string|max:255',
                    'mobile_app_primary_color_code' => 'nullable|string|max:20',
                    'mobile_app_secondary_color_code' => 'nullable|string|max:20',
                    'mobile_app_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                    'doctor_restriction_mode' => 'nullable|boolean',
                    'superadmin_visibility' => 'nullable|boolean',
                    'patient_panel' => 'nullable|boolean',
                    'opd_invoice_header_footer' => 'nullable|boolean',
                    'ipd_invoice_header_footer' => 'nullable|boolean',
                    'opd_prescription_header_footer' => 'nullable|boolean',
                    'ipd_prescription_header_footer' => 'nullable|boolean',
                    'scan_type' => 'required|in:Barcode,QR Code',
                    'current_theme' => 'required|in:default,red,blue,gray,emerald,amber,rose,indigo',
                    'ipd_no_prefix' => 'required|string|max:20',
                    'opd_no_prefix' => 'required|string|max:20',
                    'ipd_prescription_prefix' => 'required|string|max:20',
                    'opd_prescription_prefix' => 'required|string|max:20',
                    'appointment_prefix' => 'required|string|max:20',
                    'pharmacy_bill_prefix' => 'required|string|max:20',
                    'billing_bill_prefix' => 'required|string|max:20',
                    'operation_reference_no_prefix' => 'required|string|max:20',
                    'blood_bank_bill_prefix' => 'required|string|max:20',
                    'ambulance_call_bill_prefix' => 'required|string|max:20',
                    'radiology_bill_prefix' => 'required|string|max:20',
                    'pathology_bill_prefix' => 'required|string|max:20',
                    'opd_checkup_id_prefix' => 'required|string|max:20',
                    'pharmacy_purchase_no_prefix' => 'required|string|max:20',
                    'transaction_id_prefix' => 'required|string|max:20',
                    'birth_record_reference_no_prefix' => 'required|string|max:20',
                    'death_record_reference_no_prefix' => 'required|string|max:20',
                    'report_title' => 'required|string|max:255',
                    'website_hero_title' => 'nullable|string|max:255',
                    'website_hero_subtitle' => 'nullable|string',
                    'website_about_text' => 'nullable|string',
                    'website_emergency_phone' => 'nullable|string|max:100',
                    'website_enabled' => 'nullable|boolean',
                    'website_cta_text' => 'nullable|string|max:255',
                    'website_featured_doctors_json' => 'nullable|string',
                    'website_template' => 'nullable|string|max:100',
                    'website_featured_doctor_images' => 'nullable|array',
                    'website_featured_doctor_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                    'website_services_json' => 'nullable|string',
                    'website_facilities_json' => 'nullable|string',
                    'website_testimonials_en_json' => 'nullable|string',
                    'website_testimonials_bn_json' => 'nullable|string',
                    // Attendance device settings
                    'attendance_device_enabled' => 'nullable|boolean',
                    'attendance_device_type' => 'nullable|in:face,fingerprint,both',
                    'attendance_device_identifier' => 'nullable|string|max:255',
                    'attendance_device_ip' => 'nullable|ip',
                    'attendance_device_port' => 'nullable|string|max:20',
                    'attendance_device_secret' => 'nullable|string|max:255',
                    'attendance_device_options' => 'nullable|string',
                    // Bulk SMS settings
                    'sms_enabled' => 'nullable|boolean',
                    'sms_api_url' => 'nullable|url|max:255',
                    'sms_api_key' => 'nullable|string|max:255',
                    'sms_sender_id' => 'nullable|string|max:100',
                    'sms_route' => 'nullable|string|max:50',
                    'sms_is_unicode' => 'nullable|boolean',
                    'sms_additional_params' => 'nullable|string',
                    'personal_bkash_number' => 'nullable|string|max:30',
                    'personal_nagad_number' => 'nullable|string|max:30',
                ];
                break;
        }
    }

    public function messages()
    {
        return [
            'company_name.required' => 'The company name field is required.',
            'company_name.string' => 'The company name must be a string.',
            'company_name.max' => 'The company name may not be greater than 255 characters.',
            
            'company_short_name.required' => 'The company short name field is required.',
            'company_short_name.string' => 'The company short name must be a string.',
            'company_short_name.max' => 'The company short name may not be greater than 100 characters.',

            'address.required' => 'The address field is required.',
            'address.max' => 'The address may not be greater than 500 characters.',
            
            'phone.required' => 'The phone field is required.',
            'phone.string' => 'The phone must be a string.',
            'phone.max' => 'The phone may not be greater than 255 characters.',

            'email.required' => 'The email field is required.',
            'email.email' => 'Please provide a valid email address.',
            
            'logo.image' => 'The logo must be an image.',
            'logo.mimes' => 'The logo must be a file of type: jpeg, png, jpg, gif, svg.',
            'logo.max' => 'The logo may not be greater than 2MB.',
            
            'icon.image' => 'The icon must be an image.',
            'icon.mimes' => 'The icon must be a file of type: jpeg, png, jpg, gif, svg, ico.',
            'icon.max' => 'The icon may not be greater than 1MB.',
            
            'report_title.required' => 'The report title field is required.',
            'report_title.string' => 'The report title must be a string.',
            'report_title.max' => 'The report title may not be greater than 255 characters.',

            'scan_type.in' => 'The scan type must be Barcode or QR Code.',
            'current_theme.in' => 'The current theme must be one of: default, red, blue, gray, emerald, amber, rose, indigo.',
            'max_billing_discount_percent.required' => 'The max billing discount percent field is required.',
            'max_billing_discount_percent.numeric' => 'Max billing discount percent must be a number.',
            'max_billing_discount_percent.min' => 'Max billing discount percent cannot be negative.',
            'max_billing_discount_percent.max' => 'Max billing discount percent cannot be greater than 100.',
            'low_stock_threshold.integer' => 'Low stock threshold must be a whole number.',
            'low_stock_threshold.min' => 'Low stock threshold cannot be negative.',
            'low_stock_threshold.max' => 'Low stock threshold seems too large.',
        ];
    }
}