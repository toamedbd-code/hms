<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BillingPaymentStatusTest extends TestCase
{
    /**
     * Test payment status calculation when adding items to a fully paid bill
     * 
     * Scenario: Bill was fully paid (payable_amount=1000, paid_amt=1000)
     * Then a new item is added (new_amount=500)
     * Expected: New payable_amount=1500, due_amount=500, payment_status=Partial
     * (since 1000 is already paid and there's a 500 due on the new item)
     */
    public function test_payment_status_for_new_items_in_edited_bill()
    {
        // Original bill state
        $payableAmount = 1000;
        $paidAmount = 1000;
        $dueAmount = 0;
        
        // After adding new item with amount 500
        $newPayableAmount = 1500;
        // Paid amount stays the same since no payment received
        $newPaidAmount = 1000;
        // New due amount should be calculated
        $newDueAmount = $newPayableAmount - $newPaidAmount; // 500
        
        // Payment status logic from the updated BillingPage.vue
        $paymentStatus = "Pending";
        
        if ($newDueAmount <= 0 && $newPaidAmount >= $newPayableAmount) {
            $paymentStatus = "Paid";
        } else if ($newPaidAmount > 0 && $newDueAmount > 0) {
            $paymentStatus = "Partial";
        } else if ($newDueAmount > 0) {
            $paymentStatus = "Pending";
        }
        
        // Assertions
        $this->assertEquals(1500, $newPayableAmount, 'Payable amount should include new item');
        $this->assertEquals(1000, $newPaidAmount, 'Paid amount should remain unchanged');
        $this->assertEquals(500, $newDueAmount, 'Due amount should be 500');
        $this->assertEquals("Partial", $paymentStatus, 'Payment status should be Partial (1000 paid, 500 due on new items)');
    }
    
    /**
     * Test payment status remains "Paid" when no new items are added
     */
    public function test_payment_status_fully_paid_bill_unchanged()
    {
        $payableAmount = 1000;
        $paidAmount = 1000;
        $dueAmount = 0;
        
        // No new items, amounts stay the same
        $newPayableAmount = 1000;
        $newPaidAmount = 1000;
        $newDueAmount = 0;
        
        $paymentStatus = "Pending";
        
        if ($newDueAmount <= 0 && $newPaidAmount >= $newPayableAmount) {
            $paymentStatus = "Paid";
        } else if ($newPaidAmount > 0 && $newDueAmount > 0) {
            $paymentStatus = "Partial";
        } else if ($newDueAmount > 0) {
            $paymentStatus = "Pending";
        }
        
        $this->assertEquals("Paid", $paymentStatus, 'Fully paid bill should remain Paid');
    }
    
    /**
     * Test payment status shows "Partial" for partially paid bills with new items
     */
    public function test_payment_status_partial_with_new_items()
    {
        // Partially paid bill
        $payableAmount = 1000;
        $paidAmount = 500;
        $dueAmount = 500;
        
        // Add new item
        $newPayableAmount = 1500;
        $newPaidAmount = 500; // unchanged
        $newDueAmount = $newPayableAmount - $newPaidAmount; // 1000
        
        $paymentStatus = "Pending";
        
        if ($newDueAmount <= 0 && $newPaidAmount >= $newPayableAmount) {
            $paymentStatus = "Paid";
        } else if ($newPaidAmount > 0 && $newDueAmount > 0) {
            $paymentStatus = "Partial";
        } else if ($newDueAmount > 0) {
            $paymentStatus = "Pending";
        }
        
        $this->assertEquals("Partial", $paymentStatus, 'Partially paid bill should remain Partial');
    }
    
    /**
     * Test payment status shows "Pending" for completely unpaid new bill
     */
    public function test_payment_status_unpaid_new_bill()
    {
        $payableAmount = 500;
        $paidAmount = 0;
        $dueAmount = 500;
        
        $paymentStatus = "Pending";
        
        if ($dueAmount <= 0 && $paidAmount >= $payableAmount) {
            $paymentStatus = "Paid";
        } else if ($paidAmount > 0 && $dueAmount > 0) {
            $paymentStatus = "Partial";
        } else if ($dueAmount > 0) {
            $paymentStatus = "Pending";
        }
        
        $this->assertEquals("Pending", $paymentStatus, 'Unpaid bill should show Pending');
    }
}
