<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\WebSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportSettingPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $admin = Admin::create([
            'first_name' => 'Report',
            'last_name' => 'Settings',
            'email' => 'report-settings@example.test',
            'phone' => '01700000000',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $this->actingAs($admin, 'admin');
    }

    public function test_report_settings_can_be_saved_before_web_settings_exist(): void
    {
        $this->assertDatabaseCount('web_settings', 0);

        $this->post(route('backend.report-setting.update'), [
            'barcode_scale' => 2.2,
            'barcode_height' => 52,
            'report_show_header_footer' => false,
            'report_full_page_institution_pad' => true,
            'report_margin_top' => 2,
            'report_margin_left' => 10,
            'report_margin_right' => 20,
            'report_header_height' => 30,
            'signature_margin_top' => 90,
            'signature_margin_left' => 25,
            'cbc_signature_margin_top' => -7,
            'reporting_departments' => [
                'xray' => [
                    'machine_info_line' => '',
                    'machine_info_line_enabled' => true,
                    'show_header_footer' => true,
                    'full_page_institution_pad' => true,
                    'layout' => [
                        'page_margin_top' => 2,
                        'header_height' => 30,
                    ],
                    'signature' => [
                        'margin_top' => 90,
                        'margin_left' => 25,
                    ],
                ],
                'ultrasound' => [
                    'machine_info_line' => '',
                    'machine_info_line_enabled' => true,
                    'show_header_footer' => true,
                    'full_page_institution_pad' => false,
                    'layout' => [],
                    'signature' => [],
                ],
            ],
        ])->assertRedirect();

        $settings = WebSetting::query()->firstOrFail();
        $companyName = trim((string) config('app.name', 'Hospital Management System'));
        $companyName = $companyName !== '' ? $companyName : 'Hospital Management System';

        $this->assertSame($companyName, $settings->company_name);
        $this->assertSame(strtoupper(substr($companyName, 0, 10)), $settings->company_short_name);
        $this->assertSame($companyName, $settings->report_title);
        $this->assertSame(2.2, (float) $settings->barcode_scale);
        $this->assertSame(
            true,
            data_get($settings->attendance_device_options, 'reporting.full_page_institution_pad')
        );
        $this->assertFalse(data_get($settings->attendance_device_options, 'reporting.show_header'));
        $this->assertFalse(data_get($settings->attendance_device_options, 'reporting.show_footer'));
        $this->assertSame(
            -26,
            data_get($settings->attendance_device_options, 'reporting.cbc.signature.margin_top')
        );
        $this->assertSame(8, data_get($settings->attendance_device_options, 'reporting.layout.page_margin_top'));
        $this->assertSame(38, data_get($settings->attendance_device_options, 'reporting.layout.page_margin_left'));
        $this->assertSame(76, data_get($settings->attendance_device_options, 'reporting.layout.page_margin_right'));
        $this->assertSame(113, data_get($settings->attendance_device_options, 'reporting.layout.header_height'));
        $this->assertSame(340, data_get($settings->attendance_device_options, 'reporting.signature.margin_top'));
        $this->assertSame(94, data_get($settings->attendance_device_options, 'reporting.signature.margin_left'));
        $this->assertSame(8, data_get($settings->attendance_device_options, 'reporting.departments.xray.layout.page_margin_top'));
        $this->assertSame(113, data_get($settings->attendance_device_options, 'reporting.departments.xray.layout.header_height'));
        $this->assertTrue(data_get($settings->attendance_device_options, 'reporting.departments.xray.show_header'));
        $this->assertTrue(data_get($settings->attendance_device_options, 'reporting.departments.xray.show_footer'));
        $this->assertTrue(data_get($settings->attendance_device_options, 'reporting.departments.xray.full_page_institution_pad'));
        $this->assertSame(340, data_get($settings->attendance_device_options, 'reporting.departments.xray.signature.margin_top'));
        $this->assertSame(94, data_get($settings->attendance_device_options, 'reporting.departments.xray.signature.margin_left'));

        $this->post(route('backend.report-setting.update'), [
            'barcode_scale' => 2.2,
            'barcode_height' => 52,
            'report_layout_mode_scope' => 'xray',
            'report_layout_mode' => 'header_footer',
        ])->assertRedirect();

        $settings->refresh();
        $this->assertFalse(data_get($settings->attendance_device_options, 'reporting.departments.xray.full_page_institution_pad'));
        $this->assertTrue(data_get($settings->attendance_device_options, 'reporting.departments.xray.show_header'));
        $this->assertTrue(data_get($settings->attendance_device_options, 'reporting.departments.xray.show_footer'));
        $this->assertSame(8, data_get($settings->attendance_device_options, 'reporting.departments.xray.layout.page_margin_top'));
        $this->assertSame(113, data_get($settings->attendance_device_options, 'reporting.departments.xray.layout.header_height'));
        $this->assertTrue(data_get($settings->attendance_device_options, 'reporting.full_page_institution_pad'));
    }

    public function test_saved_signature_removal_deletes_global_and_department_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('report-signatures/global.png', 'global-signature');
        Storage::disk('public')->put('report-signatures/global-sample.png', 'global-sample-signature');
        Storage::disk('public')->put('report-signatures/global-pathologist.png', 'global-pathologist-signature');
        Storage::disk('public')->put('report-signatures/xray.png', 'xray-signature');
        Storage::disk('public')->put('report-signatures/xray-sample.png', 'xray-sample-signature');
        Storage::disk('public')->put('report-signatures/xray-pathologist.png', 'xray-pathologist-signature');

        $settings = WebSetting::query()->create([
            'company_name' => 'Report Center',
            'company_short_name' => 'RC',
            'phone' => '01700000001',
            'report_title' => 'Report Center',
            'status' => 'Active',
            'technologist_signature' => 'report-signatures/global.png',
            'sample_collected_by_signature' => 'report-signatures/global-sample.png',
            'pathologist_signature' => 'report-signatures/global-pathologist.png',
            'attendance_device_options' => [
                'reporting' => [
                    'departments' => [
                        'xray' => [
                            'technologist_signature' => 'report-signatures/xray.png',
                            'sample_collected_by_signature' => 'report-signatures/xray-sample.png',
                            'pathologist_signature' => 'report-signatures/xray-pathologist.png',
                        ],
                    ],
                ],
            ],
        ]);

        $this->post(route('backend.report-setting.update'), [
            'barcode_scale' => 2.2,
            'barcode_height' => 52,
            'remove_technologist_signature' => true,
            'reporting_departments' => [
                'xray' => [
                    'remove_technologist_signature' => true,
                ],
            ],
        ])->assertRedirect();

        $settings->refresh();
        $this->assertNull($settings->technologist_signature);
        $this->assertSame('report-signatures/global-sample.png', $settings->sample_collected_by_signature);
        $this->assertSame('report-signatures/global-pathologist.png', $settings->pathologist_signature);
        $this->assertNull(data_get($settings->attendance_device_options, 'reporting.departments.xray.technologist_signature'));
        $this->assertSame('report-signatures/xray-sample.png', data_get($settings->attendance_device_options, 'reporting.departments.xray.sample_collected_by_signature'));
        $this->assertSame('report-signatures/xray-pathologist.png', data_get($settings->attendance_device_options, 'reporting.departments.xray.pathologist_signature'));
        $this->assertArrayNotHasKey(
            'remove_technologist_signature',
            data_get($settings->attendance_device_options, 'reporting.departments.xray', [])
        );
        Storage::disk('public')->assertMissing('report-signatures/global.png');
        Storage::disk('public')->assertMissing('report-signatures/xray.png');
        Storage::disk('public')->assertExists('report-signatures/global-sample.png');
        Storage::disk('public')->assertExists('report-signatures/global-pathologist.png');
        Storage::disk('public')->assertExists('report-signatures/xray-sample.png');
        Storage::disk('public')->assertExists('report-signatures/xray-pathologist.png');
    }
}
