<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\AccountApiController;
use App\Models\Admin;
use App\Models\JournalEntry;
use App\Models\LedgerTransaction;
use App\Models\OpeningBalancePosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class OpeningBalanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_history_returns_filtered_json_rows_with_snapshot(): void
    {
        OpeningBalancePosting::create([
            'is_repost' => false,
            'posting_date' => now()->subDays(10)->toDateString(),
            'total_debit' => 100,
            'total_credit' => 100,
            'line_count' => 2,
            'snapshot' => [
                ['account_id' => 1, 'account_code' => 'A1', 'account_name' => 'Asset One', 'entry_type' => 'debit', 'amount' => 100],
                ['account_id' => 2, 'account_code' => 'L1', 'account_name' => 'Liability One', 'entry_type' => 'credit', 'amount' => 100],
            ],
            'notes' => 'Initial posting',
        ]);

        OpeningBalancePosting::create([
            'is_repost' => true,
            'posting_date' => now()->toDateString(),
            'total_debit' => 200,
            'total_credit' => 200,
            'line_count' => 2,
            'snapshot' => [
                ['account_id' => 3, 'account_code' => 'CASH', 'account_name' => 'Cash In Hand', 'entry_type' => 'debit', 'amount' => 200],
                ['account_id' => 4, 'account_code' => 'CAP', 'account_name' => 'Capital', 'entry_type' => 'credit', 'amount' => 200],
            ],
            'notes' => 'Repost',
        ]);

        $request = Request::create('/', 'GET', [
            'posting_type' => 'repost',
            'from_date' => now()->subDays(1)->toDateString(),
            'to_date' => now()->toDateString(),
            'q' => 'repost',
            'numOfData' => 10,
        ]);

        $response = app(AccountApiController::class)->openingBalanceHistory($request);
        $payload = $response->getData(true);

        $this->assertSame(1, $payload['total']);
        $this->assertCount(1, $payload['data']);
        $this->assertTrue((bool) $payload['data'][0]['is_repost']);
        $this->assertSame('Repost', $payload['data'][0]['notes']);
        $this->assertIsArray($payload['data'][0]['snapshot']);
        $this->assertCount(2, $payload['data'][0]['snapshot']);
        $this->assertSame('debit', $payload['data'][0]['snapshot'][0]['entry_type']);
        $this->assertSame('CASH', $payload['data'][0]['snapshot'][0]['account_code']);
        $this->assertSame('Cash In Hand', $payload['data'][0]['snapshot'][0]['account_name']);
    }

    public function test_opening_history_returns_streamed_csv_with_snapshot_json_column(): void
    {
        OpeningBalancePosting::create([
            'is_repost' => false,
            'posting_date' => now()->toDateString(),
            'total_debit' => 150,
            'total_credit' => 150,
            'line_count' => 2,
            'snapshot' => [
                ['account_id' => 11, 'account_code' => 'BANK', 'account_name' => 'Bank', 'entry_type' => 'debit', 'amount' => 150],
                ['account_id' => 12, 'account_code' => 'OWN_CAP', 'account_name' => 'Owner Capital', 'entry_type' => 'credit', 'amount' => 150],
            ],
            'notes' => 'Initial posting',
        ]);

        $request = Request::create('/', 'GET', [
            'format' => 'csv',
            'posting_type' => 'all',
        ]);

        $response = app(AccountApiController::class)->openingBalanceHistory($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('content-type'));

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('snapshot_json', $csv);
        $this->assertStringContainsString('""entry_type"":""debit""', $csv);
        $this->assertStringContainsString('""account_id"":11', $csv);
        $this->assertStringContainsString('""account_code"":""BANK""', $csv);
        $this->assertStringContainsString('""account_name"":""Bank""', $csv);
    }

    public function test_opening_history_keyword_search_matches_related_models(): void
    {
        $admin = Admin::create([
            'first_name' => 'Audit',
            'last_name' => 'Reviewer',
            'email' => 'audit.reviewer@example.com',
            'password' => 'secret',
            'status' => 'Active',
        ]);

        $journal = JournalEntry::create([
            'entry_date' => now()->toDateString(),
            'reference' => 'OB-JRN-SEARCH-001',
            'description' => 'Opening posting search target',
            'total_debit' => 50,
            'total_credit' => 50,
            'posted' => true,
            'status' => 'Posted',
            'created_by' => null,
        ]);

        $ledgerTx = LedgerTransaction::create([
            'uuid' => 'LEDGER-SEARCH-UUID-001',
            'date' => now()->toDateString(),
            'description' => 'Ledger search target',
            'reference_type' => 'opening_balance',
        ]);

        OpeningBalancePosting::create([
            'journal_entry_id' => $journal->id,
            'ledger_transaction_id' => $ledgerTx->id,
            'posted_by' => $admin->id,
            'is_repost' => false,
            'posting_date' => now()->toDateString(),
            'total_debit' => 50,
            'total_credit' => 50,
            'line_count' => 2,
            'snapshot' => [
                ['account_id' => 31, 'account_code' => 'CASH', 'account_name' => 'Cash', 'entry_type' => 'debit', 'amount' => 50],
                ['account_id' => 32, 'account_code' => 'CAP', 'account_name' => 'Capital', 'entry_type' => 'credit', 'amount' => 50],
            ],
            'notes' => 'No direct keyword in notes',
        ]);

        $journalSearchResponse = app(AccountApiController::class)->openingBalanceHistory(
            Request::create('/', 'GET', ['q' => 'OB-JRN-SEARCH-001'])
        );
        $journalPayload = $journalSearchResponse->getData(true);
        $this->assertSame(1, $journalPayload['total']);

        $ledgerSearchResponse = app(AccountApiController::class)->openingBalanceHistory(
            Request::create('/', 'GET', ['q' => 'LEDGER-SEARCH-UUID-001'])
        );
        $ledgerPayload = $ledgerSearchResponse->getData(true);
        $this->assertSame(1, $ledgerPayload['total']);

        $adminSearchResponse = app(AccountApiController::class)->openingBalanceHistory(
            Request::create('/', 'GET', ['q' => 'audit.reviewer@example.com'])
        );
        $adminPayload = $adminSearchResponse->getData(true);
        $this->assertSame(1, $adminPayload['total']);
    }

    public function test_opening_history_sort_order_changes_result_sequence(): void
    {
        $first = OpeningBalancePosting::create([
            'is_repost' => false,
            'posting_date' => now()->subDays(2)->toDateString(),
            'total_debit' => 10,
            'total_credit' => 10,
            'line_count' => 2,
            'snapshot' => [
                ['account_id' => 1, 'account_code' => 'A1', 'account_name' => 'A One', 'entry_type' => 'debit', 'amount' => 10],
                ['account_id' => 2, 'account_code' => 'E1', 'account_name' => 'E One', 'entry_type' => 'credit', 'amount' => 10],
            ],
            'notes' => 'older posting',
        ]);

        $second = OpeningBalancePosting::create([
            'is_repost' => true,
            'posting_date' => now()->toDateString(),
            'total_debit' => 20,
            'total_credit' => 20,
            'line_count' => 2,
            'snapshot' => [
                ['account_id' => 3, 'account_code' => 'A2', 'account_name' => 'A Two', 'entry_type' => 'debit', 'amount' => 20],
                ['account_id' => 4, 'account_code' => 'E2', 'account_name' => 'E Two', 'entry_type' => 'credit', 'amount' => 20],
            ],
            'notes' => 'newer posting',
        ]);

        $newestResponse = app(AccountApiController::class)->openingBalanceHistory(
            Request::create('/', 'GET', ['sort' => 'newest'])
        );
        $newestPayload = $newestResponse->getData(true);

        $oldestResponse = app(AccountApiController::class)->openingBalanceHistory(
            Request::create('/', 'GET', ['sort' => 'oldest'])
        );
        $oldestPayload = $oldestResponse->getData(true);

        $this->assertSame($second->id, $newestPayload['data'][0]['id']);
        $this->assertSame($first->id, $oldestPayload['data'][0]['id']);
    }
}
