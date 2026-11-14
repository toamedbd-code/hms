<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BillItem;
use App\Models\Billing;
use App\Models\PathologyTestParameter;
use App\Models\Test as PathologyCatalogItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportingNormalRangeSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class);
        $admin = Admin::create([
            'first_name' => 'Report',
            'last_name' => 'Editor',
            'email' => 'report-range@example.test',
            'phone' => '01700000001',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $this->actingAs($admin, 'admin');
    }

    public function test_saving_a_report_updates_catalog_parameter_normal_ranges(): void
    {
        $test = $this->createCatalogItem();
        $this->createBillItem($test->id);
        DB::table('pathology_test_parameters')->insert([
            [
                'pathology_test_id' => $test->id,
                'name' => 'Fasting Glucose',
                'reference_from' => '3',
                'reference_to' => '7',
                'pathology_unit_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pathology_test_id' => $test->id,
                'name' => 'Post Meal Glucose',
                'reference_from' => '5',
                'reference_to' => '10',
                'pathology_unit_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $billItem = BillItem::query()->firstOrFail();
        $response = $this->postJson(route('backend.reporting.item.update', $billItem), [
            'department' => 'pathology',
            'report_note' => 'Result saved',
            'report_range' => '4 - 9 | 6 - 11',
            'parameter_values' => [],
        ]);
        $response->assertOk();

        $parameters = PathologyTestParameter::query()
            ->where('pathology_test_id', $test->id)
            ->orderBy('id')
            ->get();

        $this->assertSame('4', $parameters[0]->reference_from);
        $this->assertSame('9', $parameters[0]->reference_to);
        $this->assertSame('6', $parameters[1]->reference_from);
        $this->assertSame('11', $parameters[1]->reference_to);
    }

    public function test_saving_a_report_updates_legacy_catalog_parameter_ranges(): void
    {
        $test = $this->createCatalogItem([
            'test_parameters' => json_encode([
                ['name' => 'Glucose', 'referance_from' => '3', 'referance_to' => '7'],
            ]),
        ]);
        $this->createBillItem($test->id);

        $billItem = BillItem::query()->firstOrFail();
        $this->postJson(route('backend.reporting.item.update', $billItem), [
            'department' => 'pathology',
            'report_note' => 'Result saved',
            'report_range' => '4 - 9',
            'parameter_values' => [],
        ])->assertOk();

        $test->refresh();
        $parameters = json_decode($test->test_parameters, true);
        $this->assertSame('4', $parameters[0]['referance_from']);
        $this->assertSame('9', $parameters[0]['referance_to']);
    }

    public function test_add_report_shows_pending_items_while_edit_report_shows_reported_items(): void
    {
        $test = $this->createCatalogItem();
        $billing = Billing::query()->create([
            'invoice_number' => 'INV-RANGE-002',
            'bill_number' => 'BILL-RANGE-002',
            'case_number' => 'CASE-RANGE-002',
            'patient_mobile' => '01700000000',
            'gender' => 'Others',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 200,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 200,
            'paid_amt' => 200,
            'receiving_amt' => 200,
            'due_amount' => 0,
            'created_by' => Admin::query()->value('id'),
        ]);
        $reportedItem = BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => $test->id,
            'item_name' => 'Glucose Profile - Reported',
            'category' => 'Pathology',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'net_amount' => 100,
            'sample_collected_at' => now(),
            'reported_at' => now(),
        ]);
        $pendingItem = BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => $test->id,
            'item_name' => 'Glucose Profile - Pending',
            'category' => 'Pathology',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'net_amount' => 100,
            'sample_collected_at' => now(),
        ]);

        $this->get(route('backend.reporting.edit', [
            'billing' => $billing->id,
            'department' => 'pathology',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Backend/Reporting/Form')
                ->has('billItems', 1)
                ->where('billItems.0.id', $pendingItem->id));

        $this->get(route('backend.reporting.edit', [
            'billing' => $billing->id,
            'department' => 'pathology',
            'include_reported' => 1,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Backend/Reporting/Form')
                ->has('billItems', 1)
                ->where('billItems.0.id', $reportedItem->id));
    }

    private function createCatalogItem(array $overrides = []): PathologyCatalogItem
    {
        return PathologyCatalogItem::query()->create(array_merge([
            'category_type' => 'Pathology',
            'test_name' => 'Glucose Profile',
            'test_category_id' => 1,
            'status' => 'Active',
        ], $overrides));
    }

    private function createBillItem(int $testId): void
    {
        $adminId = Admin::query()->value('id');
        $billing = Billing::query()->create([
            'invoice_number' => 'INV-RANGE-001',
            'bill_number' => 'BILL-RANGE-001',
            'case_number' => 'CASE-RANGE-001',
            'patient_mobile' => '01700000000',
            'gender' => 'Others',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 100,
            'paid_amt' => 100,
            'receiving_amt' => 100,
            'due_amount' => 0,
            'created_by' => $adminId,
        ]);

        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => $testId,
            'item_name' => 'Glucose Profile',
            'category' => 'Pathology',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'net_amount' => 100,
            'sample_collected_at' => now(),
        ]);
    }
}
