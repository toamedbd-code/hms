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

    public function test_find_open_session_is_scoped_to_admin(): void
    {
        $this->service->startSession([
            'counter_name' => 'Counter 1',
            'user_name' => 'User A',
            'shift_name' => 'Morning',
            'opening_amount' => 100,
            'created_by' => 11,
        ]);

        $this->assertNotNull($this->service->findOpenSessionForAdmin(11, 'User A'));
        $this->assertNull($this->service->findOpenSessionForAdmin(22, 'User B'));
    }

    public function test_close_session_rejects_already_closed_session(): void
    {
        $session = $this->service->startSession([
            'counter_name' => 'Counter 1',
            'user_name' => 'User A',
            'shift_name' => 'Morning',
            'opening_amount' => 100,
            'created_by' => 11,
        ]);

        $this->service->closeSession($session->id, 100, 'first close');

        $this->expectException(\RuntimeException::class);
        $this->service->closeSession($session->id, 100, 'second close');
    }

    public function test_close_report_totals_match_start_and_close_amounts(): void
    {
        $session = $this->service->startSession([
            'counter_name' => 'Counter 1',
            'user_name' => 'User A',
            'shift_name' => 'Morning',
            'opening_amount' => 1000,
            'created_by' => 11,
        ]);

        $this->service->recordInput($session->id, 250, 'extra cash');
        $this->service->closeSession($session->id, 1250, 'close match');

        $report = $this->service->getHandoverPrintSummary($session->id, 11, false);

        $this->assertSame(1000.0, (float) $report['counterTotals']['opening_amount']);
        $this->assertSame(0.0, (float) $report['counterTotals']['collection_total']);
        $this->assertSame(250.0, (float) $report['counterTotals']['input_total']);
        $this->assertSame(1250.0, (float) $report['counterTotals']['expected_amount']);
        $this->assertSame(1250.0, (float) $report['counterTotals']['closing_amount']);
        $this->assertSame(0.0, (float) $report['counterTotals']['difference_amount']);
        $this->assertSame(0.0, (float) $report['totals']['total_collection']);
    }
}
