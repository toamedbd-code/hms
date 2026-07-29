<?php

namespace Tests\Unit;

use App\Models\Billing;
use App\Services\ActivityLogService;
use Tests\TestCase;

class ActivityLogServiceBillingMetaTest extends TestCase
{
    public function test_billing_delete_meta_includes_amount_details(): void
    {
        $billing = new Billing([
            'id' => 42,
            'bill_number' => 'BILL-2026-0001',
            'invoice_number' => 'INV-2026-0001',
            'case_number' => 'CASE-2026-0001',
            'patient_id' => 7,
            'total' => 1200.50,
            'payable_amount' => 1000.25,
            'paid_amt' => 250.00,
            'due_amount' => 750.25,
        ]);

        $meta = ActivityLogService::buildBillingDeleteMeta($billing);

        $this->assertSame('BILL-2026-0001', $meta['bill_number']);
        $this->assertSame(1200.5, $meta['total_amount']);
        $this->assertSame(1000.25, $meta['payable_amount']);
        $this->assertSame(250.0, $meta['paid_amt']);
        $this->assertSame(750.25, $meta['due_amount']);
    }

    public function test_billing_item_change_summary_includes_delta_amounts(): void
    {
        $oldItems = [
            ['id' => 1, 'name' => 'CBC', 'total_amount' => 100.0],
        ];

        $newItems = [
            ['id' => 1, 'name' => 'CBC', 'total_amount' => 150.0],
            ['id' => 2, 'name' => 'X-Ray', 'total_amount' => 80.0],
        ];

        $changes = ActivityLogService::buildBillingItemChangeSummary($oldItems, $newItems);

        $this->assertCount(2, $changes);
        $this->assertSame('CBC', $changes[0]['item_name']);
        $this->assertSame(100.0, $changes[0]['old_amount']);
        $this->assertSame(150.0, $changes[0]['new_amount']);
        $this->assertSame(50.0, $changes[0]['delta_amount']);
        $this->assertSame('increased', $changes[0]['change_type']);
    }
}
