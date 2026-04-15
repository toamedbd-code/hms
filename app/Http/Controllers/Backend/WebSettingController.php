<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebSettingRequest;
use App\Models\WebSetting;
use Illuminate\Support\Facades\DB;
use App\Services\WebSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;
use App\Models\Admin;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str as SupportStr;
use Illuminate\Support\Facades\Log;

class WebSettingController extends Controller
{
    use SystemTrait;

    protected $websettingService;

    public function __construct(WebSettingService $websettingService)
    {
        $this->websettingService = $websettingService;

        $this->middleware('auth:admin')->except(['favicon']);
        $this->middleware('permission:websetting-add|cms-setting|general-setting-add', ['only' => ['create', 'section', 'store']]);
    }

    public function create(Request $request)
    {
        $requestedSection = trim((string) $request->query('section', ''));
        $singleSectionMode = $requestedSection !== '';

        return $this->renderForm($requestedSection, $singleSectionMode);
    }

    public function section(string $section)
    {
        return $this->renderForm($section, true);
    }

    private function renderForm(?string $requestedSection = null, bool $singleSectionMode = false)
    {
        $websetting = $this->websettingService->first() ?? null;

        $availableSections = ['general', 'cms', 'sms', 'prefix', 'module', 'other'];
        $normalizedSection = strtolower(trim((string) $requestedSection));
        $activeSection = in_array($normalizedSection, $availableSections, true)
            ? $normalizedSection
            : 'general';

        $pageTitleMap = [
            'general' => 'General Setting',
            'cms' => 'CMS Setting',
            'sms' => 'SMS Setting',
            'module' => 'Module Setting',
            'prefix' => 'Prefix Setting',
            'other' => 'Other Setting',
        ];

        // discover file-based frontend templates under resources/views/frontend/templates
        $templates = [];
        $templatesDir = resource_path('views/frontend/templates');
        if (is_dir($templatesDir)) {
            foreach (glob($templatesDir . '/*.blade.php') as $tplPath) {
                $templates[] = pathinfo($tplPath, PATHINFO_FILENAME);
            }
        }
        if (empty($templates)) {
            $templates = ['default'];
        }

        return Inertia::render('Backend/WebSetting/Form', [
            'websetting' => fn() => $websetting,
            'pageTitle' => fn() => $pageTitleMap[$activeSection] ?? 'General Setting',
            'activeSection' => fn() => $activeSection,
            'singleSectionMode' => fn() => $singleSectionMode,
            'availableTemplates' => fn() => $templates,
            'bookingDoctors' => fn() => Admin::query()
                ->whereNull('deleted_at')
                ->where('status', 'Active')
                ->whereHas('role', function ($query) {
                    $query->where('name', 'Doctor');
                })
                ->get(['id', 'first_name', 'last_name', 'phone'])
                ->map(function ($doctor) {
                    return [
                        'id' => $doctor->id,
                        'name' => trim(($doctor->first_name ?? '') . ' ' . ($doctor->last_name ?? '')),
                        'phone' => $doctor->phone,
                    ];
                })->values(),
        ]);
    }

