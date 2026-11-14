<?php

namespace Tests\Unit;

use App\Services\PathologyMachineIntegrationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PathologyMachineIntegrationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $this->app['db']->purge('sqlite');

        Schema::create('web_settings', function (Blueprint $table) {
            $table->id();
            $table->json('attendance_device_options')->nullable();
            $table->timestamps();
        });
        Schema::create('pathologies', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no')->unique();
            $table->string('pathology_no')->unique();
            $table->string('case_id');
            $table->date('date')->nullable();
            $table->json('tests')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_id');
            $table->unsignedBigInteger('item_id');
            $table->string('category');
            $table->string('item_name');
            $table->boolean('requires_sample')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->string('test_name');
            $table->string('test_short_name')->nullable();
            $table->softDeletes();
        });
        Schema::create('pathology_test_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pathology_test_id');
            $table->unsignedBigInteger('test_parameter_id')->nullable();
            $table->unsignedBigInteger('pathology_unit_id')->nullable();
            $table->string('name');
        });
        Schema::create('pathologyparameters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('pathologyunits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('bill_item_parameter_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_item_id');
            $table->unsignedBigInteger('pathology_test_parameter_id')->nullable();
            $table->string('name')->nullable();
            $table->text('value')->nullable();
            $table->string('unit')->nullable();
            $table->timestamps();
        });
        Schema::create('pathology_machine_integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->nullable();
            $table->string('event');
            $table->string('level');
            $table->string('source_format')->nullable();
            $table->string('communication_mode')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->timestamps();
        });

        DB::table('web_settings')->insert([
            'attendance_device_options' => json_encode([
                'pathology' => [
                    'enabled' => true,
                    'mapping' => [
                        'sample_id_key' => 'sample_id',
                        'test_code_key' => 'test_code',
                        'result_value_key' => 'result_value',
                        'result_unit_key' => 'unit',
                        'reference_range_key' => 'reference_range',
                        'result_time_key' => 'result_time',
                        'test_code_aliases_json' => '{}',
                    ],
                ],
            ]),
        ]);
        DB::table('billings')->insert(['id' => 1, 'bill_number' => 'LAB-001']);
        DB::table('bill_items')->insert([
            'id' => 77,
            'billing_id' => 1,
            'item_id' => 9,
            'category' => 'Pathology',
            'item_name' => 'Complete Blood Count',
            'requires_sample' => true,
        ]);
        DB::table('tests')->insert([
            'id' => 9,
            'test_name' => 'Complete Blood Count',
            'test_short_name' => 'CBC',
        ]);
        DB::table('pathology_test_parameters')->insert([
            'id' => 3,
            'pathology_test_id' => 9,
            'name' => 'Hemoglobin',
        ]);
        DB::table('pathologies')->insert([
            'bill_no' => 'LAB-001',
            'pathology_no' => 'PATH-001',
            'case_id' => 'CASE-001',
            'date' => now()->toDateString(),
            'tests' => json_encode([[
                'test_id' => 9,
                'test_name' => 'Complete Blood Count',
                'test_short_name' => 'CBC',
            ]]),
        ]);
    }

    public function test_barcode_accession_imports_analyzer_value_to_the_billed_test_parameter(): void
    {
        $response = app(PathologyMachineIntegrationService::class)->handleWebhook($this->machineRequest([
            'sample_id' => 'LAB-001',
            'test_code' => 'HGB',
            'result_value' => '13.8',
            'unit' => 'g/dL',
        ]));

        $this->assertTrue($response['ok']);
        $this->assertSame(0, $response['meta']['unmatched_results']);
        $this->assertDatabaseHas('bill_item_parameter_results', [
            'bill_item_id' => 77,
            'pathology_test_parameter_id' => 3,
            'value' => '13.8',
            'unit' => 'g/dL',
        ]);
    }

    public function test_bill_number_accession_matches_exactly_and_rejects_partial_barcode_matches(): void
    {
        $service = app(PathologyMachineIntegrationService::class);
        $validResponse = $service->handleWebhook($this->machineRequest([
            'sample_id' => 'LAB-001',
            'test_code' => 'HGB',
            'result_value' => '13.8',
        ]));

        $this->assertSame(0, $validResponse['meta']['unmatched_results']);
        $this->assertDatabaseHas('bill_item_parameter_results', [
            'bill_item_id' => 77,
            'pathology_test_parameter_id' => 3,
            'value' => '13.8',
        ]);

        DB::table('bill_item_parameter_results')->delete();
        $invalidResponse = $service->handleWebhook($this->machineRequest([
            'sample_id' => 'OTHER-LAB-001-77',
            'test_code' => 'HGB',
            'result_value' => '99',
        ]));

        $this->assertSame(1, $invalidResponse['meta']['unmatched_results']);
        $this->assertDatabaseCount('bill_item_parameter_results', 0);
    }

    public function test_common_hematology_codes_are_saved_to_generated_report_parameters(): void
    {
        DB::table('pathology_test_parameters')->delete();

        $response = app(PathologyMachineIntegrationService::class)->handleWebhook($this->machineRequest([
            'sample_id' => 'LAB-001',
            'test_code' => 'WBC',
            'result_value' => '7200',
            'unit' => '/uL',
        ]));

        $this->assertSame(0, $response['meta']['unmatched_results']);
        $this->assertDatabaseHas('bill_item_parameter_results', [
            'bill_item_id' => 77,
            'pathology_test_parameter_id' => null,
            'name' => 'Total WBC Count',
            'value' => '7200',
        ]);
    }

    public function test_rayto_percent_and_absolute_codes_remain_distinct(): void
    {
        DB::table('pathology_test_parameters')->insert([
            [
                'id' => 4,
                'pathology_test_id' => 9,
                'name' => 'Absolute Lymphocytes',
            ],
            [
                'id' => 5,
                'pathology_test_id' => 9,
                'name' => 'Lymphocytes',
            ],
        ]);

        $response = app(PathologyMachineIntegrationService::class)->handleWebhook($this->machineRequest([
            'sample_id' => 'LAB-001',
            'results' => [
                ['test_code' => 'LYM#', 'result_value' => '2.1', 'unit' => '10^9/L'],
                ['test_code' => 'LYM%', 'result_value' => '31.0', 'unit' => '%'],
            ],
        ]));

        $this->assertSame(0, $response['meta']['unmatched_results']);
        $this->assertDatabaseHas('bill_item_parameter_results', [
            'bill_item_id' => 77,
            'pathology_test_parameter_id' => 4,
            'value' => '2.1',
        ]);
        $this->assertDatabaseHas('bill_item_parameter_results', [
            'bill_item_id' => 77,
            'pathology_test_parameter_id' => 5,
            'value' => '31.0',
        ]);
    }

    public function test_rayto_analyzer_only_cbc_components_are_saved_for_report_entry(): void
    {
        $response = app(PathologyMachineIntegrationService::class)->handleWebhook($this->machineRequest([
            'sample_id' => 'LAB-001',
            'test_code' => 'MID#',
            'result_value' => '0.5',
            'unit' => '10^9/L',
        ]));

        $this->assertSame(0, $response['meta']['unmatched_results']);
        $this->assertDatabaseHas('bill_item_parameter_results', [
            'bill_item_id' => 77,
            'pathology_test_parameter_id' => null,
            'name' => 'MID Count',
            'value' => '0.5',
            'unit' => '10^9/L',
        ]);
    }

    private function machineRequest(array $result): Request
    {
        $payload = isset($result['results'])
            ? $result
            : ['results' => [$result]];

        return Request::create(
            '/api/pathology/machine/webhook',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
    }
}
