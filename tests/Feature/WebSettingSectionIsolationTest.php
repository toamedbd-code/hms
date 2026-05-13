<?php

namespace Tests\Feature;

use App\Models\Admin;
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
            'website_hero_title' => 'Initial CMS Hero',
            'website_about_text' => 'Initial CMS About',
            'website_cta_text' => 'Initial CTA',
        ])));

        $response = $this->post(route('backend.websetting.store'), $this->basePayload([
            'activeSection' => 'general',
            'company_name' => 'Updated General Name',
            // If section isolation fails, these CMS fields would be overwritten.
            'website_hero_title' => 'Should Not Override CMS Hero',
            'website_about_text' => 'Should Not Override CMS About',
            'website_cta_text' => 'Should Not Override CTA',
        ]));

        $response->assertStatus(302);

        $fresh = $initial->fresh();

        $this->assertSame('Updated General Name', $fresh->company_name);
        $this->assertSame('Initial CMS Hero', $fresh->website_hero_title);
        $this->assertSame('Initial CMS About', $fresh->website_about_text);
        $this->assertSame('Initial CTA', $fresh->website_cta_text);
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
