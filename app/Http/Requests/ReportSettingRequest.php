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
            'report_show_header_footer' => ['nullable'],
            'report_margin_top' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'report_margin_bottom' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'report_header_height' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'report_footer_height' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'signature_margin_top' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'signature_margin_left' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'pathologist_name' => ['nullable', 'string', 'max:255'],
            'pathologist_designation' => ['nullable', 'string', 'max:255'],
            'technologist_name' => ['nullable', 'string', 'max:1024'],
            'technologist_designation' => ['nullable', 'string', 'max:1024'],
            'sample_collected_by_name' => ['nullable', 'string', 'max:1024'],
            'sample_collected_by_designation' => ['nullable', 'string', 'max:1024'],
            'technologist_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'sample_collected_by_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'pathologist_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
