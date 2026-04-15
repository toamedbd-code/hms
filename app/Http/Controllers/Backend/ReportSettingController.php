<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportSettingRequest;
use App\Models\WebSetting;
use Illuminate\Support\Facades\Storage;
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
        $settings = WebSetting::query()->first();

        if (!$settings) {
            return redirect()->route('backend.websetting.create')
                ->with('warning', 'Please configure Websetting first.');
        }

        $settingsData = $settings->toArray();
        $settingsData['technologist_signature_preview_url'] = $this->toSignaturePreviewUrl($settings->technologist_signature);
        $settingsData['sample_collected_by_signature_preview_url'] = $this->toSignaturePreviewUrl($settings->sample_collected_by_signature);
        $settingsData['pathologist_signature_preview_url'] = $this->toSignaturePreviewUrl($settings->pathologist_signature);

        return Inertia::render('Backend/ReportSetting/Form', [
            'pageTitle' => 'Report Settings',
            'settings' => $settingsData,
        ]);
    }

    public function update(ReportSettingRequest $request)
    {
        $settings = WebSetting::query()->first();

        if (!$settings) {
            return redirect()->route('backend.websetting.create')
                ->with('warning', 'Please configure Websetting first.');
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

        // set reporting.show_header_footer
        if (array_key_exists('report_show_header_footer', $data)) {
            $show = $data['report_show_header_footer'];
            $show = $show === '0' || $show === 0 || $show === false ? false : (bool) $show;
            data_set($attendanceOptions, 'reporting.show_header_footer', $show);
            unset($data['report_show_header_footer']);
        }

        if (array_key_exists('report_margin_top', $data)) {
            data_set($attendanceOptions, 'reporting.layout.page_margin_top', intval($data['report_margin_top'] ?? 0));
            unset($data['report_margin_top']);
        }

        if (array_key_exists('report_margin_bottom', $data)) {
            data_set($attendanceOptions, 'reporting.layout.page_margin_bottom', intval($data['report_margin_bottom'] ?? 0));
            unset($data['report_margin_bottom']);
        }

        // header/footer pixel heights (in px)
        if (array_key_exists('report_header_height', $data)) {
            data_set($attendanceOptions, 'reporting.layout.header_height', intval($data['report_header_height'] ?? 0));
            unset($data['report_header_height']);
        }

        if (array_key_exists('report_footer_height', $data)) {
            data_set($attendanceOptions, 'reporting.layout.footer_height', intval($data['report_footer_height'] ?? 0));
            unset($data['report_footer_height']);
        }

        // signature margins: safe padding to shift signature area
        if (array_key_exists('signature_margin_top', $data)) {
            data_set($attendanceOptions, 'reporting.signature.margin_top', intval($data['signature_margin_top'] ?? 160));
            unset($data['signature_margin_top']);
        }

        if (array_key_exists('signature_margin_left', $data)) {
            data_set($attendanceOptions, 'reporting.signature.margin_left', intval($data['signature_margin_left'] ?? 96));
            unset($data['signature_margin_left']);
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
            if (!$request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);
            $storedPath = $file->store('report-signatures', 'public');
            $data[$field] = $storedPath;

            $oldPath = (string) ($settings->{$field} ?? '');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $settings->update($data);

        if (function_exists('get_cached_web_setting')) {
            get_cached_web_setting(true);
        }

        return redirect()->back()->with('successMessage', 'Report settings updated successfully.');
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
