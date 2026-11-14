<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\DoctorSummaryController;
use App\Models\Admin;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\Test;
use App\Models\TestCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoctorSummaryDisposableDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_search_reports_disposable_item_without_allocating_discount_to_it(): void
    {
        $admin = Admin::query()->create([
            'first_name' => 'Report',
            'last_name' => 'Tester',
            'email' => 'doctor-summary-disposable@example.test',
            'phone' => '01710000123',
            'password' => 'password',
            'status' => 'Active',
        ]);

        $billing = Billing::query()->create([
            'bill_number' => 'DISPOSABLE-SUMMARY-001',
            'case_number' => 'CASE-DISPOSABLE-SUMMARY-001',
            'patient_mobile' => '01710000123',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 150,
            'discount' => 0,
            'discount_type' => 'flat',
            'extra_flat_discount' => 10,
            'payable_amount' => 140,
            'paid_amt' => 140,
            'receiving_amt' => 140,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);

        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => 1,
            'item_name' => 'V. Tube',
            'category' => 'Disposable',
            'unit_price' => 50,
            'quantity' => 1,
            'total_amount' => 50,
            'discount' => 0,
            'net_amount' => 49.42,
            'status' => 'Active',
        ]);
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => 2,
            'item_name' => 'Blood Test',
            'category' => 'Pathology',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'discount' => 0,
            'net_amount' => 100,
            'status' => 'Active',
        ]);

        $request = Request::create('/report-summary?q=dis', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $response = app(DoctorSummaryController::class)->index($request)->toResponse($request);
        $page = json_decode($response->getContent(), true);

        $this->assertSame(50.0, (float) $page['props']['datas']['data'][0]['price']);
        $this->assertSame(50.0, (float) $page['props']['meta']['grand_total']);
    }

    public function test_report_summary_uses_active_general_setting_company_name(): void
    {
        DB::table('web_settings')->insert([
            'company_name' => 'The Apollo Diagnostic Center',
            'company_short_name' => 'TADC',
            'phone' => '01339-514747',
            'report_title' => 'Report Summary',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $page = $this->requestSummary([]);

        $this->assertSame('The Apollo Diagnostic Center', $page['props']['companyName']);
    }

    public function test_core_billing_tables_have_indexes_for_common_report_filters(): void
    {
        $this->assertTrue(Schema::hasIndex('billings', 'billings_created_at_idx'));
        $this->assertTrue(Schema::hasIndex('billings', 'billings_doctor_created_at_idx'));
        $this->assertTrue(Schema::hasIndex('billings', 'billings_patient_created_at_idx'));
        $this->assertTrue(Schema::hasIndex('billings', 'billings_referrer_created_at_idx'));
        $this->assertTrue(Schema::hasIndex('bill_items', 'bill_items_billing_active_idx'));
        $this->assertTrue(Schema::hasIndex('bill_items', 'bill_items_category_active_idx'));
        $this->assertTrue(Schema::hasIndex('bill_items', 'bill_items_reporter_active_idx'));
        $this->assertTrue(Schema::hasIndex('bill_items', 'bill_items_collector_active_idx'));
    }

    public function test_ultrasound_category_search_matches_spaced_category_name(): void
    {
        $admin = $this->createSummaryAdmin('ultrasound');
        $billing = Billing::query()->create([
            'bill_number' => 'ULTRASOUND-SUMMARY-001',
            'case_number' => 'CASE-ULTRASOUND-SUMMARY-001',
            'patient_mobile' => '01710000456',
            'gender' => 'Female',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 100,
            'paid_amt' => 100,
            'receiving_amt' => 100,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => 1,
            'item_name' => 'Whole Abdomen',
            'category' => 'Ultrasound',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'discount' => 0,
            'net_amount' => 100,
            'status' => 'Active',
        ]);

        $page = $this->requestSummary(['q' => 'ultra sound']);

        $this->assertSame('test', $page['props']['mode']);
        $this->assertSame(1, $page['props']['meta']['total_cases']);
        $this->assertSame(1.0, (float) $page['props']['meta']['total_items']);
    }

    public function test_ultrasound_alias_search_matches_usg_category(): void
    {
        $admin = $this->createSummaryAdmin('usg-alias');
        $billing = Billing::query()->create([
            'bill_number' => 'USG-ALIAS-SUMMARY-001',
            'case_number' => 'CASE-USG-ALIAS-SUMMARY-001',
            'patient_mobile' => '01710000457',
            'gender' => 'Female',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 100,
            'paid_amt' => 100,
            'receiving_amt' => 100,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => 21,
            'item_name' => 'Abdominal Scan',
            'category' => 'USG',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'discount' => 0,
            'net_amount' => 100,
            'status' => 'Active',
        ]);

        $page = $this->requestSummary(['q' => 'ultra sound']);

        $this->assertSame('test', $page['props']['mode']);
        $this->assertSame(1.0, (float) $page['props']['meta']['total_items']);
    }

    public function test_ultrasonogram_category_filter_matches_usg_bill_items(): void
    {
        $admin = $this->createSummaryAdmin('ultrasonogram-filter');
        TestCategory::query()->create([
            'name' => 'Ultrasonogram',
            'status' => 'Active',
        ]);
        $billing = Billing::query()->create([
            'bill_number' => 'ULTRASONOGRAM-FILTER-001',
            'case_number' => 'CASE-ULTRASONOGRAM-FILTER-001',
            'patient_mobile' => '01710000458',
            'gender' => 'Female',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 100,
            'paid_amt' => 100,
            'receiving_amt' => 100,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => 22,
            'item_name' => 'Abdominal Scan',
            'category' => 'USG',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'discount' => 0,
            'net_amount' => 100,
            'status' => 'Active',
        ]);

        $page = $this->requestSummary([
            'mode' => 'test',
            'subcat' => 'Ultrasonogram',
        ]);

        $this->assertSame('test', $page['props']['mode']);
        $this->assertSame(1.0, (float) $page['props']['meta']['total_items']);
        $this->assertSame(['Abdominal Scan'], $page['props']['datas']['data'][0]['matched_tests']);
    }

    public function test_ultrasonogram_category_filter_matches_radiology_bill_items_by_test_category(): void
    {
        $admin = $this->createSummaryAdmin('ultrasonogram-test-category');
        $testCategory = TestCategory::query()->create([
            'name' => 'Ultrasonogram',
            'status' => 'Active',
        ]);
        $test = Test::query()->create([
            'category_type' => 'Radiology',
            'test_name' => 'USG of Whole Abdomen',
            'test_category_id' => $testCategory->id,
            'status' => 'Active',
        ]);
        $billing = Billing::query()->create([
            'bill_number' => 'ULTRASONOGRAM-TEST-CATEGORY-001',
            'case_number' => 'CASE-ULTRASONOGRAM-TEST-CATEGORY-001',
            'patient_mobile' => '01710000459',
            'gender' => 'Female',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 100,
            'paid_amt' => 100,
            'receiving_amt' => 100,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => $test->id,
            'item_name' => 'USG of Whole Abdomen',
            'category' => 'Radiology',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'discount' => 0,
            'net_amount' => 100,
            'status' => 'Active',
        ]);

        $mainCategoryId = DB::table('main_categories')->insertGetId([
            'name' => 'Ultrasonogram',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mainCategoryTest = Test::query()->create([
            'category_type' => 'Radiology',
            'test_name' => 'USG of Lower Abdomen',
            'main_category_id' => $mainCategoryId,
            'status' => 'Active',
        ]);
        $mainCategoryBilling = Billing::query()->create([
            'bill_number' => 'ULTRASONOGRAM-MAIN-CATEGORY-001',
            'case_number' => 'CASE-ULTRASONOGRAM-MAIN-CATEGORY-001',
            'patient_mobile' => '01710000460',
            'gender' => 'Female',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 100,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 100,
            'paid_amt' => 100,
            'receiving_amt' => 100,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);
        BillItem::query()->create([
            'billing_id' => $mainCategoryBilling->id,
            'item_id' => $mainCategoryTest->id,
            'item_name' => 'USG of Lower Abdomen',
            'category' => 'Radiology',
            'unit_price' => 100,
            'quantity' => 1,
            'total_amount' => 100,
            'discount' => 0,
            'net_amount' => 100,
            'status' => 'Active',
        ]);

        $page = $this->requestSummary([
            'mode' => 'test',
            'subcat' => 'Ultrasonogram',
        ]);

        $this->assertSame('test', $page['props']['mode']);
        $this->assertSame(2.0, (float) $page['props']['meta']['total_items']);
        $this->assertSame(2, $page['props']['datas']['total']);
        $this->assertEqualsCanonicalizing(
            ['USG of Whole Abdomen', 'USG of Lower Abdomen'],
            collect($page['props']['datas']['data'])
                ->flatMap(fn ($row) => $row['matched_tests'])
                ->all()
        );

        $bengaliPage = $this->requestSummary([
            'mode' => 'test',
            'subcat' => 'আল্ট্রাসনোগ্রাম',
        ]);

        $this->assertSame(2.0, (float) $bengaliPage['props']['meta']['total_items']);
    }

    public function test_searching_exact_category_name_returns_same_items_as_selecting_category(): void
    {
        $admin = $this->createSummaryAdmin('category-name-search');
        $ultrasoundCategory = TestCategory::query()->create([
            'name' => 'Ultrasonogram',
            'status' => 'Active',
        ]);
        $radiologyCategory = TestCategory::query()->create([
            'name' => 'Radiology',
            'status' => 'Active',
        ]);
        $ultrasoundTest = Test::query()->create([
            'category_type' => 'Radiology',
            'test_name' => 'USG of Whole Abdomen',
            'test_category_id' => $ultrasoundCategory->id,
            'status' => 'Active',
        ]);
        $radiologyTest = Test::query()->create([
            'category_type' => 'Radiology',
            'test_name' => 'Chest X-Ray',
            'test_category_id' => $radiologyCategory->id,
            'status' => 'Active',
        ]);
        $billing = Billing::query()->create([
            'bill_number' => 'CATEGORY-NAME-SEARCH-001',
            'case_number' => 'CASE-CATEGORY-NAME-SEARCH-001',
            'patient_mobile' => '01710000615',
            'gender' => 'Female',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 300,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 300,
            'paid_amt' => 300,
            'receiving_amt' => 300,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);

        foreach ([
            [$ultrasoundTest, 'USG of Whole Abdomen', 100],
            [$radiologyTest, 'Chest X-Ray', 200],
        ] as [$test, $name, $amount]) {
            BillItem::query()->create([
                'billing_id' => $billing->id,
                'item_id' => $test->id,
                'item_name' => $name,
                'category' => 'Radiology',
                'unit_price' => $amount,
                'quantity' => 1,
                'total_amount' => $amount,
                'discount' => 0,
                'net_amount' => $amount,
                'status' => 'Active',
            ]);
        }

        $searchedByName = $this->requestSummary(['q' => 'Ultrasonogram']);
        $selectedCategory = $this->requestSummary([
            'mode' => 'test',
            'subcat' => 'Ultrasonogram',
        ]);

        $this->assertSame(1, $searchedByName['props']['datas']['total']);
        $this->assertSame(
            $selectedCategory['props']['datas']['data'][0]['matched_tests'],
            $searchedByName['props']['datas']['data'][0]['matched_tests']
        );
        $this->assertSame(
            $selectedCategory['props']['meta']['total_items'],
            $searchedByName['props']['meta']['total_items']
        );
    }

    public function test_radiology_filter_excludes_ultrasound_items_even_when_test_category_is_radiology(): void
    {
        $admin = $this->createSummaryAdmin('radiology-excludes-ultrasound');
        $ultrasoundCategory = TestCategory::query()->create([
            'name' => 'Ultrasonogram',
            'status' => 'Active',
        ]);
        $test = Test::query()->create([
            'category_type' => 'Radiology',
            'test_name' => 'USG of Whole Abdomen',
            'test_category_id' => $ultrasoundCategory->id,
            'status' => 'Active',
        ]);
        $billing = Billing::query()->create([
            'bill_number' => 'RADIOLOGY-ULTRA-EXCLUDE-001',
            'case_number' => 'CASE-RADIOLOGY-ULTRA-EXCLUDE-001',
            'patient_mobile' => '01710000613',
            'gender' => 'Female',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 200,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 200,
            'paid_amt' => 200,
            'receiving_amt' => 200,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);

        BillItem::query()->create([
            'billing_id' => $billing->id,
            'item_id' => $test->id,
            'item_name' => 'USG of Whole Abdomen',
            'category' => 'Ultrasonogram',
            'unit_price' => 200,
            'quantity' => 1,
            'total_amount' => 200,
            'discount' => 0,
            'net_amount' => 200,
            'status' => 'Active',
        ]);

        $page = $this->requestSummary([
            'mode' => 'test',
            'subcat' => 'Radiology',
        ]);

        $this->assertSame('test', $page['props']['mode']);
        $this->assertSame(0, $page['props']['datas']['total']);
    }

    public function test_radiology_category_filter_does_not_include_ultrasonogram_items(): void
    {
        $admin = $this->createSummaryAdmin('radiology-filter');
        TestCategory::query()->create([
            'name' => 'Radiology',
            'status' => 'Active',
        ]);
        TestCategory::query()->create([
            'name' => 'Ultrasonogram',
            'status' => 'Active',
        ]);

        $radiologyBilling = Billing::query()->create([
            'bill_number' => 'RADIOLOGY-FILTER-001',
            'case_number' => 'CASE-RADIOLOGY-FILTER-001',
            'patient_mobile' => '01710000610',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 300,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 300,
            'paid_amt' => 300,
            'receiving_amt' => 300,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);

        BillItem::query()->create([
            'billing_id' => $radiologyBilling->id,
            'item_id' => 1001,
            'item_name' => 'X-Ray Chest',
            'category' => 'Radiology',
            'unit_price' => 300,
            'quantity' => 1,
            'total_amount' => 300,
            'discount' => 0,
            'net_amount' => 300,
            'status' => 'Active',
        ]);

        $ultrasoundBilling = Billing::query()->create([
            'bill_number' => 'RADIOLOGY-FILTER-002',
            'case_number' => 'CASE-RADIOLOGY-FILTER-002',
            'patient_mobile' => '01710000611',
            'gender' => 'Female',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 200,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 200,
            'paid_amt' => 200,
            'receiving_amt' => 200,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);

        BillItem::query()->create([
            'billing_id' => $ultrasoundBilling->id,
            'item_id' => 1002,
            'item_name' => 'USG Whole Abdomen',
            'category' => 'Ultrasonogram',
            'unit_price' => 200,
            'quantity' => 1,
            'total_amount' => 200,
            'discount' => 0,
            'net_amount' => 200,
            'status' => 'Active',
        ]);

        $page = $this->requestSummary([
            'mode' => 'test',
            'subcat' => 'Radiology',
        ]);

        $this->assertSame('test', $page['props']['mode']);
        $this->assertSame(1, $page['props']['datas']['total']);
        $this->assertSame('X-Ray Chest', $page['props']['datas']['data'][0]['item_name']);
    }

    public function test_category_filter_does_not_match_another_item_type_through_overlapping_test_id(): void
    {
        $admin = $this->createSummaryAdmin('category-type-collision');
        $radiologyCategory = TestCategory::query()->create([
            'name' => 'Radiology',
            'status' => 'Active',
        ]);
        $radiologyTest = Test::query()->create([
            'category_type' => 'Radiology',
            'test_name' => 'Chest X-Ray',
            'test_category_id' => $radiologyCategory->id,
            'status' => 'Active',
        ]);
        $misclassifiedPathologyTest = Test::query()->create([
            'category_type' => 'Pathology',
            'test_name' => 'Serum Electrolytes',
            'test_category_id' => $radiologyCategory->id,
            'status' => 'Active',
        ]);
        $billing = Billing::query()->create([
            'bill_number' => 'CATEGORY-TYPE-COLLISION-001',
            'case_number' => 'CASE-CATEGORY-TYPE-COLLISION-001',
            'patient_mobile' => '01710000614',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 300,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 300,
            'paid_amt' => 300,
            'receiving_amt' => 300,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);

        foreach ([
            [
                'category' => 'Pathology',
                'name' => 'Serum Electrolytes',
                'amount' => 100,
                'test_id' => $misclassifiedPathologyTest->id,
            ],
            [
                'category' => 'Radiology',
                'name' => 'CBC',
                'amount' => 100,
                'test_id' => $misclassifiedPathologyTest->id,
            ],
            [
                'category' => 'Radiology',
                'name' => 'Chest X-Ray',
                'amount' => 200,
                'test_id' => $radiologyTest->id,
            ],
        ] as $item) {
            BillItem::query()->create([
                'billing_id' => $billing->id,
                'item_id' => $item['test_id'],
                'item_name' => $item['name'],
                'category' => $item['category'],
                'unit_price' => $item['amount'],
                'quantity' => 1,
                'total_amount' => $item['amount'],
                'discount' => 0,
                'net_amount' => $item['amount'],
                'status' => 'Active',
            ]);
        }

        $page = $this->requestSummary([
            'mode' => 'test',
            'subcat' => 'Radiology',
        ]);

        $this->assertSame(1, $page['props']['datas']['total']);
        $this->assertSame('Chest X-Ray', $page['props']['datas']['data'][0]['item_name']);
    }

    public function test_category_filter_works_without_search_term_in_doctor_mode(): void
    {
        $admin = $this->createSummaryAdmin('doctor-category-filter');
        $doctorBilling = Billing::query()->create([
            'bill_number' => 'DOCTOR-CATEGORY-FILTER-001',
            'case_number' => 'CASE-DOCTOR-CATEGORY-FILTER-001',
            'doctor_name' => 'Dr. Category Filter',
            'patient_mobile' => '01710000810',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 200,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 200,
            'paid_amt' => 200,
            'receiving_amt' => 200,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);

        BillItem::query()->create([
            'billing_id' => $doctorBilling->id,
            'item_id' => 101,
            'item_name' => 'USG Whole Abdomen',
            'category' => 'Ultrasonogram',
            'unit_price' => 200,
            'quantity' => 1,
            'total_amount' => 200,
            'discount' => 0,
            'net_amount' => 200,
            'status' => 'Active',
        ]);

        BillItem::query()->create([
            'billing_id' => $doctorBilling->id,
            'item_id' => 102,
            'item_name' => 'CBC',
            'category' => 'Pathology',
            'unit_price' => 50,
            'quantity' => 1,
            'total_amount' => 50,
            'discount' => 0,
            'net_amount' => 50,
            'status' => 'Active',
        ]);

        $page = $this->requestSummary([
            'mode' => 'doctor',
            'subcat' => 'Ultrasonogram',
        ]);

        $this->assertSame('test', $page['props']['mode']);
        $this->assertSame(1, $page['props']['datas']['total']);
        $this->assertSame('USG Whole Abdomen', $page['props']['datas']['data'][0]['item_name']);
    }

    public function test_doctor_search_returns_patient_and_test_rows_with_category_filter(): void
    {
        $admin = $this->createSummaryAdmin('patient-detail');
        TestCategory::query()->create([
            'name' => 'USG',
            'status' => 'Active',
        ]);
        $patientId = DB::table('patients')->insertGetId([
            'name' => 'Patient Detail Example',
            'phone' => '01710000777',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $billing = Billing::query()->create([
            'bill_number' => 'DOCTOR-PATIENT-DETAIL-001',
            'case_number' => 'CASE-DOCTOR-PATIENT-DETAIL-001',
            'doctor_name' => 'Dr. Detail Example',
            'patient_id' => $patientId,
            'patient_mobile' => '01710000777',
            'gender' => 'Female',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 300,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 300,
            'paid_amt' => 300,
            'receiving_amt' => 300,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);

        foreach ([
            ['name' => 'Abdominal Scan', 'category' => 'USG', 'price' => 200],
            ['name' => 'Lower Abdomen Scan', 'category' => 'USG', 'price' => 100],
            ['name' => 'CBC', 'category' => 'Pathology', 'price' => 100],
        ] as $index => $item) {
            BillItem::query()->create([
                'billing_id' => $billing->id,
                'item_id' => $index + 30,
                'item_name' => $item['name'],
                'category' => $item['category'],
                'unit_price' => $item['price'],
                'quantity' => 1,
                'total_amount' => $item['price'],
                'discount' => 0,
                'net_amount' => $item['price'],
                'status' => 'Active',
            ]);
        }

        $page = $this->requestSummary([
            'mode' => 'test',
            'q' => 'Detail Example',
            'subcat' => 'USG',
        ]);

        $this->assertSame('doctor_patient', $page['props']['mode']);
        $this->assertSame(1, $page['props']['meta']['total_cases']);
        $this->assertSame(2.0, (float) $page['props']['meta']['total_items']);
        $this->assertSame(1, $page['props']['datas']['total']);
        $this->assertSame('Patient Detail Example', $page['props']['datas']['data'][0]['patient_name']);
        $this->assertSame($patientId, $page['props']['datas']['data'][0]['patient_id']);
        $this->assertEqualsCanonicalizing(
            ['Abdominal Scan', 'Lower Abdomen Scan'],
            $page['props']['datas']['data'][0]['item_names']
        );
        $this->assertSame('USG', $page['props']['datas']['data'][0]['category']);
        $this->assertSame(2.0, (float) $page['props']['datas']['data'][0]['quantity']);
        $this->assertSame(300.0, (float) $page['props']['datas']['data'][0]['price']);
    }

    public function test_category_list_comes_from_item_category_master_not_old_bill_items(): void
    {
        $admin = $this->createSummaryAdmin('category-list');
        foreach (['USG', 'Pathology', 'X-Ray'] as $category) {
            TestCategory::query()->create([
                'name' => $category,
                'status' => 'Active',
            ]);
        }

        $billing = Billing::query()->create([
            'bill_number' => 'CATEGORY-LIST-SUMMARY-001',
            'case_number' => 'CASE-CATEGORY-LIST-SUMMARY-001',
            'doctor_name' => 'Dr. Category List',
            'patient_mobile' => '01710000888',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 200,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 200,
            'paid_amt' => 200,
            'receiving_amt' => 200,
            'due_amount' => 0,
            'created_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);
        foreach (['USG', 'Pathology', 'X-Ray', 'Legacy Billing Category'] as $index => $category) {
            BillItem::query()->create([
                'billing_id' => $billing->id,
                'item_id' => $index + 50,
                'item_name' => $category . ' example',
                'category' => $category,
                'unit_price' => 50,
                'quantity' => 1,
                'total_amount' => 50,
                'discount' => 0,
                'net_amount' => 50,
                'status' => 'Active',
            ]);
        }

        $request = Request::create('/report-summary?list=1&category=categories', 'GET');
        $response = app(DoctorSummaryController::class)->index($request);

        $names = $response->getData(true)['names'];
        $this->assertContains('USG', $names);
        $this->assertContains('Pathology', $names);
        $this->assertContains('X-Ray', $names);
        $this->assertNotContains('Legacy Billing Category', $names);
    }

    public function test_category_list_uses_leaf_category_names_without_parent_path_prefixes(): void
    {
        $parent = TestCategory::query()->create([
            'name' => 'Radiology',
            'status' => 'Active',
        ]);
        TestCategory::query()->create([
            'name' => 'Ultrasonogram',
            'parent_id' => $parent->id,
            'status' => 'Active',
        ]);

        $request = Request::create('/report-summary?list=1&category=categories', 'GET');
        $response = app(DoctorSummaryController::class)->index($request);
        $names = $response->getData(true)['names'];

        $this->assertContains('Radiology', $names);
        $this->assertContains('Ultrasonogram', $names);
        $this->assertNotContains('Radiology > Ultrasonogram', $names);
    }

    public function test_category_list_includes_item_category_master_names_without_bill_items(): void
    {
        TestCategory::query()->create([
            'name' => 'Master Only Category',
            'status' => 'Active',
        ]);
        TestCategory::query()->create([
            'name' => 'Inactive Master Category',
            'status' => 'Inactive',
        ]);
        DB::table('testcategories')->insert([
            [
                'name' => 'Deleted Master Category',
                'status' => 'Deleted',
                'deleted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Soft Deleted Master Category',
                'status' => 'Active',
                'deleted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('main_categories')->insert([
            'name' => 'Main Category Only',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/report-summary?list=1&category=categories', 'GET');
        $response = app(DoctorSummaryController::class)->index($request);
        $names = $response->getData(true)['names'];

        $this->assertContains('Master Only Category', $names);
        $this->assertContains('Inactive Master Category', $names);
        $this->assertContains('Main Category Only', $names);
        $this->assertNotContains('Deleted Master Category', $names);
        $this->assertNotContains('Soft Deleted Master Category', $names);
    }

    public function test_doctor_and_referrer_searches_return_date_wise_patient_and_test_counts(): void
    {
        $admin = $this->createSummaryAdmin('daily');
        $categoryId = DB::table('referralcategories')->insertGetId([
            'name' => 'Clinic',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $referrerId = DB::table('referralpeople')->insertGetId([
            'name' => 'Referral Clinic',
            'phone' => '01710000789',
            'category_id' => $categoryId,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            ['case' => 'DOCTOR-DAILY-001', 'date' => '2025-02-10 09:00:00', 'doctor' => 'Dr. Daily Search', 'referrer_id' => null],
            ['case' => 'REFERRER-DAILY-001', 'date' => '2025-02-11 09:00:00', 'doctor' => 'Another Doctor', 'referrer_id' => $referrerId],
        ] as $index => $record) {
            $billing = Billing::query()->create([
                'bill_number' => 'DAILY-SUMMARY-' . $index,
                'case_number' => $record['case'],
                'doctor_name' => $record['doctor'],
                'referrer_id' => $record['referrer_id'],
                'patient_mobile' => '01710000' . (100 + $index),
                'gender' => 'Male',
                'card_type' => 'Cash',
                'pay_mode' => 'Cash',
                'total' => 200,
                'discount' => 0,
                'discount_type' => 'flat',
                'payable_amount' => 200,
                'paid_amt' => 200,
                'receiving_amt' => 200,
                'due_amount' => 0,
                'created_by' => $admin->id,
                'payment_status' => 'Paid',
                'status' => 'Active',
                'created_at' => $record['date'],
                'updated_at' => $record['date'],
            ]);

            foreach (['Pathology', 'Radiology'] as $itemIndex => $category) {
                BillItem::query()->create([
                    'billing_id' => $billing->id,
                    'item_id' => $itemIndex + 1,
                    'item_name' => $category . ' Test',
                    'category' => $category,
                    'unit_price' => 100,
                    'quantity' => 1,
                    'total_amount' => 100,
                    'discount' => 0,
                    'net_amount' => 100,
                    'status' => 'Active',
                ]);
            }
        }

        $doctorPage = $this->requestSummary([
            'q' => 'Daily Search',
            'from' => '2025-02-10',
            'to' => '2025-02-10',
        ]);
        $this->assertSame('doctor_patient', $doctorPage['props']['mode']);
        $this->assertSame(1, $doctorPage['props']['meta']['total_cases']);
        $this->assertSame(2.0, (float) $doctorPage['props']['meta']['total_items']);
        $this->assertSame('2025-02-10', $doctorPage['props']['datas']['data'][0]['billing_date']);
        $this->assertSame('Dr. Daily Search', $doctorPage['props']['datas']['data'][0]['doctor_name']);

        $referrerPage = $this->requestSummary([
            'q' => 'Referral Clinic',
            'from' => '2025-02-11',
            'to' => '2025-02-11',
        ]);
        $this->assertSame('doctor_daily', $referrerPage['props']['mode']);
        $this->assertSame(1, $referrerPage['props']['meta']['total_cases']);
        $this->assertSame(2.0, (float) $referrerPage['props']['meta']['total_items']);
        $this->assertSame('Referral Clinic', $referrerPage['props']['datas']['data'][0]['doctor_name']);
    }

    private function createSummaryAdmin(string $suffix): Admin
    {
        return Admin::query()->create([
            'first_name' => 'Report',
            'last_name' => 'Tester',
            'email' => 'doctor-summary-' . $suffix . '@example.test',
            'phone' => '01710000' . (string) random_int(100, 999),
            'password' => 'password',
            'status' => 'Active',
        ]);
    }

    private function requestSummary(array $query): array
    {
        $request = Request::create('/report-summary?' . http_build_query($query), 'GET');
        $request->headers->set('X-Inertia', 'true');
        $response = app(DoctorSummaryController::class)->index($request)->toResponse($request);

        return json_decode($response->getContent(), true);
    }
}
