<?php

namespace Tests\Unit;

use App\Models\Billing;
use PHPUnit\Framework\TestCase;

class BillingVatTest extends TestCase
{
    public function test_billing_model_allows_vat_fields_to_be_mass_assigned(): void
    {
        $billing = new Billing();

        $this->assertContains('vat_percentage', $billing->getFillable());
        $this->assertContains('vat_amount', $billing->getFillable());
    }
}
