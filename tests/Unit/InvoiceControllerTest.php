<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\InvoiceController;
use App\Models\Admin;
use App\Models\BillItem;
use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\InvoiceDesign;
use App\Models\Payment;
use App\Models\PharmacyBill;
use App\Models\WebSetting;
use App\Services\IpdDischargeBillingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pharmacy_invoice_qr_uses_a_signed_auto_print_url(): void
    {
        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function qrUrlForTest(int $billingId, int $pharmacyBillId): string
            {
                return $this->pharmacyInvoiceQrUrl($billingId, $pharmacyBillId);
            }
        };

        $url = $controller->qrUrlForTest(2249, 3381);
        $request = Request::create($url, 'GET');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertTrue(URL::hasValidSignature($request));
        $this->assertSame('2249', (string) ($query['id'] ?? ''));
        $this->assertSame('3381', (string) ($query['pharmacy_bill_id'] ?? ''));
        $this->assertSame('pharmacy', $query['module'] ?? null);
        $this->assertSame('1', (string) ($query['fast_open'] ?? ''));
        $this->assertSame('1', (string) ($query['auto_print'] ?? ''));
    }

    public function test_fast_open_invoice_with_a_bill_id_does_not_return_a_loading_shell(): void
    {
        $this->withoutMiddleware();

        $this->get(route('backend.download.invoice', [
            'id' => 999999999,
            'module' => 'billing',
            'fast_open' => 1,
        ]))
            ->assertRedirect();
    }

    public function test_fast_open_print_token_does_not_show_a_loading_page_until_the_bill_is_saved(): void
    {
        $this->withoutMiddleware();

        $this->get(route('backend.download.invoice', [
            'print_token' => 'invoice-pending-test-token',
            'module' => 'billing',
            'fast_open' => 1,
        ]))
            ->assertOk()
            ->assertDontSee('role="progressbar"', false)
            ->assertSee('response.status === 204')
            ->assertSee('X-Invoice-Bill-Id')
            ->assertDontSee('progress-track');
    }

    public function test_fast_open_print_token_returns_the_bill_id_without_rendering_the_invoice(): void
    {
        $this->withoutMiddleware();

        $token = 'invoice-resolved-test-' . bin2hex(random_bytes(6));
        Cache::put('print_token_' . $token, 12345, now()->addMinute());
        Cache::put('print_token_pharmacy_bill_' . $token, 67890, now()->addMinute());

        try {
            $this->get(route('backend.download.invoice', [
                'print_token' => $token,
                'module' => 'pharmacy',
                'fast_open' => 1,
                'auto_print' => 1,
            ]), ['X-Invoice-Render' => '1'])
                ->assertNoContent()
                ->assertHeader('X-Invoice-Bill-Id', '12345')
                ->assertHeader('X-Pharmacy-Bill-Id', '67890');
        } finally {
            Cache::forget('print_token_' . $token);
            Cache::forget('print_token_pharmacy_bill_' . $token);
        }
    }

    public function test_pharmacy_invoice_uses_the_billing_money_receipt_template_and_pharmacy_totals(): void
    {
        $this->withoutMiddleware();

        $admin = Admin::query()->create([
            'first_name' => 'Invoice',
            'last_name' => 'Test',
            'email' => 'pharmacy-invoice@example.test',
            'phone' => '01700000000',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $billing = Billing::query()->create([
            'invoice_number' => 'INV-PHARMACY-TEST',
            'bill_number' => 'BILL-PHARMACY-TEST',
            'case_number' => 'CASE-PHARMACY-TEST',
            'patient_mobile' => '01700000000',
            'gender' => 'Others',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 26,
            'discount' => 0,
            'discount_type' => 'flat',
            'payable_amount' => 26,
            'paid_amt' => 0,
            'receiving_amt' => 0,
            'due_amount' => 26,
            'created_by' => $admin->id,
            'payment_status' => 'Partial',
            'status' => 'Active',
        ]);
        $pharmacyBill = PharmacyBill::query()->create([
            'pharmacy_no' => 'PHARMACY-TEST',
            'bill_no' => $billing->bill_number,
            'date' => now()->toDateString(),
            'products' => json_encode([[
                'productId' => 1,
                'productName' => 'Test Medicine',
                'quantity' => 1,
                'rate' => 100,
                'amount' => 100,
            ]]),
            'subtotal' => 26,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'vat_percentage' => 0,
            'vat_amount' => 0,
            'extra_discount' => 0,
            'net_amount' => 26,
            'payment_mode' => 'Cash',
            'payment_amount' => 26,
            'return_amount' => 26,
            'created_by' => $admin->id,
            'status' => 'Active',
            'billing_id' => $billing->id,
        ]);
        Payment::query()->create([
            'billing_id' => $billing->id,
            'pharmacy_bill_id' => $pharmacyBill->id,
            'amount' => 26,
            'payment_method' => 'Cash',
            'status' => 'Active',
        ]);
        BillItem::query()->create([
            'billing_id' => $billing->id,
            'pharmacy_bill_id' => $pharmacyBill->id,
            'item_id' => 1,
            'item_name' => 'Test Medicine',
            'category' => 'Medicine',
            'unit_price' => 26,
            'quantity' => 1,
            'total_amount' => 26,
            'net_amount' => 26,
            'status' => 'Active',
        ]);
        PharmacyBill::query()->create([
            'pharmacy_no' => 'PHARMACY-TEST-PAID',
            'bill_no' => 'BILL-PHARMACY-PAID-TEST',
            'date' => now()->toDateString(),
            'products' => '[]',
            'subtotal' => 26,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'vat_percentage' => 0,
            'vat_amount' => 0,
            'extra_discount' => 0,
            'net_amount' => 26,
            'payment_mode' => 'Cash',
            'payment_amount' => 26,
            'return_amount' => 0,
            'created_by' => $admin->id,
            'status' => 'Active',
            'billing_id' => $billing->id,
        ]);
        WebSetting::query()->create([
            'company_name' => 'Test Pharmacy',
            'company_short_name' => 'TP',
            'phone' => '01700000000',
            'report_title' => 'Test Pharmacy',
            'pharmacy_tax_number' => 'MUSH-123456',
            'status' => 'Active',
        ]);
        InvoiceDesign::query()->create([
            'module' => 'pharmacy',
            'status' => 'Active',
            'active_pad_type' => 'institution',
            'full_page_pad_path' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXrwAAAAASUVORK5CYII=',
            'footer_content' => 'Custom Pharmacy Footer, Department service details, Extended contact and location information, Final footer content remains visible',
            'footer_font_size' => 16,
        ]);

        $redirectResponse = $this->get(route('backend.pharmacybill.invoice', [
            'id' => $pharmacyBill->id,
            'auto_print' => 1,
        ]))->assertRedirect();
        parse_str((string) parse_url($redirectResponse->headers->get('Location'), PHP_URL_QUERY), $redirectQuery);
        $this->assertSame((string) $billing->id, (string) ($redirectQuery['id'] ?? ''));
        $this->assertSame((string) $pharmacyBill->id, (string) ($redirectQuery['pharmacy_bill_id'] ?? ''));
        $this->assertSame('pharmacy', $redirectQuery['module'] ?? null);
        $this->assertSame('1', (string) ($redirectQuery['auto_print'] ?? ''));

        $response = $this->get(route('backend.download.invoice', [
            'id' => $billing->id,
            'module' => 'pharmacy',
            'pharmacy_bill_id' => $pharmacyBill->id,
            'fast_open' => 1,
            'auto_print' => 1,
        ]));

        $response->assertOk()
            ->assertSee('MONEY RECEIPT')
            ->assertSee('Test Medicine')
            ->assertSee('VAT/Mushak No.')
            ->assertSee('MUSH-123456')
            ->assertSee('pharmacy-tax-number-title')
            ->assertSee('window.print()')
            ->assertSee('DUE')
            ->assertDontSee('<div class="paid-badge">PAID</div>', false)
            ->assertSee('Custom Pharmacy Footer')
            ->assertSee('Final footer content remains visible')
            ->assertSee('white-space: normal')
            ->assertSee('overflow-wrap: anywhere')
            ->assertSee('.invoice-container.with-institution-pad .footer-section')
            ->assertSee('position: fixed !important')
            ->assertSee('height: 297mm')
            ->assertSee('font-size: 9px !important')
            ->assertSee('transform: translateY(0mm)')
            ->assertSee('26.00');

        $webSetting = WebSetting::query()->where('status', 'Active')->latest('id')->firstOrFail();
        $webSetting->attendance_device_options = ['pharmacy_tax_number_enabled' => false];
        $webSetting->save();

        $this->get(route('backend.download.invoice', [
            'id' => $billing->id,
            'module' => 'pharmacy',
            'pharmacy_bill_id' => $pharmacyBill->id,
        ]))
            ->assertOk()
            ->assertDontSee('VAT/Mushak No.')
            ->assertDontSee('MUSH-123456');
    }

    public function test_it_uses_a_module_specific_invoice_design_before_the_default_design(): void
    {
        $defaultDesign = InvoiceDesign::query()->create([
            'module' => null,
            'status' => 'Active',
            'header_photo_path' => 'default-header.png',
        ]);
        $opdDesign = InvoiceDesign::query()->create([
            'module' => 'opd',
            'status' => 'Active',
            'header_photo_path' => 'opd-header.png',
        ]);

        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function resolveForTest(string $module): ?InvoiceDesign
            {
                return $this->resolveInvoiceDesign($module);
            }
        };

        $this->assertSame($opdDesign->id, $controller->resolveForTest('opd')?->id);
        $this->assertSame($defaultDesign->id, $controller->resolveForTest('ipd')?->id);
    }

    public function test_it_does_not_use_an_unrelated_module_design_as_a_fallback(): void
    {
        InvoiceDesign::query()->create([
            'module' => 'radiology',
            'status' => 'Active',
            'header_photo_path' => 'radiology-header.png',
        ]);

        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function resolveForTest(string $module): ?InvoiceDesign
            {
                return $this->resolveInvoiceDesign($module);
            }
        };

        $this->assertNull($controller->resolveForTest('pharmacy'));
    }

    public function test_it_uses_the_billing_design_when_a_module_has_no_design(): void
    {
        $billingDesign = InvoiceDesign::query()->create([
            'module' => 'billing',
            'status' => 'Active',
            'header_photo_path' => 'billing-header.png',
            'footer_photo_path' => 'billing-footer.png',
            'full_page_pad_path' => 'billing-institution-pad.png',
            'active_pad_type' => 'institution',
        ]);
        InvoiceDesign::query()->create([
            'module' => 'radiology',
            'status' => 'Active',
            'header_photo_path' => 'radiology-header.png',
        ]);

        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function resolveForTest(string $module): ?InvoiceDesign
            {
                return $this->resolveInvoiceDesign($module);
            }
        };

        $this->assertSame($billingDesign->id, $controller->resolveForTest('pharmacy')?->id);
    }

    public function test_it_prefers_the_module_design_and_its_institution_pad_over_billing(): void
    {
        InvoiceDesign::query()->create([
            'module' => 'billing',
            'status' => 'Active',
            'header_photo_path' => 'billing-header.png',
            'full_page_pad_path' => 'billing-institution-pad.png',
        ]);
        $pharmacyDesign = InvoiceDesign::query()->create([
            'module' => 'pharmacy',
            'status' => 'Active',
            'header_photo_path' => 'pharmacy-header.png',
            'footer_photo_path' => 'pharmacy-footer.png',
            'full_page_pad_path' => 'pharmacy-institution-pad.png',
            'active_pad_type' => 'institution',
        ]);

        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function resolveForTest(string $module): ?InvoiceDesign
            {
                return $this->resolveInvoiceDesign($module);
            }
        };

        $resolvedDesign = $controller->resolveForTest('pharmacy');

        $this->assertSame($pharmacyDesign->id, $resolvedDesign?->id);
        $this->assertSame('pharmacy-header.png', $resolvedDesign?->header_photo_path);
        $this->assertSame('pharmacy-footer.png', $resolvedDesign?->footer_photo_path);
        $this->assertSame('pharmacy-institution-pad.png', $resolvedDesign?->full_page_pad_path);
    }

    public function test_shared_invoice_footer_uses_billing_fallback_before_unrelated_module_designs(): void
    {
        InvoiceDesign::query()->create([
            'module' => 'billing',
            'status' => 'Active',
            'footer_content' => 'Billing footer',
        ]);
        InvoiceDesign::query()->create([
            'module' => 'radiology',
            'status' => 'Active',
            'footer_content' => 'Radiology footer',
        ]);

        $html = view('prints.partials._footer', [
            'module' => 'ipd_final',
            'showHeaderFooter' => true,
        ])->render();

        $this->assertStringContainsString('Billing footer', $html);
        $this->assertStringNotContainsString('Radiology footer', $html);
    }

    public function test_shared_invoice_footer_uses_the_configured_design_font_size(): void
    {
        $design = (new InvoiceDesign())->forceFill([
            'footer_font_size' => 27,
            'footer_height' => 70,
        ]);

        $html = view('prints.partials._footer', [
            'invoiceDesign' => $design,
            'footer_content' => 'Configured footer',
            'footerHeight' => 70,
            'showHeaderFooter' => true,
        ])->render();

        $this->assertStringContainsString('--report-footer-font-size: 27px', $html);
        $this->assertStringContainsString('font-size: var(--report-footer-font-size) !important', $html);
    }

    public function test_institution_pad_shows_footer_content_without_separate_footer_image(): void
    {
        $design = (new InvoiceDesign())->forceFill([
            'active_pad_type' => 'institution',
            'footer_height' => 70,
        ]);

        $html = view('prints.partials._footer', [
            'invoiceDesign' => $design,
            'footer_image' => 'https://example.test/footer.png',
            'footer_content' => 'Separate footer content',
            'footerHeight' => 70,
            'showHeaderFooter' => true,
        ])->render();

        $this->assertStringContainsString('--report-footer-height: 0px', $html);
        $this->assertStringNotContainsString('https://example.test/footer.png', $html);
        $this->assertStringContainsString('Separate footer content', $html);
        $this->assertStringContainsString('institution-pad-footer', $html);
    }

    public function test_shared_footer_position_keeps_zero_as_baseline_and_moves_up_to_fifty_mm(): void
    {
        $design = (new InvoiceDesign())->forceFill([
            'footer_content_offset' => 0,
            'footer_height' => 70,
        ]);

        $html = view('prints.partials._footer', [
            'invoiceDesign' => $design,
            'footer_content' => 'Positioned footer',
            'footerHeight' => 70,
            'showHeaderFooter' => true,
        ])->render();

        $this->assertStringContainsString('transform: translateY(0mm)', $html);

        $design->footer_content_offset = 50;
        $html = view('prints.partials._footer', [
            'invoiceDesign' => $design,
            'footer_content' => 'Positioned footer',
            'footerHeight' => 70,
            'showHeaderFooter' => true,
        ])->render();

        $this->assertStringContainsString('transform: translateY(-50mm)', $html);
    }

    public function test_shared_footer_content_wraps_to_the_available_page_width(): void
    {
        $longFooter = 'Room 102 Pathology, Room 106 Ultrasound, Room 103 ECG, Extended Department Contact Details';

        $html = view('prints.partials._footer', [
            'invoiceDesign' => new InvoiceDesign(),
            'footer_content' => $longFooter,
            'footerHeight' => 70,
            'showHeaderFooter' => true,
        ])->render();

        $this->assertStringContainsString($longFooter, $html);
        $this->assertStringContainsString('white-space: normal', $html);
        $this->assertStringContainsString('overflow-wrap: anywhere', $html);
        $this->assertStringNotContainsString('text-overflow: ellipsis', $html);
    }

    public function test_shared_invoice_header_prefers_its_module_design(): void
    {
        InvoiceDesign::query()->create([
            'module' => 'billing',
            'status' => 'Active',
            'header_photo_path' => 'billing-header.png',
        ]);
        InvoiceDesign::query()->create([
            'module' => 'ipd',
            'status' => 'Active',
            'header_photo_path' => 'ipd-header.png',
        ]);

        $html = view('prints.partials._header', [
            'module' => 'ipd',
            'showHeaderFooter' => true,
        ])->render();

        $this->assertStringContainsString('ipd-header.png', $html);
        $this->assertStringNotContainsString('billing-header.png', $html);
    }

    public function test_it_prefers_persisted_return_amount_when_building_invoice_totals(): void
    {
        $billing = new Billing();
        $billing->forceFill([
            'return_amt' => 250,
            'receiving_amt' => 1200,
            'invoice_amount' => 1000,
        ]);

        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function resolveForTest(Billing $billing, Request $request, $productReturnAmount = null): float
            {
                return $this->resolveInvoiceReturnAmount($billing, $request, (float) ($productReturnAmount ?? 0));
            }
        };

        $request = Request::create('/test', 'GET', []);

        $this->assertSame(250.0, $controller->resolveForTest($billing, $request, 0.0));
    }

    public function test_it_allows_explicit_zero_return_amount_to_reset_persisted_return(): void
    {
        $billing = new Billing();
        $billing->forceFill([
            'return_amt' => 250,
            'receiving_amt' => 1200,
            'invoice_amount' => 1000,
        ]);

        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function resolveForTest(Billing $billing, Request $request, $productReturnAmount = null): float
            {
                return $this->resolveInvoiceReturnAmount($billing, $request, (float) ($productReturnAmount ?? 0));
            }
        };

        $request = Request::create('/test', 'GET', ['return_amt' => 0]);

        $this->assertSame(0.0, $controller->resolveForTest($billing, $request, 0.0));
    }

    public function test_it_does_not_create_false_return_amount_when_payment_matches_payable_and_invoice_amount_is_missing(): void
    {
        $billing = new Billing();
        $billing->forceFill([
            'payable_amount' => 1200,
            'paid_amt' => 1200,
            'receiving_amt' => 1200,
            'invoice_amount' => 0,
        ]);

        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function resolveForTest(Billing $billing, Request $request, $productReturnAmount = null): float
            {
                return $this->resolveInvoiceReturnAmount($billing, $request, (float) ($productReturnAmount ?? 0));
            }
        };

        $request = Request::create('/test', 'GET', []);

        $this->assertSame(0.0, $controller->resolveForTest($billing, $request, 0.0));
    }

    public function test_it_prefers_billing_items_for_ipd_invoice_display_when_available(): void
    {
        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function resolveForTest($billingLineItems, array $runningLineItems): array
            {
                return $this->resolveIpdInvoiceLineItems($billingLineItems, $runningLineItems);
            }
        };

        $billingLineItems = collect([
            (object) [
                'item_name' => 'New Added Item',
                'quantity' => 2,
                'unit_price' => 50,
                'net_amount' => 100,
                'category' => 'ipd',
            ],
        ]);

        $runningLineItems = [
            [
                'item_name' => 'Old Running Item',
                'quantity' => 1,
                'unit_price' => 20,
                'net_amount' => 20,
                'category' => 'ipd',
            ],
        ];

        $resolved = $controller->resolveForTest($billingLineItems, $runningLineItems);

        $this->assertCount(2, $resolved);
        $this->assertSame('New Added Item', $resolved[0]['item_name']);
        $this->assertSame(100.0, $resolved[0]['net_amount']);
        $this->assertSame('Old Running Item', $resolved[1]['item_name']);
        $this->assertSame(20.0, $resolved[1]['net_amount']);
    }

    public function test_it_appends_missing_running_line_items_when_billing_items_are_incomplete(): void
    {
        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function resolveForTest($billingLineItems, array $runningLineItems): array
            {
                return $this->resolveIpdInvoiceLineItems($billingLineItems, $runningLineItems);
            }
        };

        $billingLineItems = collect([
            (object) [
                'item_name' => 'New Added Item',
                'quantity' => 2,
                'unit_price' => 50,
                'net_amount' => 100,
                'category' => 'ipd',
            ],
        ]);

        $runningLineItems = [
            [
                'item_name' => 'New Added Item',
                'quantity' => 2,
                'unit_price' => 50,
                'net_amount' => 100,
                'category' => 'ipd',
            ],
            [
                'item_name' => 'Bed Charge',
                'quantity' => 1,
                'unit_price' => 200,
                'net_amount' => 200,
                'category' => 'Bed Charge',
            ],
        ];

        $resolved = $controller->resolveForTest($billingLineItems, $runningLineItems);

        $this->assertCount(2, $resolved);
        $this->assertSame('New Added Item', $resolved[0]['item_name']);
        $this->assertSame('Bed Charge', $resolved[1]['item_name']);
    }

    public function test_it_deduplicates_repeated_ipd_lines_before_invoice_rendering(): void
    {
        $service = new IpdDischargeBillingService();
        $reflection = new \ReflectionMethod(IpdDischargeBillingService::class, 'deduplicateLines');
        $reflection->setAccessible(true);

        $lines = [
            [
                'item_name' => 'Bed Charge',
                'category' => 'Bed Charge',
                'unit_price' => 1000,
                'quantity' => 1,
                'net_amount' => 1000,
            ],
            [
                'item_name' => 'Bed Charge',
                'category' => 'Bed Charge',
                'unit_price' => 1000,
                'quantity' => 1,
                'net_amount' => 1000,
            ],
            [
                'item_name' => 'Bed Charge',
                'category' => 'Bed Charge',
                'unit_price' => 1200,
                'quantity' => 1,
                'net_amount' => 1200,
            ],
        ];

        $deduped = $reflection->invoke($service, $lines);

        $this->assertCount(2, $deduped);
        $this->assertSame(1000.0, (float) ($deduped[0]['net_amount'] ?? 0));
        $this->assertSame(1200.0, (float) ($deduped[1]['net_amount'] ?? 0));
    }

    public function test_it_includes_due_collections_when_computing_invoice_due_with_existing_receiving_amount(): void
    {
        $suffix = uniqid('invoice-test-', true);

        $admin = Admin::create([
            'first_name' => 'Invoice',
            'last_name' => 'Tester',
            'email' => 'invoice.' . $suffix . '@example.test',
            'phone' => '01900' . random_int(100000, 999999),
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);

        $billing = Billing::create([
            'bill_number' => 'BILL-' . $suffix,
            'invoice_number' => 'INV-' . $suffix,
            'case_number' => 'CASE-' . $suffix,
            'patient_mobile' => '01700000000',
            'gender' => 'Male',
            'card_type' => 'Cash',
            'pay_mode' => 'Cash',
            'total' => 1000,
            'payable_amount' => 1000,
            'paid_amt' => 800,
            'change_amt' => 0,
            'receiving_amt' => 800,
            'due_amount' => 200,
            'return_amt' => 0,
            'discount' => 0,
            'discount_type' => 'flat',
            'extra_flat_discount' => 0,
            'vat_amount' => 0,
            'payment_status' => 'Partial',
            'created_by' => $admin->id,
        ]);

        Payment::create([
            'billing_id' => $billing->id,
            'amount' => 800,
            'payment_method' => 'Cash',
            'received_by' => $admin->id,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);

        DueCollection::create([
            'billing_id' => $billing->id,
            'collected_amount' => 200,
            'collected_at' => now(),
            'created_by' => $admin->id,
        ]);

        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function calculateForTest(Billing $billing): array
            {
                $reflection = new \ReflectionMethod($this, 'calculateFilteredTotals');
                $reflection->setAccessible(true);

                return $reflection->invoke($this, collect(), $billing, 'billing');
            }
        };

        $totals = $controller->calculateForTest($billing);

        $this->assertSame(1000.0, (float) ($totals['net_payable'] ?? 0));
        $this->assertSame(1000.0, (float) ($totals['paid'] ?? 0));
        $this->assertSame(0.0, (float) ($totals['due'] ?? 0));
    }

    public function test_it_enables_auto_print_for_ipd_final_bill_when_requested(): void
    {
        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function resolveForTest(Request $request): array
            {
                return $this->resolveIpdFinalBillViewOptions($request);
            }
        };

        $request = Request::create('/test', 'GET', ['auto_print' => 1, 'fast_open' => 1]);
        $options = $controller->resolveForTest($request);

        $this->assertTrue($options['auto_print']);
        $this->assertTrue($options['is_fast_open']);
    }

    public function test_it_includes_ipd_due_collections_in_invoice_time_paid_amount(): void
    {
        $controller = new class extends InvoiceController {
            public function __construct() {}

            public function normalizeForTest($payments, $dueCollections, $invoiceTime): array
            {
                return $this->normalizeIpdInvoicePaymentsAndDueCollections($payments, $dueCollections, $invoiceTime);
            }
        };

        $invoiceTime = now()->subDay();
        $payments = collect([
            (object) [
                'amount' => 1000,
                'created_at' => $invoiceTime->copy()->subHours(2),
                'payment_method' => 'Cash',
                'transaction_id' => 'TXN-1',
                'notes' => 'partial payment',
            ],
        ]);
        $dueCollections = collect([
            (object) [
                'collected_amount' => 500,
                'collected_at' => $invoiceTime->copy()->subHour(),
                'payment_method' => 'Due Collection',
                'note' => 'Collected via IPD payment id: 77',
            ],
        ]);

        $summary = $controller->normalizeForTest($payments, $dueCollections, $invoiceTime);

        $this->assertSame(1500.0, (float) ($summary['invoice_time_paid_amount'] ?? 0));
        $this->assertCount(1, $summary['due_collections'] ?? collect());
    }
}
