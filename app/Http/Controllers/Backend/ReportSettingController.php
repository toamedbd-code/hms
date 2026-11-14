<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportSettingRequest;
use App\Models\Company;
use App\Models\InvoiceDesign;
use App\Models\MainCategory;
use App\Models\WebSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ReportSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:report-settings');
    }

    public function edit()
    {
        $settings = WebSetting::where('status', 'Active')->orderByDesc('id')->first();
        if (! $settings) {
            $settings = WebSetting::query()->orderByDesc('id')->first();
        }

        $settingsData = $settings ? $settings->toArray() : [];
        $settingsData['technologist_signature_preview_url'] = $this->toSignaturePreviewUrl($settings?->technologist_signature);
        $settingsData['sample_collected_by_signature_preview_url'] = $this->toSignaturePreviewUrl($settings?->sample_collected_by_signature);
        $settingsData['pathologist_signature_preview_url'] = $this->toSignaturePreviewUrl($settings?->pathologist_signature);
        $attendanceOptions = $settingsData['attendance_device_options'] ?? [];
        $reportingDepartments = data_get($attendanceOptions, 'reporting.departments', []);
        $reportingDepartments = is_array($reportingDepartments) ? $reportingDepartments : [];
        foreach (['xray', 'ultrasound'] as $department) {
            $departmentSettings = $reportingDepartments[$department] ?? [];
            $departmentSettings = is_array($departmentSettings) ? $departmentSettings : [];
            foreach (['technologist_signature', 'sample_collected_by_signature', 'pathologist_signature'] as $field) {
                $departmentSettings[$field . '_preview_url'] = $this->toSignaturePreviewUrl(
                    isset($departmentSettings[$field]) ? (string) $departmentSettings[$field] : null
                );
                unset($departmentSettings[$field]);
            }
            $reportingDepartments[$department] = $departmentSettings;
        }
        $reportFullPagePad = InvoiceDesign::reportFullPagePad();

        $mainCategories = MainCategory::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => trim((string) $category->name),
            ])
            ->filter(fn ($category) => $category['name'] !== '')
            ->values();

        return Inertia::render('Backend/ReportSetting/Form', [
            'pageTitle' => 'Report Settings',
            'settings' => $settingsData,
            'mainCategories' => $mainCategories,
            'reportingDepartments' => $reportingDepartments,
            'fullPagePadAvailable' => $reportFullPagePad !== null,
        ]);
    }

    public function update(ReportSettingRequest $request)
    {
        $settings = WebSetting::where('status', 'Active')->orderByDesc('id')->first();
        if (! $settings) {
            $settings = WebSetting::query()->orderByDesc('id')->first();
        }

        if (! $settings) {
            $settings = new WebSetting();
            $settings->status = 'Active';

            $company = Company::query()
                ->where('status', 'Active')
                ->orderBy('sorting')
                ->first();
            $companyName = trim((string) ($company?->name ?: config('app.name', 'Hospital Management System')));
            if ($companyName === '') {
                $companyName = 'Hospital Management System';
            }

            $settings->fill([
                'company_name' => $companyName,
                'company_short_name' => trim((string) $company?->short_name) ?: Str::upper(Str::substr($companyName, 0, 10)),
                'phone' => trim((string) ($company?->phone ?? '')),
                'report_title' => $companyName,
            ]);
        }

        $data = $request->validated();

        // persist reporting layout options inside attendance_device_options JSON
        $attendanceOptions = $settings->attendance_device_options;
        if (!is_array($attendanceOptions)) {
            try {
                $attendanceOptions = is_string($attendanceOptions) && trim($attendanceOptions) !== '' ? json_decode($attendanceOptions, true) : [];
            } catch (\Throwable $e) {
                $attendanceOptions = [];
            }
        }

        // ensure array
        $attendanceOptions = is_array($attendanceOptions) ? $attendanceOptions : [];

        if (array_key_exists('report_layout_mode_scope', $data)) {
            $scope = $data['report_layout_mode_scope'];
            $mode = $data['report_layout_mode'];
            $fullPagePadEnabled = $mode === 'full_page_pad';
            $showHeaderFooter = $mode === 'header_footer';
            $reportingPath = $scope === 'default'
                ? 'reporting'
                : "reporting.departments.{$scope}";

            data_set($attendanceOptions, "{$reportingPath}.full_page_institution_pad", $fullPagePadEnabled);
            data_set($attendanceOptions, "{$reportingPath}.show_header", $showHeaderFooter);
            data_set($attendanceOptions, "{$reportingPath}.show_footer", $showHeaderFooter);

            $settings->attendance_device_options = $attendanceOptions;
            $settings->save();

            if (function_exists('get_cached_web_setting')) {
                get_cached_web_setting(true);
            }

            return redirect()->back()->with('successMessage', 'Report settings updated successfully.');
        }

        // support both combined and separate header/footer toggles
        if (array_key_exists('report_show_header_footer', $data)) {
            $show = $data['report_show_header_footer'];
            $show = $show === '0' || $show === 0 || $show === false ? false : (bool) $show;
            data_set($attendanceOptions, 'reporting.show_header', $show);
            data_set($attendanceOptions, 'reporting.show_footer', $show);
            unset($data['report_show_header_footer']);
        }

        if (array_key_exists('report_show_header', $data)) {
            $showHeader = $data['report_show_header'];
            $showHeader = $showHeader === '0' || $showHeader === 0 || $showHeader === false ? false : (bool) $showHeader;
            data_set($attendanceOptions, 'reporting.show_header', $showHeader);
            unset($data['report_show_header']);
        }

        if (array_key_exists('report_show_footer', $data)) {
            $showFooter = $data['report_show_footer'];
            $showFooter = $showFooter === '0' || $showFooter === 0 || $showFooter === false ? false : (bool) $showFooter;
            data_set($attendanceOptions, 'reporting.show_footer', $showFooter);
            unset($data['report_show_footer']);
        }

        if (array_key_exists('report_full_page_institution_pad', $data)) {
            $enabled = $data['report_full_page_institution_pad'];
            $enabled = $enabled === '0' || $enabled === 0 || $enabled === false ? false : (bool) $enabled;
            data_set($attendanceOptions, 'reporting.full_page_institution_pad', $enabled);
            unset($data['report_full_page_institution_pad']);
        }

        if (array_key_exists('report_full_page_pad_content_offset', $data)) {
            data_set($attendanceOptions, 'reporting.layout.full_page_pad_content_offset', (int) ($data['report_full_page_pad_content_offset'] ?? 0));
            unset($data['report_full_page_pad_content_offset']);
        }

        if (array_key_exists('report_margin_top', $data)) {
            data_set($attendanceOptions, 'reporting.layout.page_margin_top', self::pixelsFromMillimeters($data['report_margin_top'] ?? 0));
            unset($data['report_margin_top']);
        }

        if (array_key_exists('report_margin_bottom', $data)) {
            data_set($attendanceOptions, 'reporting.layout.page_margin_bottom', self::pixelsFromMillimeters($data['report_margin_bottom'] ?? 0));
            unset($data['report_margin_bottom']);
        }

        foreach (['left', 'right'] as $side) {
            $field = "report_margin_{$side}";
            if (array_key_exists($field, $data)) {
                data_set(
                    $attendanceOptions,
                    "reporting.layout.page_margin_{$side}",
                    self::pixelsFromMillimeters($data[$field] ?? 13)
                );
                unset($data[$field]);
            }
        }

        if (array_key_exists('report_header_height', $data)) {
            data_set($attendanceOptions, 'reporting.layout.header_height', self::pixelsFromMillimeters($data['report_header_height'] ?? 0));
            unset($data['report_header_height']);
        }

        if (array_key_exists('report_footer_height', $data)) {
            data_set($attendanceOptions, 'reporting.layout.footer_height', self::pixelsFromMillimeters($data['report_footer_height'] ?? 0));
            unset($data['report_footer_height']);
        }

        if (array_key_exists('report_footer_content_offset', $data)) {
            data_set($attendanceOptions, 'reporting.layout.footer_content_offset', intval($data['report_footer_content_offset'] ?? 0));
            unset($data['report_footer_content_offset']);
        }

        if (array_key_exists('report_font_size_adjustment', $data)) {
            data_set($attendanceOptions, 'reporting.layout.font_size_adjustment', self::pixelsFromMillimeters($data['report_font_size_adjustment'] ?? 0));
            unset($data['report_font_size_adjustment']);
        }

        // signature margins: safe padding to shift signature area
        if (array_key_exists('signature_margin_top', $data)) {
            data_set($attendanceOptions, 'reporting.signature.margin_top', self::pixelsFromMillimeters($data['signature_margin_top'] ?? 42.33));
            unset($data['signature_margin_top']);
        }

        if (array_key_exists('signature_margin_left', $data)) {
            data_set($attendanceOptions, 'reporting.signature.margin_left', self::pixelsFromMillimeters($data['signature_margin_left'] ?? 25.4));
            unset($data['signature_margin_left']);
        }

        if (array_key_exists('cbc_signature_margin_top', $data)) {
            data_set($attendanceOptions, 'reporting.cbc.signature.margin_top', self::pixelsFromMillimeters($data['cbc_signature_margin_top'] ?? 3.7));
            unset($data['cbc_signature_margin_top']);
        }

        if (array_key_exists('machine_info_line', $data)) {
            $machineInfoLine = trim((string) ($data['machine_info_line'] ?? ''));
            data_set($attendanceOptions, 'reporting.machine_info_line', $machineInfoLine);
            unset($data['machine_info_line']);
        }

        if (array_key_exists('machine_info_line_enabled', $data)) {
            $showMachineInfoLine = $data['machine_info_line_enabled'];
            $showMachineInfoLine = $showMachineInfoLine === '0' || $showMachineInfoLine === 0 || $showMachineInfoLine === false ? false : (bool) $showMachineInfoLine;
            data_set($attendanceOptions, 'reporting.machine_info_line_enabled', $showMachineInfoLine);
            unset($data['machine_info_line_enabled']);
        }

        if (array_key_exists('machine_info_by_category', $data)) {
            $categoryConfig = $data['machine_info_by_category'] ?? [];
            $normalizedConfig = [];

            if (is_array($categoryConfig)) {
                foreach ($categoryConfig as $categoryKey => $config) {
                    if (!is_array($config)) {
                        continue;
                    }

                    $normalizedKey = trim((string) $categoryKey);
                    if ($normalizedKey === '') {
                        continue;
                    }

                    $enabled = $config['enabled'] ?? true;
                    if (is_string($enabled)) {
                        $enabled = in_array(strtolower($enabled), ['1', 'true', 'yes', 'on'], true);
                    }

                    $normalizedConfig[$normalizedKey] = [
                        'enabled' => (bool) $enabled,
                        'text' => trim((string) ($config['text'] ?? '')),
                    ];
                }
            }

            data_set($attendanceOptions, 'reporting.machine_info_by_category', $normalizedConfig);
            unset($data['machine_info_by_category']);
        }

        if (array_key_exists('reporting_departments', $data)) {
            $submittedDepartments = is_array($data['reporting_departments']) ? $data['reporting_departments'] : [];
            foreach (['xray', 'ultrasound'] as $department) {
                $submitted = $submittedDepartments[$department] ?? [];
                if (!is_array($submitted)) {
                    continue;
                }

                $savedDepartmentSettings = data_get($attendanceOptions, "reporting.departments.{$department}", []);
                $savedDepartmentSettings = is_array($savedDepartmentSettings) ? $savedDepartmentSettings : [];
                $departmentShowHeaderFooter = $submitted['show_header_footer'] ?? null;

                $normalized = [
                    'machine_info_line' => trim((string) ($submitted['machine_info_line'] ?? '')),
                    'machine_info_line_enabled' => filter_var($submitted['machine_info_line_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'show_header' => filter_var(
                        $submitted['show_header'] ?? $departmentShowHeaderFooter ?? ($savedDepartmentSettings['show_header']
                            ?? data_get($attendanceOptions, 'reporting.show_header', data_get($attendanceOptions, 'reporting.show_header_footer', true))),
                        FILTER_VALIDATE_BOOLEAN
                    ),
                    'show_footer' => filter_var(
                        $submitted['show_footer'] ?? $departmentShowHeaderFooter ?? ($savedDepartmentSettings['show_footer']
                            ?? data_get($attendanceOptions, 'reporting.show_footer', data_get($attendanceOptions, 'reporting.show_header_footer', true))),
                        FILTER_VALIDATE_BOOLEAN
                    ),
                    'full_page_institution_pad' => filter_var($submitted['full_page_institution_pad'] ?? data_get($attendanceOptions, "reporting.departments.{$department}.full_page_institution_pad", data_get($attendanceOptions, 'reporting.full_page_institution_pad', false)), FILTER_VALIDATE_BOOLEAN),
                ];

                $layoutDefaults = [
                    'full_page_pad_content_offset' => 0,
                    'page_margin_top' => 0,
                    'page_margin_bottom' => 0,
                    'header_height' => 115,
                    'footer_height' => 70,
                    'footer_content_offset' => 0,
                    'font_size_adjustment' => 0,
                ];
                foreach ($layoutDefaults as $field => $default) {
                    $isSubmitted = array_key_exists($field, $submitted['layout'] ?? []);
                    $value = data_get(
                        $submitted,
                        "layout.{$field}",
                        data_get(
                            $attendanceOptions,
                            "reporting.departments.{$department}.layout.{$field}",
                            data_get($attendanceOptions, "reporting.layout.{$field}", $default)
                        )
                    );
                    $storedValue = $isSubmitted && in_array($field, [
                        'page_margin_top',
                        'page_margin_bottom',
                        'header_height',
                        'footer_height',
                        'font_size_adjustment',
                    ], true)
                        ? self::pixelsFromMillimeters($value)
                        : (int) $value;
                    data_set($normalized, "layout.{$field}", $storedValue);
                }

                foreach (['margin_top', 'margin_left'] as $field) {
                    $isSubmitted = array_key_exists($field, $submitted['signature'] ?? []);
                    $value = data_get(
                        $submitted,
                        "signature.{$field}",
                        data_get(
                            $attendanceOptions,
                            "reporting.departments.{$department}.signature.{$field}",
                            data_get($attendanceOptions, "reporting.signature.{$field}", $field === 'margin_top' ? 160 : 96)
                        )
                    );
                    data_set(
                        $normalized,
                        "signature.{$field}",
                        $isSubmitted ? self::pixelsFromMillimeters($value) : (int) $value
                    );
                }

                foreach ([
                    'pathologist_name',
                    'pathologist_designation',
                    'technologist_name',
                    'technologist_designation',
                    'sample_collected_by_name',
                    'sample_collected_by_designation',
                ] as $field) {
                    $normalized[$field] = trim((string) ($submitted[$field] ?? ''));
                }

                foreach (['technologist_signature', 'sample_collected_by_signature', 'pathologist_signature'] as $field) {
                    $fileKey = "reporting_departments.{$department}.{$field}";
                    $oldPath = (string) data_get($attendanceOptions, "reporting.departments.{$department}.{$field}", '');
                    if ($request->hasFile($fileKey)) {
                        $storedPath = $request->file($fileKey)->store('report-signatures', 'public');
                        $normalized[$field] = $storedPath;
                        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    } elseif (filter_var($submitted["remove_{$field}"] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $normalized[$field] = null;
                        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    } else {
                        $normalized[$field] = $oldPath;
                    }
                }

                data_set($attendanceOptions, "reporting.departments.{$department}", $normalized);
            }

            unset($data['reporting_departments']);
        }

        // identity fields: technologist and sample collected by
        if (array_key_exists('technologist_name', $data)) {
            data_set($attendanceOptions, 'reporting.identity.technologist_name', trim(strval($data['technologist_name'] ?? '')));
            unset($data['technologist_name']);
        }
        if (array_key_exists('technologist_designation', $data)) {
            data_set($attendanceOptions, 'reporting.identity.technologist_designation', trim(strval($data['technologist_designation'] ?? '')));
            unset($data['technologist_designation']);
        }
        if (array_key_exists('sample_collected_by_name', $data)) {
            data_set($attendanceOptions, 'reporting.identity.sample_collected_by_name', trim(strval($data['sample_collected_by_name'] ?? '')));
            unset($data['sample_collected_by_name']);
        }
        if (array_key_exists('sample_collected_by_designation', $data)) {
            data_set($attendanceOptions, 'reporting.identity.sample_collected_by_designation', trim(strval($data['sample_collected_by_designation'] ?? '')));
            unset($data['sample_collected_by_designation']);
        }

        $data['attendance_device_options'] = $attendanceOptions;

        $uploadMappings = [
            'technologist_signature',
            'sample_collected_by_signature',
            'pathologist_signature',
        ];

        foreach ($uploadMappings as $field) {
            $oldPath = (string) ($settings->{$field} ?? '');

            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $data[$field] = $file->store('report-signatures', 'public');

                if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            } elseif (filter_var($data["remove_{$field}"] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $data[$field] = null;

                if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            } else {
                unset($data[$field]);
            }

            unset($data["remove_{$field}"]);
        }

        if ($settings->exists) {
            $settings->update($data);
        } else {
            $settings->fill($data);
            $settings->save();
        }

        if (function_exists('get_cached_web_setting')) {
            get_cached_web_setting(true);
        }

        return redirect()->back()->with('successMessage', 'Report settings updated successfully.');
    }

    private static function pixelsFromMillimeters(mixed $millimeters): int
    {
        return (int) round(((float) $millimeters * 96) / 25.4);
    }

    private function toSignaturePreviewUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (preg_match('/^(https?:\/\/|data:)/i', $path) === 1) {
            return $path;
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedPath = ltrim($normalizedPath, '/');

        $candidates = array_values(array_unique(array_filter([
            $normalizedPath,
            preg_replace('#^storage/#i', '', $normalizedPath),
            preg_replace('#^public/#i', '', $normalizedPath),
            preg_replace('#^public/storage/#i', '', $normalizedPath),
        ])));

        $resolvedPath = null;
        foreach ($candidates as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                $resolvedPath = $candidate;
                break;
            }
        }

        if ($resolvedPath !== null) {
            $fileBytes = Storage::disk('public')->get($resolvedPath);
            $extension = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                default => 'image/png',
            };

            return 'data:' . $mimeType . ';base64,' . base64_encode($fileBytes);
        }

        $fallbackPath = preg_replace('#^(public/|storage/)+#i', '', $normalizedPath) ?: $normalizedPath;

        return '/storage/' . ltrim($fallbackPath, '/');
    }
}