    public function store(WebSettingRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            // If a website_template was provided, persist it inside attendance_device_options
            // to avoid requiring a DB migration for a dedicated column.
            if (isset($data['website_template'])) {
                $existingOptions = $settings?->attendance_device_options ?? [];
                if (is_string($existingOptions)) {
                    $decoded = json_decode($existingOptions, true);
                    $existingOptions = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
                }

                $existingOptions = is_array($existingOptions) ? $existingOptions : [];
                $existingOptions['website_template'] = $data['website_template'];
                $data['attendance_device_options'] = json_encode($existingOptions, JSON_UNESCAPED_UNICODE);
                unset($data['website_template']);
            }

            if (isset($data['attendance_device_options']) && is_string($data['attendance_device_options'])) {
                $decodedOptions = json_decode($data['attendance_device_options'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedOptions)) {
                    $data['attendance_device_options'] = $decodedOptions;
                }
            }

            if (isset($data['website_featured_doctors_json']) && is_string($data['website_featured_doctors_json'])) {
                $decodedDoctors = json_decode($data['website_featured_doctors_json'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedDoctors)) {
                    $uploadedDoctorImages = $request->file('website_featured_doctor_images', []);

                    foreach ($decodedDoctors as $index => $doctor) {
                        if (!is_array($doctor)) {
                            continue;
                        }

                        if (isset($uploadedDoctorImages[$index]) && $uploadedDoctorImages[$index]) {
                            $uploadedPath = $this->imageUpload($uploadedDoctorImages[$index], 'webSetting/doctors');
                            $doctor['image_url'] = Str::startsWith($uploadedPath, ['http://', 'https://'])
                                ? $uploadedPath
                                : asset('storage/' . ltrim($uploadedPath, '/'));
                        }

                        $decodedDoctors[$index] = $doctor;
                    }

                    $data['website_featured_doctors_json'] = json_encode($decodedDoctors, JSON_UNESCAPED_UNICODE);
                }
            }

            unset($data['website_featured_doctor_images']);

            $settings = $this->websettingService->first();

            if ($request->hasFile('logo')) {
                $data['logo'] = $this->imageUpload($request->file('logo'), 'webSetting');
                
                if ($settings && $settings->logo) {
                    $oldLogoPath = strstr($settings->logo, 'storage/');
                    if ($oldLogoPath && file_exists($oldLogoPath)) {
                        unlink($oldLogoPath);
                    }
                }
            }

            if ($request->hasFile('icon')) {
                $data['icon'] = $this->imageUpload($request->file('icon'), 'webSetting');
                
                if ($settings && $settings->icon) {
                    $oldIconPath = strstr($settings->icon, 'storage/');
                    if ($oldIconPath && file_exists($oldIconPath)) {
                        unlink($oldIconPath);
                    }
                }
            }

            if ($request->hasFile('mobile_app_logo')) {
                $data['mobile_app_logo'] = $this->imageUpload($request->file('mobile_app_logo'), 'webSetting');

                if ($settings && $settings->mobile_app_logo) {
                    $oldMobileLogoPath = strstr($settings->mobile_app_logo, 'storage/');
                    if ($oldMobileLogoPath && file_exists($oldMobileLogoPath)) {
                        unlink($oldMobileLogoPath);
                    }
                }
            }

            // Keep legacy consumers working that still read report_title as hospital address.
            if (!empty($data['address'])) {
                $data['report_title'] = $data['address'];
            }

            if (empty($data['company_short_name']) && !empty($data['company_name'])) {
                $words = explode(' ', trim($data['company_name']));
                $shortName = '';
                foreach ($words as $word) {
                    if (!empty($word)) {
                        $shortName .= strtoupper(substr($word, 0, 1));
                    }
                }
                $data['company_short_name'] = substr($shortName, 0, 10);
            }

            $dataInfo = $this->websettingService->first();
            $oldSettingsSnapshot = $dataInfo ? clone $dataInfo : null;

            if ($dataInfo) {
                $updatedSettings = $this->websettingService->update($data, $dataInfo->id);
                $this->syncHistoricalPrefixValues($oldSettingsSnapshot, $updatedSettings);
                $message = 'General settings updated successfully';
            } else {
                WebSetting::create($data);
                $message = 'General settings created successfully';
            }

            // Sync featured doctors (CMS) to Admins so they are available in appointment lists
            try {
                $settingsAfter = $this->websettingService->first();
                $raw = trim((string) ($settingsAfter->website_featured_doctors_json ?? ''));
                if ($raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $changed = false;

                        $doctorRole = Role::where('name', 'Doctor')->first();
                        if (!$doctorRole) {
                            $doctorRole = Role::create([
                                'name' => 'Doctor',
                                'guard_name' => 'admin',
                                'description' => 'Doctor role created by websettings sync',
                            ]);
                        }

                        foreach ($decoded as $idx => $doc) {
                            if (!is_array($doc)) continue;

                            // Skip if already linked to an admin
                            if (!empty($doc['admin_id'])) continue;

                            // Try to find existing admin by phone or exact name
                            $found = null;
                            if (!empty($doc['phone'])) {
                                $found = Admin::where('phone', trim((string)$doc['phone']))->first();
                            }
                            if (!$found && !empty($doc['email'])) {
                                $found = Admin::where('email', trim((string)$doc['email']))->first();
                            }

                            if (!$found && !empty($doc['name'])) {
                                $nameParts = preg_split('/\s+/', trim((string)$doc['name']), 2);
                                $first = $nameParts[0] ?? $doc['name'];
                                $last = $nameParts[1] ?? '';
                                $found = Admin::where('first_name', $first)->where('last_name', $last)->first();
                            }

                            if (!$found) {
                                // Create a synthetic unique email if none provided
                                $email = !empty($doc['email']) ? trim((string)$doc['email']) : ('doctor+' . time() . rand(1000,9999) . '@local');

                                $admin = Admin::create([
                                    'first_name' => trim((string)($doc['name'] ?? 'Doctor')),
                                    'last_name' => '',
                                    'email' => $email,
                                    'phone' => trim((string)($doc['phone'] ?? '')),
                                    'password' => '12345678',
                                    'role_id' => $doctorRole->id,
                                    'doctor_charge' => 0,
                                    'status' => 'Active',
                                ]);

                                // Create details if available
                                try {
                                    $admin->details()->create([
                                        'gender' => $doc['gender'] ?? 'Male',
                                        'designation_id' => $doc['designation_id'] ?? null,
                                        'department_id' => $doc['department_id'] ?? null,
                                        'specialist_id' => $doc['specialist_id'] ?? null,
                                    ]);
                                } catch (\Throwable $e) {
                                    // ignore details creation errors
                                }

                                $found = $admin;
                                try {
                                    if (method_exists($found, 'assignRole')) {
                                        $found->assignRole($doctorRole->name);
                                    }
                                } catch (\Throwable $_) {
                                }

                                Log::info('WebSetting sync: created admin for featured doctor', ['admin_id' => $found->id, 'name' => $found->first_name ?? $found->name ?? null]);
                            }

                            if ($found) {
                                try {
                                    if (method_exists($found, 'assignRole')) {
                                        $found->assignRole($doctorRole->name);
                                    }
                                } catch (\Throwable $_) {
                                }

                                $found->status = $found->status ?: 'Active';
                                $found->save();

                                $decoded[$idx]['admin_id'] = $found->id;
                                // ensure email/phone are present for later matching
                                $decoded[$idx]['email'] = $decoded[$idx]['email'] ?? $found->email;
                                $decoded[$idx]['phone'] = $decoded[$idx]['phone'] ?? $found->phone;
                                $changed = true;
                                Log::info('WebSetting sync: linked featured doctor to admin', ['admin_id' => $found->id, 'idx' => $idx, 'doc_name' => $doc['name'] ?? null]);
                            }
                        }

                        if (!empty($changed)) {
                            $settingsAfter->website_featured_doctors_json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                            $settingsAfter->save();

                            if (function_exists('get_cached_web_setting')) {
                                get_cached_web_setting(true);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // non-fatal: ignore sync errors
            }

            // Make sure subsequent requests see fresh settings immediately.
            get_cached_web_setting(true);

            $this->storeAdminWorkLog($dataInfo ? $dataInfo->id : WebSetting::latest()->first()->id, 'web_settings', $message);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message);

        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'WebSettingController', 'store', substr($err->getMessage(), 0, 1000));

            return redirect()
                ->back()
                ->with('errorMessage', 'Server error occurred. Please try again.');
        }
    }

    
    public function getSettings()
    {
        try {
            $settings = $this->websettingService->first();
            
            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'Settings retrieved successfully'
            ]);

        } catch (Exception $err) {
            $this->storeSystemError('Backend', 'WebSettingController', 'getSettings', substr($err->getMessage(), 0, 1000));

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings'
            ], 500);
        }
    }

    public function favicon(Request $request)
    {
        $setting = get_cached_web_setting();

        $rawLogo = trim((string) ($setting?->getRawOriginal('logo') ?? ''));
        $rawIcon = trim((string) ($setting?->getRawOriginal('icon') ?? ''));
        $candidate = $rawLogo !== '' ? $rawLogo : $rawIcon;

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        if ($candidate !== '') {
            if (Str::startsWith($candidate, ['http://', 'https://'])) {
                return redirect()->away($candidate);
            }

            $candidate = ltrim($candidate, '/');
            $storagePath = storage_path('app/public/' . $candidate);
            $publicPath = public_path($candidate);

            if (is_file($storagePath)) {
                return response()->file($storagePath, $headers);
            }

            if (is_file($publicPath)) {
                return response()->file($publicPath, $headers);
            }

        }

        $fallback = public_path('favicon.ico');
        if (is_file($fallback)) {
            return response()->file($fallback, $headers);
        }

        abort(404);
    }

    private function syncHistoricalPrefixValues(?WebSetting $oldSettings, ?WebSetting $newSettings): void
    {
        if (!$oldSettings || !$newSettings) {
            return;
        }

        $maps = [
            [
                'field' => 'billing_bill_prefix',
                'targets' => [
                    ['table' => 'billings', 'column' => 'bill_number'],
                    ['table' => 'pathologies', 'column' => 'bill_no'],
                    ['table' => 'radiologies', 'column' => 'bill_no'],
                    ['table' => 'pharmacybills', 'column' => 'bill_no'],
                    ['table' => 'expenses', 'column' => 'bill_number'],
                    ['table' => 'product_returns', 'column' => 'source_bill_no'],
                ],
            ],
            [
                'field' => 'pathology_bill_prefix',
                'targets' => [
                    ['table' => 'pathologies', 'column' => 'pathology_no'],
                ],
            ],
            [
                'field' => 'radiology_bill_prefix',
                'targets' => [
                    ['table' => 'radiologies', 'column' => 'radiology_no'],
                ],
            ],
            [
                'field' => 'pharmacy_bill_prefix',
                'targets' => [
                    ['table' => 'pharmacybills', 'column' => 'pharmacy_no'],
                ],
            ],
        ];

        foreach ($maps as $map) {
            $field = $map['field'];
            $oldPrefix = trim((string) ($oldSettings->{$field} ?? ''));
            $newPrefix = trim((string) ($newSettings->{$field} ?? ''));

            if ($oldPrefix === '' || $newPrefix === '' || $oldPrefix === $newPrefix) {
                continue;
            }

            foreach ($map['targets'] as $target) {
                $this->renamePrefixedColumnValues(
                    $target['table'],
                    $target['column'],
                    $oldPrefix,
                    $newPrefix,
                    $field
                );
            }
        }
    }

    private function renamePrefixedColumnValues(
        string $table,
        string $column,
        string $oldPrefix,
        string $newPrefix,
        string $settingField
    ): void {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $rows = DB::table($table)
            ->select('id', $column)
            ->whereNotNull($column)
            ->where($column, 'like', $oldPrefix . '%')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $rowIds = [];
        $nextValues = [];

        foreach ($rows as $row) {
            $currentValue = (string) ($row->{$column} ?? '');
            if ($currentValue === '' || !str_starts_with($currentValue, $oldPrefix)) {
                continue;
            }

            $rowIds[] = (int) $row->id;
            $nextValues[(int) $row->id] = $newPrefix . substr($currentValue, strlen($oldPrefix));
        }

        if (empty($nextValues)) {
            return;
        }

        $conflicts = DB::table($table)
            ->whereIn($column, array_values($nextValues))
            ->whereNotIn('id', $rowIds)
            ->pluck($column)
            ->all();

        if (!empty($conflicts)) {
            throw new Exception(sprintf(
                'Cannot rename %s in %s.%s because target values already exist.',
                $settingField,
                $table,
                $column
            ));
        }

        foreach ($rows as $row) {
            $rowId = (int) $row->id;
            if (!isset($nextValues[$rowId])) {
                continue;
            }

            $tmpValue = '__TMP__' . strtoupper(Str::random(20));
            DB::table($table)->where('id', $rowId)->update([$column => $tmpValue]);
        }

        foreach ($nextValues as $rowId => $value) {
            DB::table($table)->where('id', $rowId)->update([$column => $value]);
        }
    }
}