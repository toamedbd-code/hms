<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\BillingController;
use App\Models\Billing;
use PHPUnit\Framework\TestCase;

class BillingReturnAmountNormalizationTest extends TestCase
{
    public function test_overpayment_is_converted_into_return_amount_when_no_explicit_return_is_sent()
    {
        $controller = new class extends BillingController {
            public function __construct() {}
        };

        $method = new \ReflectionMethod(BillingController::class, 'normalizeBillingPaymentData');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'payable_amount' => 650,
            'paid_amt' => 1250,
            'receiving_amt' => 650,
            'return_amt' => 0,
        ]);

        $this->assertEquals(650.0, $result['paid_amt']);
        $this->assertEquals(600.0, $result['return_amt']);
        $this->assertEquals(0.0, $result['due_amount']);
    }

    public function test_refund_amount_is_derived_from_overpayment_when_return_amt_is_missing()
    {
        $billing = new Billing();
        $billing->payable_amount = 650;
        $billing->paid_amt = 1250;
        $billing->return_amt = 0;

        $this->assertEquals(600.0, $billing->getEffectiveRefundAmount());
    }
}
