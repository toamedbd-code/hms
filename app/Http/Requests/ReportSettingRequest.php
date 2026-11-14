<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportSettingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'barcode_scale' => ['required', 'numeric', 'min:0.5', 'max:10'],
            'barcode_height' => ['required', 'integer', 'min:20', 'max:200'],
            'report_header_html' => ['nullable', 'string'],
            'report_footer_html' => ['nullable', 'string'],
            'report_show_header_footer' => ['nullable', 'boolean'],
            'report_layout_mode_scope' => ['sometimes', 'in:default,xray,ultrasound'],
            'report_layout_mode' => ['required_with:report_layout_mode_scope', 'in:full_page_pad,header_footer'],
            'report_show_header' => ['nullable', 'boolean'],
            'report_show_footer' => ['nullable', 'boolean'],
            'report_full_page_institution_pad' => ['nullable', 'boolean'],
            'report_full_page_pad_content_offset' => ['nullable', 'integer', 'min:0', 'max:100'],
            'report_margin_top' => ['nullable', 'integer', 'min:0', 'max:529'],
            'report_margin_bottom' => ['nullable', 'integer', 'min:0', 'max:529'],
            'report_margin_left' => ['nullable', 'integer', 'min:0', 'max:100'],
            'report_margin_right' => ['nullable', 'integer', 'min:0', 'max:100'],
            'report_header_height' => ['nullable', 'integer', 'min:0', 'max:265'],
            'report_footer_height' => ['nullable', 'integer', 'min:0', 'max:265'],
            'report_footer_content_offset' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'report_font_size_adjustment' => ['nullable', 'integer', 'min:0', 'max:3'],
            'signature_margin_top' => ['nullable', 'integer', 'min:0', 'max:529'],
            'signature_margin_left' => ['nullable', 'integer', 'min:0', 'max:529'],
            'cbc_signature_margin_top' => ['nullable', 'integer', 'min:-132', 'max:132'],
            'machine_info_line' => ['nullable', 'string', 'max:1024'],
            'machine_info_line_enabled' => ['nullable', 'boolean'],
            'machine_info_by_category' => ['nullable', 'array'],
            'reporting_departments' => ['nullable', 'array'],
            'reporting_departments.xray' => ['nullable', 'array'],
            'reporting_departments.ultrasound' => ['nullable', 'array'],
            'reporting_departments.*.machine_info_line' => ['nullable', 'string', 'max:1024'],
            'reporting_departments.*.machine_info_line_enabled' => ['nullable', 'boolean'],
            'reporting_departments.*.show_header' => ['nullable', 'boolean'],
            'reporting_departments.*.show_footer' => ['nullable', 'boolean'],
            'reporting_departments.*.show_header_footer' => ['nullable', 'boolean'],
            'reporting_departments.*.full_page_institution_pad' => ['nullable', 'boolean'],
            'reporting_departments.*.layout.full_page_pad_content_offset' => ['nullable', 'integer', 'min:0', 'max:100'],
            'reporting_departments.*.layout.page_margin_top' => ['nullable', 'integer', 'min:0', 'max:529'],
            'reporting_departments.*.layout.page_margin_bottom' => ['nullable', 'integer', 'min:0', 'max:529'],
            'reporting_departments.*.layout.header_height' => ['nullable', 'integer', 'min:0', 'max:265'],
            'reporting_departments.*.layout.footer_height' => ['nullable', 'integer', 'min:0', 'max:265'],
            'reporting_departments.*.layout.footer_content_offset' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'reporting_departments.*.layout.font_size_adjustment' => ['nullable', 'integer', 'min:0', 'max:3'],
            'reporting_departments.*.signature.margin_top' => ['nullable', 'integer', 'min:0', 'max:529'],
            'reporting_departments.*.signature.margin_left' => ['nullable', 'integer', 'min:0', 'max:529'],
            'reporting_departments.*.pathologist_name' => ['nullable', 'string', 'max:1024'],
            'reporting_departments.*.pathologist_designation' => ['nullable', 'string', 'max:1024'],
            'reporting_departments.*.technologist_name' => ['nullable', 'string', 'max:1024'],
            'reporting_departments.*.technologist_designation' => ['nullable', 'string', 'max:1024'],
            'reporting_departments.*.sample_collected_by_name' => ['nullable', 'string', 'max:1024'],
            'reporting_departments.*.sample_collected_by_designation' => ['nullable', 'string', 'max:1024'],
            'reporting_departments.*.technologist_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'reporting_departments.*.sample_collected_by_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'reporting_departments.*.pathologist_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'reporting_departments.*.remove_technologist_signature' => ['nullable', 'boolean'],
            'reporting_departments.*.remove_sample_collected_by_signature' => ['nullable', 'boolean'],
            'reporting_departments.*.remove_pathologist_signature' => ['nullable', 'boolean'],
            'pathologist_name' => ['nullable', 'string', 'max:255'],
            'pathologist_designation' => ['nullable', 'string', 'max:255'],
            'technologist_name' => ['nullable', 'string', 'max:1024'],
            'technologist_designation' => ['nullable', 'string', 'max:1024'],
            'sample_collected_by_name' => ['nullable', 'string', 'max:1024'],
            'sample_collected_by_designation' => ['nullable', 'string', 'max:1024'],
            'technologist_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'sample_collected_by_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'pathologist_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_technologist_signature' => ['nullable', 'boolean'],
            'remove_sample_collected_by_signature' => ['nullable', 'boolean'],
            'remove_pathologist_signature' => ['nullable', 'boolean'],
        ];
    }
}
