<?php

namespace Tests\Unit;

use App\Models\BillItem;
use App\Models\Billing;
use App\Models\Referral;
use App\Models\ReferralPerson;
use App\Services\ReferralCommissionCalculator;
use Tests\TestCase;

class ReferralCommissionCalculatorTest extends TestCase
{
    public function test_commission_bases_reconcile_to_payable_amount_after_discount(): void
    {
        $calculator = new ReferralCommissionCalculator();
        $result = $calculator->calculate([
            ['id' => 1, 'name' => 'Ultrasound', 'category' => 'ultrasound', 'net_amount' => 1000],
            ['id' => 2, 'name' => 'Pathology', 'category' => 'pathology', 'net_amount' => 2600],
            ['id' => 3, 'name' => 'X-ray', 'category' => 'radiology', 'net_amount' => 300],
        ], 3000, static function (array $item): array {
            return [
                'category' => $item['category'],
                'rate' => [
                    'ultrasound' => 30,
                    'pathology' => 50,
                    'radiology' => 25,
                ][$item['category']],
            ];
        });

        $this->assertSame(3000.0, array_sum(array_column($result['categories'], 'base_amount')));
        $this->assertSame(1288.46, $result['total']);
        $this->assertSame(230.77, $result['categories']['ultrasound']['amount']);
        $this->assertSame(1000.0, $result['categories']['pathology']['amount']);
        $this->assertSame(57.69, $result['categories']['radiology']['amount']);
    }

    public function test_ultrasound_referral_recalculation_does_not_resolve_category_as_a_model(): void
    {
        $billing = new Billing(['payable_amount' => 1000]);
        $billing->setRelation('billItems', collect([
            new BillItem([
                'item_id' => 1,
                'item_name' => 'USG of Whole Abdomen',
                'category' => 'Ultrasound',
                'net_amount' => 1000,
                'total_amount' => 1000,
            ]),
        ]));

        $payee = new ReferralPerson(['ultrasound_commission' => 10]);
        $referral = new Referral();
        $referral->setRelation('billing', $billing);
        $referral->setRelation('payee', $payee);

        $result = $referral->calculateCommission();

        $this->assertSame(100.0, $result['total_commission']);
        $this->assertSame(100.0, $result['category_breakdown']['ultrasound']['commission_amount']);
    }

    public function test_referral_uses_the_commission_amount_saved_on_the_invoice(): void
    {
        $billing = new Billing([
            'payable_amount' => 3000,
            'commission_total' => 1134.63,
        ]);
        $billing->setRelation('billItems', collect([
            new BillItem([
                'item_id' => 1,
                'item_name' => 'USG of Whole Abdomen',
                'category' => 'Ultrasound',
                'net_amount' => 1000,
                'total_amount' => 1000,
                'commission_rate' => 30,
            ]),
        ]));

        $payee = new ReferralPerson(['ultrasound_commission' => 20]);
        $referral = new Referral();
        $referral->setRelation('billing', $billing);
        $referral->setRelation('payee', $payee);
        $referral->category_commissions = [
            'ultrasound' => [
                'amount' => 769.23,
                'commission_rate' => 20,
                'commission_amount' => 153.85,
            ],
            'radiology' => [
                'amount' => 538.44,
                'commission_rate' => 25,
                'commission_amount' => 134.61,
            ],
            'pathology' => [
                'amount' => 1692.33,
                'commission_rate' => 50,
                'commission_amount' => 846.17,
            ],
        ];

        $result = $referral->calculateCommission();

        $this->assertSame(1134.63, $result['total_commission']);
        $this->assertSame(1134.63, array_sum(array_column($result['category_breakdown'], 'commission_amount')));
    }
}
