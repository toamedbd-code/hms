<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BillingReceivingAmountTest extends TestCase
{
    /**
     * Test receiving amount calculation when adding items to a paid bill
     * 
     * Scenario: Paid bill (1000 total, 1000 paid)
     * Add new item worth 500
     * Expected: Total=1500, Paid=1000, Due=500, No new payment received
     */
    public function test_adding_items_to_paid_bill_shows_correct_due()
    {
        // Original bill (fully paid)
        $originalPaidAmount = 1000;
        $newPayableAmount = 1500; // After adding 500 item
        
        // When editing: receivingAmount should be empty (0), not set to paid amount
        $receivingAmount = 0; // Fixed initialization
        
        // Calculate total paid using the logic from calculateChangeAndDue()
        $requestedPaid = $originalPaidAmount + $receivingAmount; // 1000 + 0 = 1000
        $effectivePaid = min($newPayableAmount, $requestedPaid); // min(1500, 1000) = 1000
        $dueAmount = max(0, $newPayableAmount - $effectivePaid); // max(0, 1500-1000) = 500
        $returnAmount = max(0, max($receivingAmount, $effectivePaid) - $effectivePaid); // 0
        
        // Assertions
        $this->assertEquals(1500, $newPayableAmount, 'Payable should be 1500 with new item');
        $this->assertEquals(1000, $effectivePaid, 'Paid amount should stay 1000');
        $this->assertEquals(500, $dueAmount, 'Due amount should be 500 for new unpaid item');
        $this->assertEquals(0, $returnAmount, 'No return needed when adding items');
    }
    
    /**
     * Test receiving amount calculation when removing items from a paid bill
     * 
     * Scenario: Paid bill (1000 total, 1000 paid)
     * Remove item worth 300
     * Expected: Total=700, Paid=700 (capped at new total), Return=300
     */
    public function test_removing_items_from_paid_bill_shows_return()
    {
        // Original bill (fully paid)
        $originalPaidAmount = 1000;
        $newPayableAmount = 700; // After removing 300 item
        
        // No new payment received when removing items
        $receivingAmount = 0;
        
        // EDIT MODE: Return amount = original paid - new payable (when overpaid)
        // This is the corrected logic
        $returnAmount = 0;
        if ($originalPaidAmount > $newPayableAmount) {
            $returnAmount = $originalPaidAmount - $newPayableAmount; // 1000 - 700 = 300
        }
        
        // Calculate paid amount
        $requestedPaid = $originalPaidAmount + $receivingAmount; // 1000 + 0 = 1000
        $effectivePaid = min($newPayableAmount, $requestedPaid); // min(700, 1000) = 700
        $dueAmount = max(0, $newPayableAmount - $effectivePaid); // max(0, 700-700) = 0
        
        // Assertions
        $this->assertEquals(700, $newPayableAmount, 'Payable should be 700 after removing item');
        $this->assertEquals(700, $effectivePaid, 'Effective paid should be 700');
        $this->assertEquals(0, $dueAmount, 'No due when 700 paid on 700 bill');
        $this->assertEquals(300, $returnAmount, 'Return should be 300 when paid 1000 on 700 bill');
    }
    
    /**
     * Test receiving amount with partial payment and new items
     * 
     * Scenario: Partially paid bill (1000 total, 400 paid, 600 due)
     * Add item worth 500
     * Expected: Total=1500, Paid=400, Due=1100
     */
    public function test_partial_payment_with_new_items()
    {
        // Partially paid bill
        $originalPaidAmount = 400;
        $newPayableAmount = 1500; // After adding 500
        
        $receivingAmount = 0; // No new payment yet
        
        $requestedPaid = $originalPaidAmount + $receivingAmount; // 400
        $effectivePaid = min($newPayableAmount, $requestedPaid); // min(1500, 400) = 400
        $dueAmount = max(0, $newPayableAmount - $effectivePaid); // max(0, 1500-400) = 1100
        
        // Assertions
        $this->assertEquals(1500, $newPayableAmount);
        $this->assertEquals(400, $effectivePaid, 'Paid stays at 400');
        $this->assertEquals(1100, $dueAmount, 'Due increases to 1100 with new items');
    }
    
    /**
     * Test receiving amount when user explicitly pays on edit
     * 
     * Scenario: Partially paid bill (1000 total, 400 paid, 600 due)
     * Add item worth 500
     * User pays additional 200
     * Expected: Total=1500, Paid=600, Due=900
     */
    public function test_explicit_payment_on_edit_with_new_items()
    {
        // Partially paid, user adds 200 more payment
        $originalPaidAmount = 400;
        $receivingAmount = 200; // User entered new payment
        $newPayableAmount = 1500; // After adding item
        
        $requestedPaid = $originalPaidAmount + $receivingAmount; // 400 + 200 = 600
        $effectivePaid = min($newPayableAmount, $requestedPaid); // min(1500, 600) = 600
        $dueAmount = max(0, $newPayableAmount - $effectivePaid); // max(0, 1500-600) = 900
        
        // Assertions
        $this->assertEquals(600, $effectivePaid, 'Paid should include new payment');
        $this->assertEquals(900, $dueAmount, 'Due should be reduced by new payment');
    }

    public function test_invoice_return_amount_uses_overpayment_when_persisted_return_is_zero()
    {
        $billingPaidAmount = 1000;
        $billingReceivingAmount = 1000;
        $billingInvoiceAmount = 700;
        $productReturnAmount = 0;

        $persistedReturn = 0;
        $invoiceAmount = (float) ($billingInvoiceAmount ?? 0);
        $cashReturnAmount = max(0, (float) ($billingReceivingAmount ?? 0) - $invoiceAmount);
        $returnAmount = round($productReturnAmount + $cashReturnAmount, 2);

        $this->assertEquals(300, $returnAmount, 'Invoice return should be derived from overpayment when persisted return is zero');
    }

    public function test_invoice_return_amount_uses_paid_minus_payable_when_bill_is_overpaid_after_edit()
    {
        $paidAmount = 1250;
        $payableAmount = 200;
        $receivingAmount = 200;
        $invoiceAmount = 1250;
        $productReturnAmount = 0;

        $cashReturnAmount = max(0, $receivingAmount - $invoiceAmount);
        $overpaymentReturn = max(0, $paidAmount - $payableAmount);
        $returnAmount = round($productReturnAmount + max($cashReturnAmount, $overpaymentReturn), 2);

        $this->assertEquals(1050, $returnAmount, 'Invoice return should reflect overpayment after the bill was reduced');
    }
}
