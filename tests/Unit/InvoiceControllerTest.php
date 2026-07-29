<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\InvoiceController;
use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\Payment;
use App\Services\IpdDischargeBillingService;
use Illuminate\Http\Request;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
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
            'created_by' => 1,
        ]);

        Payment::create([
            'billing_id' => $billing->id,
            'amount' => 800,
            'payment_method' => 'Cash',
            'received_by' => 1,
            'payment_status' => 'Paid',
            'status' => 'Active',
        ]);

        DueCollection::create([
            'billing_id' => $billing->id,
            'collected_amount' => 200,
            'collected_at' => now(),
            'created_by' => 1,
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
