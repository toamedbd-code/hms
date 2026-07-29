<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebSettingRequest;
use App\Models\Menu;
use App\Models\WebSetting;
use Illuminate\Support\Facades\DB;
use App\Services\WebSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
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
        $this->middleware('permission:websetting-add|cms-setting|general-setting-add|sidebar-setting', ['only' => ['create', 'section', 'module', 'store']]);
    }

    /**
     * Update or add a key in the .env file.
     */
    private function setEnvValue(string $key, string $value): bool
    {
        $envPath = base_path('.env');
        if (!is_file($envPath) || !is_writable($envPath)) {
            return false;
        }

        $content = file_get_contents($envPath);
        $escaped = str_replace('"', '\\"', $value);
        $newLine = $key . '="' . $escaped . '"';

        if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $content)) {
            $content = preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $newLine, $content);
        } else {
            $content = rtrim($content, "\n") . PHP_EOL . $newLine . PHP_EOL;
        }

        file_put_contents($envPath, $content);
        // Also update runtime environment for immediate effect in current process
        try {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        } catch (\Throwable $_) {
            // ignore runtime update errors
        }

        return true;
    }

    private function deleteStoredWebSettingFile(?string $existingValue): void
    {
        if (!is_string($existingValue) || trim($existingValue) === '') {
            return;
        }

        $normalized = trim($existingValue);

        if (Str::startsWith($normalized, ['http://', 'https://'])) {
            $path = parse_url($normalized, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $normalized = $path;
            }
        }

        if (strpos($normalized, 'storage/') !== false) {
            $normalized = preg_replace('#^.*storage/#', '', $normalized);
        }

        $normalized = trim($normalized, '/');

        if ($normalized === '') {
            return;
        }

        if (Storage::disk('public')->exists($normalized)) {
            Storage::disk('public')->delete($normalized);
        }
    }

    public function create(Request $request)
    {
        $requestedSection = trim((string) $request->query('section', ''));
        $requestedModule = trim((string) $request->query('module', ''));
        $singleSectionMode = $requestedSection !== '';

        return $this->renderForm($requestedSection, $singleSectionMode, $requestedModule);
    }

    public function section(Request $request, string $section)
    {
        $requestedModule = trim((string) $request->query('module', ''));
        return $this->renderForm($section, true, $requestedModule);
    }

    public function module(Request $request)
    {
        $requestedModule = trim((string) $request->route('module', $request->query('module', '')));
        return $this->renderForm('module', true, $requestedModule);
    }

    private function renderForm(?string $requestedSection = null, bool $singleSectionMode = false, ?string $requestedModule = null)
    {
        $websetting = $this->websettingService->first() ?? null;

        $availableSections = ['general', 'cms', 'sms', 'prefix', 'module', 'other', 'sidebar'];
        $normalizedSection = strtolower(trim((string) $requestedSection));
        $activeSection = in_array($normalizedSection, $availableSections, true)
            ? $normalizedSection
            : 'general';

        $availableModules = ['attendance', 'pathology', 'payroll', 'reporting'];
        $normalizedModule = strtolower(trim((string) $requestedModule));
        $activeModule = in_array($normalizedModule, $availableModules, true)
            ? $normalizedModule
            : '';

        $pageTitleMap = [
            'general' => 'General Setting',
            'cms' => 'CMS Setting',
            'sms' => 'SMS Setting',
            'module' => 'Module Setting',
            'prefix' => 'Prefix Setting',
            'other' => 'Other Setting',
            'sidebar' => 'Sidebar Menu Order',
        ];

        $pageTitle = $pageTitleMap[$activeSection] ?? 'General Setting';
        if ($activeSection === 'module') {
            $pageTitle = match ($activeModule) {
                'attendance' => 'Attendance Module Setting',
                'pathology' => 'Machine Integration Setting',
                'payroll' => 'Payroll Module Setting',
                'reporting' => 'Report Settings',
                default => 'Module Setting',
            };
        }

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
            'websetting' => fn() => $websetting ? $this->decorateVatFallback($websetting) : $websetting,
            'sidebarMenus' => fn() => Menu::whereNull('parent_id')
                ->whereNull('deleted_at')
                ->where('status', 'Active')
                ->orderBy('sorting', 'ASC')
                ->orderBy('id', 'ASC')
                ->get(['id', 'name', 'sorting'])
                ->map(function ($menu) {
                    return [
                        'id' => $menu->id,
                        'name' => $menu->name,
                        'sorting' => $menu->sorting,
                    ];
                })->values(),
            'pageTitle' => fn() => $pageTitle,
            'activeSection' => fn() => $activeSection,
            'activeModule' => fn() => $activeModule,
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

    /**
     * If VAT columns are missing in DB, read fallback values from attendance_device_options
     * and attach them as properties so frontend form can display them.
     */
    private function decorateVatFallback($websetting)
    {
        try {
            $hasVatCols = Schema::hasColumn('web_settings', 'vat_enabled') && Schema::hasColumn('web_settings', 'vat_percent');
            if ($hasVatCols) {
                return $websetting;
            }

            $opts = $websetting->attendance_device_options ?? [];
            if (is_string($opts)) {
                $decoded = json_decode($opts, true);
                $opts = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
            }

            $websetting->vat_enabled = $opts['vat_enabled'] ?? ($websetting->vat_enabled ?? false);
            $websetting->vat_percent = isset($opts['vat_percent']) ? (float) $opts['vat_percent'] : ($websetting->vat_percent ?? 0.0);
        } catch (\Throwable $_) {
            // ignore and return original
        }

        return $websetting;
    }

    public function store(WebSettingRequest $request)
    {

        DB::beginTransaction();
        try {
            $data = $request->validated();
            $settings = $this->websettingService->first();

            // Persist VAT values directly in DB when the columns exist, otherwise store them
            // in attendance_device_options as a fallback so the values survive page reloads.
            $vatEnabledProvided = $request->has('vat_enabled');
            $vatPercentProvided = $request->has('vat_percent');
            if ((!Schema::hasColumn('web_settings', 'vat_enabled') || !Schema::hasColumn('web_settings', 'vat_percent')) && ($vatEnabledProvided || $vatPercentProvided)) {
                $existingOptions = $settings?->attendance_device_options ?? [];
                if (is_string($existingOptions)) {
                    $decoded = json_decode($existingOptions, true);
                    $existingOptions = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
                }
                $existingOptions = is_array($existingOptions) ? $existingOptions : [];
                if ($vatEnabledProvided) {
                    $existingOptions['vat_enabled'] = filter_var($request->input('vat_enabled'), FILTER_VALIDATE_BOOLEAN);
                }
                if ($vatPercentProvided) {
                    $existingOptions['vat_percent'] = (float) $request->input('vat_percent');
                }
                $data['attendance_device_options'] = json_encode($existingOptions, JSON_UNESCAPED_UNICODE);
                // ensure vat keys won't be used further down as DB columns
                unset($data['vat_enabled'], $data['vat_percent']);
            }
            $section = strtolower(trim((string) ($request->input('activeSection') ?? $request->input('section') ?? 'general')));

            // Section-wise field whitelist to prevent cross-section overwrite.
            $generalFields = [
                'company_name','company_short_name','hospital_code','address','phone','email','logo','icon','language','date_format','time_zone','currency','currency_symbol','vat_enabled','vat_percent','credit_limit','max_billing_discount_percent','low_stock_threshold','time_format','mobile_app_api_url','mobile_app_primary_color_code','mobile_app_secondary_color_code','mobile_app_logo','login_banner','login_title','login_subtitle','report_title',
            ];
            $cmsFields = [
                'website_hero_title','website_hero_subtitle','website_about_text','website_emergency_phone','website_enabled','website_cta_text','website_featured_doctors_json','website_featured_doctor_images','website_services_json','website_facilities_json','website_testimonials_en_json','website_testimonials_bn_json','website_template',
            ];
            $prefixFields = [
                'ipd_no_prefix','opd_no_prefix','ipd_prescription_prefix','opd_prescription_prefix','appointment_prefix','pharmacy_bill_prefix','billing_bill_prefix','operation_reference_no_prefix','blood_bank_bill_prefix','ambulance_call_bill_prefix','radiology_bill_prefix','pathology_bill_prefix','opd_checkup_id_prefix','pharmacy_purchase_no_prefix','transaction_id_prefix','birth_record_reference_no_prefix','death_record_reference_no_prefix',
            ];
            $smsFields = [
                'sms_enabled','sms_api_url','sms_api_key','sms_sender_id','sms_route','sms_is_unicode','sms_additional_params','personal_bkash_number','personal_nagad_number',
            ];
            $moduleFields = [
                'attendance_device_enabled','attendance_device_type','attendance_device_identifier','attendance_device_ip','attendance_device_port','attendance_device_secret','attendance_device_options',
            ];
            $otherFields = [
                'doctor_restriction_mode','superadmin_visibility','patient_panel','opd_invoice_header_footer','ipd_invoice_header_footer','opd_prescription_header_footer','ipd_prescription_header_footer','scan_type','current_theme',
            ];

            $sectionFieldMap = [
                'general' => $generalFields,
                'cms' => $cmsFields,
                'prefix' => $prefixFields,
                'sms' => $smsFields,
                'module' => $moduleFields,
                'other' => $otherFields,
                'sidebar' => ['sidebar_menu_order'],
            ];

            if (!array_key_exists($section, $sectionFieldMap)) {
                $section = 'general';
            }

            $sidebarOrder = $request->input('sidebar_menu_order', []);
            $data = array_intersect_key($data, array_flip($sectionFieldMap[$section]));

            if ($section === 'sidebar') {
                unset($data['sidebar_menu_order']);
            }

            // নিচের কোড অপরিবর্তিত থাকবে (ফাইল আপলোড, doctor image, template ইত্যাদি)
            // If a website_template was provided, persist it inside attendance_device_options
            // to avoid requiring a DB migration for a dedicated column.
            if (isset($data['website_template'])) {
                $normalizedTemplate = trim((string) $data['website_template']);
                $normalizedTemplate = preg_replace('/\.blade(\.php)?$/i', '', $normalizedTemplate);

                $existingOptions = $settings?->attendance_device_options ?? [];
                if (is_string($existingOptions)) {
                    $decoded = json_decode($existingOptions, true);
                    $existingOptions = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
                }

                $existingOptions = is_array($existingOptions) ? $existingOptions : [];
                $existingOptions['website_template'] = $normalizedTemplate;
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

            if ($request->hasFile('logo')) {
                if ($settings && $settings->logo) {
                    $this->deleteStoredWebSettingFile($settings->logo);
                }

                $data['logo'] = $this->imageUpload($request->file('logo'), 'webSetting');
            }

            if ($request->hasFile('icon')) {
                if ($settings && $settings->icon) {
                    $this->deleteStoredWebSettingFile($settings->icon);
                }

                $data['icon'] = $this->imageUpload($request->file('icon'), 'webSetting');
            }

            if ($request->hasFile('mobile_app_logo')) {
                if ($settings && $settings->mobile_app_logo) {
                    $this->deleteStoredWebSettingFile($settings->mobile_app_logo);
                }

                $data['mobile_app_logo'] = $this->imageUpload($request->file('mobile_app_logo'), 'webSetting');
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
                // Remove env-only fields so DB update doesn't fail if columns are absent
                unset($data['login_banner'], $data['login_title'], $data['login_subtitle']);
                $updatedSettings = $this->websettingService->update($data, $dataInfo->id);
                $this->syncHistoricalPrefixValues($oldSettingsSnapshot, $updatedSettings);
                $message = ($section === 'sidebar') ? 'Sidebar menu order updated successfully' : 'General settings updated successfully';
            } else {
                unset($data['login_banner'], $data['login_title'], $data['login_subtitle']);
                if (!empty($data)) {
                    WebSetting::create($data);
                    $message = 'General settings created successfully';
                } else {
                    $message = 'Sidebar menu order updated successfully';
                }
            }

            if ($section === 'sidebar' && is_array($sidebarOrder)) {
                foreach ($sidebarOrder as $index => $menuId) {
                    $menuId = (int) $menuId;
                    if ($menuId <= 0) {
                        continue;
                    }
                    Menu::whereNull('parent_id')
                        ->where('id', $menuId)
                        ->update(['sorting' => $index + 1]);
                }
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

            // Refresh cache will be executed after transaction commit to
            // ensure DB changes are visible to all connections.
            // (moved to after DB::commit below)

            $this->storeAdminWorkLog($dataInfo ? $dataInfo->id : WebSetting::latest()->first()->id, 'web_settings', $message);

            DB::commit();

            // After commit, refresh cached web setting and clear any
            // session fallback that might cause stale company info to appear.
            try {
                get_cached_web_setting(true);
            } catch (\Throwable $e) {
                Log::warning('Failed to refresh websetting cache after commit: ' . $e->getMessage());
            }

            try {
                session()->forget('companyInfo');
            } catch (\Throwable $e) {
                Log::warning('Failed to forget companyInfo session after websetting update: ' . $e->getMessage());
            }

            try {
                Log::info('WebSetting updated: cache refreshed and companyInfo cleared', [
                    'company_name' => $settingsAfter->company_name ?? null,
                    'id' => $settingsAfter->id ?? null,
                ]);
            } catch (\Throwable $_) {
                // ignore logging failures
            }

            // Persist optional login texts into .env so admin can edit them from WebSetting form
            try {
                $envUpdates = [
                    'LOGIN_BANNER' => $request->input('login_banner', null),
                    'LOGIN_TITLE' => $request->input('login_title', null),
                    'LOGIN_SUBTITLE' => $request->input('login_subtitle', null),
                ];

                foreach ($envUpdates as $envKey => $envValue) {
                    if (!is_null($envValue)) {
                        $this->setEnvValue($envKey, (string) $envValue);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to update .env login texts: ' . $e->getMessage());
            }

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

        $rawIcon = trim((string) ($setting?->getRawOriginal('icon') ?? ''));
        $candidate = $rawIcon;

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        if ($candidate !== '') {
            if (Str::startsWith($candidate, ['http://', 'https://'])) {
                return redirect()->away($candidate);
            }

            $candidate = trim(str_replace('\\', '/', $candidate), '/');
            $candidate = preg_replace('#^(?:public/storage/|storage/app/public/|storage/)#', '', $candidate);

            $storagePath = storage_path('app/public/' . $candidate);
            $publicPath = public_path($candidate);
            $publicStoragePath = public_path('storage/' . $candidate);

            if (is_file($storagePath)) {
                return response()->file($storagePath, $headers);
            }

            if (is_file($publicStoragePath)) {
                return response()->file($publicStoragePath, $headers);
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