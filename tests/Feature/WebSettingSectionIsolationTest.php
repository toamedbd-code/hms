<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\TestCategory;
use App\Models\WebSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSettingSectionIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This test targets section-wise persistence logic, not auth/permission middleware.
        $this->withoutMiddleware();

        $admin = Admin::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'websetting-admin@example.test',
            'phone' => '01700000000',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);

        $this->actingAs($admin, 'admin');
    }

    public function test_general_save_does_not_overwrite_cms_fields()
    {
        $initial = WebSetting::create($this->dbPayload($this->basePayload([
            'company_name' => 'Initial General Name',
            'pharmacy_tax_number' => 'OLD-PHARMACY-NUMBER',
            'website_hero_title' => 'Initial CMS Hero',
            'website_about_text' => 'Initial CMS About',
            'website_cta_text' => 'Initial CTA',
        ])));

        $response = $this->post(route('backend.websetting.store'), $this->basePayload([
            'activeSection' => 'general',
            'company_name' => 'Updated General Name',
            'pharmacy_tax_number' => 'Should Not Override Pharmacy Settings',
            // If section isolation fails, these CMS fields would be overwritten.
            'website_hero_title' => 'Should Not Override CMS Hero',
            'website_about_text' => 'Should Not Override CMS About',
            'website_cta_text' => 'Should Not Override CTA',
        ]));

        $response->assertStatus(302);

        $fresh = $initial->fresh();

        $this->assertSame('Updated General Name', $fresh->company_name);
        $this->assertSame('OLD-PHARMACY-NUMBER', $fresh->pharmacy_tax_number);
        $this->assertSame('Initial CMS Hero', $fresh->website_hero_title);
        $this->assertSame('Initial CMS About', $fresh->website_about_text);
        $this->assertSame('Initial CTA', $fresh->website_cta_text);
    }

    public function test_general_save_validates_only_general_settings_fields()
    {
        $initial = WebSetting::create($this->dbPayload($this->basePayload([
            'website_hero_title' => 'Initial CMS Hero',
        ])));

        $generalFields = [
            'company_name',
            'company_short_name',
            'hospital_code',
            'address',
            'phone',
            'email',
            'language',
            'date_format',
            'time_zone',
            'currency',
            'currency_symbol',
            'vat_enabled',
            'vat_percent',
            'credit_limit',
            'max_billing_discount_percent',
            'low_stock_threshold',
            'time_format',
            'mobile_app_api_url',
            'mobile_app_primary_color_code',
            'mobile_app_secondary_color_code',
            'login_banner',
            'login_title',
            'login_subtitle',
            'report_title',
            'attendance_device_options',
        ];
        $payload = array_intersect_key($this->basePayload(), array_flip($generalFields));
        $payload['activeSection'] = 'general';
        $payload['company_name'] = 'General Only Update';
        $payload['website_hero_title'] = ['not', 'a', 'string'];

        $this->post(route('backend.websetting.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('General Only Update', $initial->fresh()->company_name);
        $this->assertSame('Initial CMS Hero', $initial->fresh()->website_hero_title);
    }

    public function test_general_save_does_not_sync_featured_doctors()
    {
        WebSetting::create($this->dbPayload($this->basePayload([
            'website_featured_doctors_json' => json_encode([[
                'name' => 'Unsynced Featured Doctor',
                'phone' => '01800000999',
            ]]),
        ])));

        $this->post(route('backend.websetting.store'), $this->basePayload([
            'activeSection' => 'general',
            'company_name' => 'Updated General Name',
        ]))->assertRedirect();

        $this->assertDatabaseMissing('admins', [
            'first_name' => 'Unsynced Featured Doctor',
            'phone' => '01800000999',
        ]);
    }

    public function test_pharmacy_settings_save_persists_the_vat_mushak_number()
    {
        $initial = WebSetting::create($this->dbPayload($this->basePayload()));

        $response = $this->post(route('backend.websetting.store'), $this->basePayload([
            'activeSection' => 'pharmacy',
            'pharmacy_tax_number' => 'BIN-123456789',
        ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('BIN-123456789', $initial->fresh()->pharmacy_tax_number);
    }

    public function test_pharmacy_settings_json_save_returns_without_a_page_redirect()
    {
        $initial = WebSetting::create($this->dbPayload($this->basePayload()));

        $this->postJson(route('backend.websetting.store'), [
            'activeSection' => 'pharmacy',
            'pharmacy_tax_number' => 'BIN-JSON-123',
        ])
            ->assertOk()
            ->assertJsonPath('successMessage', 'Pharmacy settings saved successfully.');

        $this->assertSame('BIN-JSON-123', $initial->fresh()->pharmacy_tax_number);
    }

    public function test_general_settings_page_exposes_the_pharmacy_settings_section()
    {
        $this->get(route('backend.websetting.section.pharmacy'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('activeSection', 'pharmacy'));
    }

    public function test_general_settings_section_includes_categories_for_billing_configuration_fields()
    {
        TestCategory::query()->create([
            'name' => 'Pathology',
            'status' => 'Active',
        ]);

        $this->get(route('backend.websetting.section.general'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeSection', 'general')
                ->where('itemCategories', fn ($categories) => collect($categories)
                    ->contains(fn ($category) => ($category['name'] ?? null) === 'Pathology')));
    }

    public function test_pharmacy_settings_bootstrap_general_settings_when_no_settings_exist()
    {
        $response = $this->post(route('backend.websetting.store'), [
            'activeSection' => 'pharmacy',
            'pharmacy_tax_number' => 'MUSH-987654321',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('MUSH-987654321', WebSetting::query()->latest('id')->value('pharmacy_tax_number'));
    }

    public function test_non_developer_cannot_change_installed_app_branding()
    {
        $initial = WebSetting::create($this->dbPayload($this->basePayload([
            'pwa_app_name' => 'Developer Controlled Name',
            'pwa_app_icon' => 'webSetting/pwa/current.png',
        ])));

        $response = $this->post(route('backend.websetting.store'), $this->basePayload([
            'activeSection' => 'general',
            'pwa_app_name' => 'Unauthorized Name',
        ]));

        $response->assertStatus(302);
        $fresh = $initial->fresh();

        $this->assertSame('Developer Controlled Name', $fresh->pwa_app_name);
        $this->assertSame('webSetting/pwa/current.png', $fresh->getRawOriginal('pwa_app_icon'));
    }

    public function test_non_developer_does_not_receive_installed_app_branding_settings()
    {
        WebSetting::create($this->dbPayload($this->basePayload([
            'pwa_app_name' => 'Developer Controlled Name',
            'pwa_app_icon' => 'webSetting/pwa/current.png',
        ])));

        $this->get(route('backend.websetting.section.general'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isDeveloper', false)
                ->missing('websetting.pwa_app_name')
                ->missing('websetting.pwa_app_icon'));
    }

    public function test_public_install_manifest_uses_configured_app_name_and_icon()
    {
        $setting = WebSetting::create($this->dbPayload($this->basePayload([
            'pwa_app_name' => 'Clinic App',
            'pwa_app_icon' => 'webSetting/pwa/clinic.png',
        ])));

        $response = $this->getJson(route('backend.pwa.manifest'));

        $response->assertOk()
            ->assertJsonPath('name', 'Clinic App')
            ->assertJsonPath('short_name', 'Clinic App')
            ->assertJsonPath('icons.0.src', route('backend.pwa.icon', ['v' => $setting->updated_at->timestamp]));
    }

    public function test_cms_save_does_not_overwrite_general_fields()
    {
        $initial = WebSetting::create($this->dbPayload($this->basePayload([
            'company_name' => 'Initial General Name',
            'company_short_name' => 'IGN',
            'website_hero_title' => 'Initial CMS Hero',
            'website_about_text' => 'Initial CMS About',
            'website_cta_text' => 'Initial CTA',
        ])));

        $response = $this->post(route('backend.websetting.store'), [
            'activeSection' => 'cms',
            'website_hero_title' => 'Updated CMS Hero',
            'website_hero_subtitle' => 'Updated CMS Subtitle',
            'website_about_text' => 'Updated CMS About',
            'website_emergency_phone' => '01800000000',
            'website_enabled' => true,
            'website_cta_text' => 'Updated CMS CTA',
            // Sending a general field intentionally to ensure it remains untouched.
            'company_name' => 'Should Not Override General Name',
        ]);

        $response->assertStatus(302);

        $fresh = $initial->fresh();

        $this->assertSame('Initial General Name', $fresh->company_name);
        $this->assertSame('IGN', $fresh->company_short_name);
        $this->assertSame('Updated CMS Hero', $fresh->website_hero_title);
        $this->assertSame('Updated CMS About', $fresh->website_about_text);
        $this->assertSame('Updated CMS CTA', $fresh->website_cta_text);
    }

    public function test_cms_save_bootstraps_required_general_fields_when_no_settings_exist()
    {
        $response = $this->post(route('backend.websetting.store'), [
            'activeSection' => 'cms',
            'website_enabled' => false,
            'website_hero_title' => 'Welcome',
            'website_cta_text' => 'Book Appointment',
            'website_template' => 'default',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $settings = WebSetting::query()->firstOrFail();
        $this->assertFalse((bool) $settings->website_enabled);
        $this->assertNotSame('', $settings->company_name);
        $this->assertNotSame('', $settings->company_short_name);
        $this->assertNotSame('', $settings->report_title);
    }

    public function test_other_setting_save_bootstraps_required_general_fields_when_no_settings_exist()
    {
        $response = $this->post(route('backend.websetting.store'), [
            'activeSection' => 'other',
            'doctor_restriction_mode' => false,
            'superadmin_visibility' => false,
            'patient_panel' => false,
            'opd_invoice_header_footer' => false,
            'ipd_invoice_header_footer' => false,
            'opd_prescription_header_footer' => false,
            'ipd_prescription_header_footer' => false,
            'scan_type' => 'Barcode',
            'current_theme' => 'emerald',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $settings = WebSetting::query()->firstOrFail();
        $this->assertFalse((bool) $settings->doctor_restriction_mode);
        $this->assertSame('Barcode', $settings->scan_type);
        $this->assertSame('emerald', $settings->current_theme);
        $this->assertNotSame('', $settings->company_name);
        $this->assertNotSame('', $settings->company_short_name);
        $this->assertNotSame('', $settings->report_title);
    }

    public function test_form_prefers_latest_active_web_setting_for_vat_values()
    {
        WebSetting::create($this->dbPayload($this->basePayload([
            'company_name' => 'Older Hospital',
            'vat_enabled' => false,
            'vat_percent' => 0.0,
            'status' => 'Inactive',
        ])));

        WebSetting::create($this->dbPayload($this->basePayload([
            'company_name' => 'Latest Hospital',
            'vat_enabled' => true,
            'vat_percent' => 5.5,
            'status' => 'Active',
        ])));

        $service = app(\App\Services\WebSettingService::class);
        $settings = $service->first();

        $this->assertNotNull($settings);
        $this->assertSame('Latest Hospital', $settings->company_name);
        $this->assertTrue($settings->vat_enabled);
        $this->assertSame(5.5, (float) $settings->vat_percent);
    }

    public function test_general_save_persists_category_discount_cap_settings()
    {
        $response = $this->post(route('backend.websetting.store'), $this->basePayload([
            'activeSection' => 'general',
            'attendance_device_options' => json_encode([
                'billing_discount_cap_by_category_enabled' => true,
                'billing_category_discount_caps' => [
                    'Pathology' => 15,
                    'Medicine' => 10,
                ],
                'billing_room_no_by_category_enabled' => true,
                'billing_category_room_numbers' => [
                    'Pathology' => '101',
                    'Medicine' => 'PH-01',
                ],
                'pharmacy_tax_number_enabled' => false,
            ], JSON_THROW_ON_ERROR),
        ]));

        $response->assertStatus(302);

        $webSetting = WebSetting::query()->latest()->first();
        $this->assertNotNull($webSetting);
        $this->assertTrue((bool) ($webSetting->attendance_device_options['billing_discount_cap_by_category_enabled'] ?? false));
        $this->assertSame(15.0, (float) ($webSetting->attendance_device_options['billing_category_discount_caps']['Pathology'] ?? 0));
        $this->assertSame(10.0, (float) ($webSetting->attendance_device_options['billing_category_discount_caps']['Medicine'] ?? 0));
        $this->assertTrue((bool) ($webSetting->attendance_device_options['billing_room_no_by_category_enabled'] ?? false));
        $this->assertSame('101', $webSetting->attendance_device_options['billing_category_room_numbers']['Pathology'] ?? null);
        $this->assertSame('PH-01', $webSetting->attendance_device_options['billing_category_room_numbers']['Medicine'] ?? null);
        $this->assertFalse((bool) ($webSetting->attendance_device_options['pharmacy_tax_number_enabled'] ?? true));
    }

    public function test_module_save_persists_attendance_device_settings_and_registers_device()
    {
        $initial = WebSetting::create($this->dbPayload($this->basePayload([
            'company_name' => 'Initial General Name',
            'attendance_device_enabled' => false,
            'attendance_device_identifier' => '',
        ])));

        $response = $this->post(route('backend.websetting.store'), [
            'activeSection' => 'module',
            'attendance_device_enabled' => true,
            'attendance_device_type' => 'fingerprint',
            'attendance_device_identifier' => 'zkteco-k40-1',
            'attendance_device_ip' => '',
            'attendance_device_port' => '4370',
            'attendance_device_secret' => 'secret123',
            'attendance_device_options' => json_encode([
                'sync_mode' => 'batched',
                'sync_interval_minutes' => 10,
                'zkteco' => [
                    'enabled' => true,
                    'protocol' => 'pull_sync',
                ],
                'webhook' => [
                    'payload_device_key' => 'device_id',
                ],
            ], JSON_THROW_ON_ERROR),
            'company_name' => 'Should Not Override General Name',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $fresh = $initial->fresh();
        $this->assertSame('Initial General Name', $fresh->company_name);
        $this->assertTrue((bool) $fresh->attendance_device_enabled);
        $this->assertSame('fingerprint', $fresh->attendance_device_type);
        $this->assertSame('zkteco-k40-1', $fresh->attendance_device_identifier);
        $this->assertSame('4370', (string) $fresh->attendance_device_port);
        $this->assertSame('secret123', $fresh->attendance_device_secret);
        $this->assertSame('batched', $fresh->attendance_device_options['sync_mode'] ?? null);
        $this->assertSame('pull_sync', $fresh->attendance_device_options['zkteco']['protocol'] ?? null);

        $device = \App\Models\AttendanceDevice::where('identifier', 'zkteco-k40-1')->first();
        $this->assertNotNull($device);
        $this->assertSame('Active', $device->status);
        $this->assertSame('secret123', $device->secret);
        $this->assertSame('4370', (string) ($device->meta['port'] ?? ''));
    }

    public function test_empty_sidebar_save_does_not_crash_when_no_web_settings_row_exists()
    {
        $response = $this->post(route('backend.websetting.store'), [
            'activeSection' => 'sidebar',
        ]);

        $response->assertStatus(302);
    }

    private function basePayload(array $overrides = []): array
    {
        $payload = [
            'company_name' => 'ToaMed Hospital',
            'company_short_name' => 'TMH',
            'hospital_code' => 'TMH01',
            'address' => 'Mirpur, Dhaka',
            'phone' => '01919592638',
            'email' => 'toamedbd@example.com',
            'language' => 'English',
            'date_format' => 'dd-mm-yyyy',
            'time_zone' => '(GMT+06:00) Asia, Dhaka',
            'currency' => 'BDT',
            'currency_symbol' => 'Tk.',
            'credit_limit' => 10000,
            'max_billing_discount_percent' => 100,
            'low_stock_threshold' => 10,
            'time_format' => '12 Hour',
            'mobile_app_api_url' => '',
            'mobile_app_primary_color_code' => '444444',
            'mobile_app_secondary_color_code' => 'ffffff',
            'doctor_restriction_mode' => false,
            'superadmin_visibility' => false,
            'patient_panel' => false,
            'opd_invoice_header_footer' => false,
            'ipd_invoice_header_footer' => false,
            'opd_prescription_header_footer' => false,
            'ipd_prescription_header_footer' => false,
            'scan_type' => 'Barcode',
            'current_theme' => 'default',
            'sms_enabled' => false,
            'sms_api_url' => null,
            'sms_api_key' => '',
            'sms_sender_id' => '',
            'sms_route' => '',
            'sms_is_unicode' => false,
            'sms_additional_params' => '',
            'personal_bkash_number' => '',
            'personal_nagad_number' => '',
            'ipd_no_prefix' => 'IPDN',
            'opd_no_prefix' => 'OPDN',
            'ipd_prescription_prefix' => 'IPDP',
            'opd_prescription_prefix' => 'OPDP',
            'appointment_prefix' => 'APPN',
            'pharmacy_bill_prefix' => 'PHAB',
            'billing_bill_prefix' => 'BILL',
            'operation_reference_no_prefix' => 'OTRN',
            'blood_bank_bill_prefix' => 'BLBB',
            'ambulance_call_bill_prefix' => 'AMCB',
            'radiology_bill_prefix' => 'RADB',
            'pathology_bill_prefix' => 'Bill',
            'opd_checkup_id_prefix' => 'OCID',
            'pharmacy_purchase_no_prefix' => 'PHPN',
            'transaction_id_prefix' => 'TRID',
            'birth_record_reference_no_prefix' => 'BRRN',
            'death_record_reference_no_prefix' => 'DRRN',
            'report_title' => 'Mirpur, Dhaka',
            'website_hero_title' => 'Welcome to ToaMed Hospital',
            'website_hero_subtitle' => 'Compassionate care',
            'website_about_text' => 'About content',
            'website_emergency_phone' => '01919592638',
            'website_enabled' => true,
            'website_cta_text' => 'Book Appointment',
            'website_featured_doctors_json' => '[]',
            'website_services_json' => '["Emergency"]',
            'website_facilities_json' => '["Pharmacy"]',
            'website_testimonials_en_json' => '[]',
            'website_testimonials_bn_json' => '[]',
            'attendance_device_enabled' => true,
            'attendance_device_type' => 'both',
            'attendance_device_identifier' => '',
            'attendance_device_ip' => null,
            'attendance_device_port' => '',
            'attendance_device_secret' => '',
            'attendance_device_options' => '{}',
            'activeSection' => 'general',
        ];

        return array_merge($payload, $overrides);
    }

    private function dbPayload(array $payload): array
    {
        unset($payload['activeSection']);

        return $payload;
    }
}
