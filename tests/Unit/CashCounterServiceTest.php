<?php

namespace Tests\Unit;

use App\Services\CashCounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashCounterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CashCounterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CashCounterService::class);
    }

    public function test_cash_counter_calculates_expected_balance_and_difference(): void
    {
        $session = $this->service->startSession([
            'counter_name' => 'Counter 1',
            'user_name' => 'User A',
            'shift_name' => 'Morning',
            'opening_amount' => 1000,
            'opened_at' => now()->toDateTimeString(),
            'created_by' => 1,
        ]);

        $this->service->recordInput($session->id, 500, 'cash input');

        $targetSession = $this->service->startSession([
            'counter_name' => 'Counter 2',
            'user_name' => 'User B',
            'shift_name' => 'Evening',
            'opening_amount' => 200,
            'opened_at' => now()->toDateTimeString(),
            'created_by' => 2,
        ]);

        $this->service->recordHandover($session->id, 200, $targetSession->id, 'handover to counter 2');
        $this->service->closeSession($session->id, 1300, 'cash close');

        $session->refresh();

        $this->assertSame('closed', $session->status);
        $this->assertSame(1300.0, (float) $session->closing_amount);
        $this->assertSame(1300.0, (float) $session->expected_amount);
        $this->assertSame(0.0, (float) $session->difference_amount);
        $this->assertSame(200.0, (float) $targetSession->fresh()->handover_in_amount);
    }
}
