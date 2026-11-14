<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Billing;
use App\Models\CashCounterSession;
use App\Models\CashCounterTransaction;
use App\Models\DueCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CashCounterService
{
    public function findOpenSessionForAdmin(int $adminId, string $adminName = ''): ?CashCounterSession
    {
        if ($adminId <= 0) {
            return null;
        }

        $adminName = trim($adminName);

        return CashCounterSession::query()
            ->whereRaw('LOWER(status) = ?', ['open'])
            ->where(function ($query) use ($adminId, $adminName) {
                $query->where('created_by', $adminId);

                if ($adminName !== '') {
                    $query->orWhere(function ($subQuery) use ($adminName) {
                        $subQuery->whereNull('created_by')
                            ->where('user_name', $adminName);
                    });
                }
            })
            ->latest('opened_at')
            ->first();
    }

    public function findLastClosedSessionForAdmin(int $adminId): ?CashCounterSession
    {
        if ($adminId <= 0) {
            return null;
        }

        return CashCounterSession::query()
            ->where('created_by', $adminId)
            ->whereRaw('LOWER(status) = ?', ['closed'])
            ->latest('closed_at')
            ->first();
    }

    public function startSession(array $data): CashCounterSession
    {
        return DB::transaction(function () use ($data) {
            $session = CashCounterSession::create([
                'counter_name' => $data['counter_name'] ?? 'Main Counter',
                'user_name' => $data['user_name'] ?? 'Unknown',
                'shift_name' => $data['shift_name'] ?? 'Default',
                'opening_amount' => (float) ($data['opening_amount'] ?? 0),
                'expected_amount' => (float) ($data['opening_amount'] ?? 0),
                'opening_note' => $data['opening_note'] ?? null,
                'opened_at' => $data['opened_at'] ?? now(),
                'created_by' => $data['created_by'] ?? null,
                'status' => 'open',
            ]);

            $this->recordTransaction($session, 'opening', (float) ($data['opening_amount'] ?? 0), $data['opening_note'] ?? 'Counter started');
            $this->forgetAdminCounterCache($session->created_by);

            return $session;
        });
    }

    public function recordInput(int $sessionId, float $amount, string $note = null): CashCounterTransaction
    {
        return DB::transaction(function () use ($sessionId, $amount, $note) {
            $session = CashCounterSession::findOrFail($sessionId);
            $transaction = $this->recordTransaction($session, 'input', $amount, $note);
            $session->expected_amount = (float) $session->expected_amount + $amount;
            $session->save();
            $this->forgetAdminCounterCache($session->created_by);

            return $transaction;
        });
    }

    public function recordHandover(int $fromSessionId, float $amount, int $toSessionId, string $note = null): CashCounterTransaction
    {
        return DB::transaction(function () use ($fromSessionId, $amount, $toSessionId, $note) {
            $from = CashCounterSession::findOrFail($fromSessionId);
            $to = CashCounterSession::findOrFail($toSessionId);

            $transaction = $this->recordTransaction($from, 'handover_out', $amount, $note);
            $from->expected_amount = (float) $from->expected_amount - $amount;
            $from->handover_out_amount = (float) $from->handover_out_amount + $amount;
            $from->save();

            $to->handover_in_amount = (float) $to->handover_in_amount + $amount;
            $to->expected_amount = (float) $to->expected_amount + $amount;
            $to->save();
            $this->forgetAdminCounterCache($from->created_by);
            $this->forgetAdminCounterCache($to->created_by);

            return $transaction;
        });
    }

    public function closeSession(int $sessionId, float $closingAmount, string $note = null): CashCounterSession
    {
        return DB::transaction(function () use ($sessionId, $closingAmount, $note) {
            $session = CashCounterSession::query()->lockForUpdate()->findOrFail($sessionId);

            if (strtolower((string) $session->status) === 'closed') {
                throw new \RuntimeException('This counter session is already closed.');
            }

            $session->closed_at = now();
            $session->expected_amount = $this->calculateExpectedDrawerAmount($session);
            $session->closing_amount = (float) $closingAmount;
            $session->difference_amount = round((float) $closingAmount - (float) $session->expected_amount, 2);
            $session->status = 'closed';
            $session->save();

            $this->recordTransaction($session, 'close', $closingAmount, $note);
            $this->forgetAdminCounterCache($session->created_by);

            return $session->fresh();
        });
    }

    public function calculateExpectedDrawerAmount(CashCounterSession $session): float
    {
        return (float) ($this->getSessionMoneySummary($session)['expected_amount'] ?? 0);
    }

    public function getSessionMoneySummary(CashCounterSession $session): array
    {
        $from = $session->opened_at ?? $session->created_at ?? now();
        $to = $session->closed_at ?? now();
        $adminId = (int) ($session->created_by ?? 0);
        $sessionUserName = trim((string) ($session->user_name ?? ''));

        $inputTotal = 0.0;
        try {
            $inputTotal = (float) $session->transactions()
                ->where('type', 'input')
                ->sum('amount');
        } catch (\Throwable $e) {
            $inputTotal = 0.0;
        }

        $lifetimeDueByBilling = collect();
        $sessionDueRows = collect();
        $sessionBills = collect();

        try {
            $lifetimeDueByBilling = DueCollection::query()
                ->select([
                    'billing_id',
                    DB::raw('COALESCE(SUM(collected_amount), 0) as due_collected'),
                ])
                ->whereNotNull('billing_id')
                ->groupBy('billing_id')
                ->pluck('due_collected', 'billing_id');
        } catch (\Throwable $e) {
            $lifetimeDueByBilling = collect();
        }

        try {
            $sessionDueQuery = DueCollection::query()
                ->whereBetween('created_at', [$from, $to]);

            if ($adminId > 0) {
                $sessionDueQuery->where('created_by', $adminId);
            }

            $sessionDueRows = $sessionDueQuery->get();
        } catch (\Throwable $e) {
            $sessionDueRows = collect();
        }

        try {
            $sessionBillQuery = Billing::query()
                ->whereBetween('created_at', [$from, $to]);

            if ($adminId > 0) {
                $sessionBillQuery->where('created_by', $adminId);
            }

            $sessionBills = $sessionBillQuery->orderBy('created_at')->get();
        } catch (\Throwable $e) {
            $sessionBills = collect();
        }

        $sessionDueByBilling = $sessionDueRows
            ->groupBy('billing_id')
            ->map(fn ($rows) => (float) $rows->sum('collected_amount'));

        $userIds = collect()
            ->merge($sessionBills->pluck('created_by'))
            ->merge($sessionDueRows->pluck('created_by'))
            ->filter()
            ->unique()
            ->values();

        $admins = collect();
        if ($userIds->isNotEmpty()) {
            $admins = Admin::query()
                ->select('id', 'first_name', 'last_name')
                ->whereIn('id', $userIds)
                ->get()
                ->keyBy('id');
        }

        $resolveUserName = function ($userId) use ($admins, $session, $sessionUserName) {
            $admin = $admins->get($userId);
            $resolvedName = trim((string) (($admin?->first_name ?? '') . ' ' . ($admin?->last_name ?? '')));
            if ($resolvedName === '' && (int) $userId === (int) ($session->created_by ?? 0) && $sessionUserName !== '') {
                $resolvedName = $sessionUserName;
            }

            return $resolvedName !== '' ? $resolvedName : ('User #' . (int) $userId);
        };

        $detailedBillingRows = $sessionBills->map(function ($row) use ($lifetimeDueByBilling, $sessionDueByBilling, $resolveUserName) {
            $lifetimeDue = (float) ($lifetimeDueByBilling->get($row->id) ?? 0);
            $originalPaid = max(0, round((float) ($row->paid_amt ?? 0) - $lifetimeDue, 2));
            $dueCollected = (float) ($sessionDueByBilling->get($row->id) ?? 0);

            return [
                'billing_id' => (int) $row->id,
                'bill_number' => (string) ($row->bill_number ?? ('BILL-' . $row->id)),
                'billing_date' => $row->created_at,
                'user_name' => $resolveUserName($row->created_by),
                'user_id' => (int) ($row->created_by ?? 0),
                'pay_mode' => (string) ($row->pay_mode ?: 'Cash'),
                'total_amount' => (float) ($row->total ?? 0),
                'discount_amount' => (float) ($row->discount ?? 0),
                'extra_discount' => (float) ($row->extra_flat_discount ?? 0),
                'net_amount' => (float) ($row->payable_amount ?? 0),
                'paid_amount' => $originalPaid,
                'due_amount' => (float) ($row->due_amount ?? 0),
                'due_collected' => $dueCollected,
                'total_collected' => $originalPaid + $dueCollected,
            ];
        })->values();

        $sessionBillIds = $sessionBills->pluck('id')->map(fn ($id) => (int) $id)->all();
        $extraDueBillIds = $sessionDueRows
            ->pluck('billing_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => in_array($id, $sessionBillIds, true))
            ->values();

        if ($extraDueBillIds->isNotEmpty()) {
            $extraBills = Billing::query()->whereIn('id', $extraDueBillIds)->get()->keyBy('id');
            foreach ($extraDueBillIds as $billingId) {
                $bill = $extraBills->get($billingId);
                $dueCollected = (float) ($sessionDueByBilling->get($billingId) ?? 0);
                if ($dueCollected <= 0) {
                    continue;
                }

                $detailedBillingRows->push([
                    'billing_id' => (int) $billingId,
                    'bill_number' => (string) ($bill?->bill_number ?? ('BILL-' . $billingId)),
                    'billing_date' => $bill?->created_at,
                    'user_name' => $resolveUserName($bill?->created_by ?? $session->created_by),
                    'user_id' => (int) ($bill?->created_by ?? $session->created_by ?? 0),
                    'pay_mode' => (string) ($bill?->pay_mode ?: 'Due'),
                    'total_amount' => (float) ($bill?->total ?? 0),
                    'discount_amount' => (float) ($bill?->discount ?? 0),
                    'extra_discount' => (float) ($bill?->extra_flat_discount ?? 0),
                    'net_amount' => (float) ($bill?->payable_amount ?? 0),
                    'paid_amount' => 0.0,
                    'due_amount' => (float) ($bill?->due_amount ?? 0),
                    'due_collected' => $dueCollected,
                    'total_collected' => $dueCollected,
                ]);
            }
        }

        $billingPaidTotal = round((float) $detailedBillingRows->sum('paid_amount'), 2);
        $dueCollectedTotal = round((float) $sessionDueRows->sum('collected_amount'), 2);
        $collectionTotal = round($billingPaidTotal + $dueCollectedTotal, 2);
        $expectedAmount = round(
            (float) $session->opening_amount
            + $collectionTotal
            + $inputTotal
            + (float) $session->handover_in_amount
            - (float) $session->handover_out_amount,
            2
        );

        $userSummaries = $userIds->map(function ($userId) use ($sessionBills, $detailedBillingRows, $sessionDueRows, $resolveUserName) {
            $userBills = $detailedBillingRows->where('user_id', (int) $userId);
            $userDues = $sessionDueRows->where('created_by', (int) $userId);
            $billingPaid = round((float) $userBills->sum('paid_amount'), 2);
            $dueCollected = round((float) $userDues->sum('collected_amount'), 2);

            return [
                'user_id' => (int) $userId,
                'user_name' => $resolveUserName($userId),
                'bill_count' => (int) $sessionBills->where('created_by', (int) $userId)->count(),
                'due_bill_id_count' => (int) $userDues->pluck('billing_id')->filter()->unique()->count(),
                'due_collection_count' => (int) $userDues->count(),
                'billing_paid_total' => $billingPaid,
                'due_collection_total' => $dueCollected,
                'total_collection' => round($billingPaid + $dueCollected, 2),
            ];
        })->sortByDesc('total_collection')->values();

        if ($userSummaries->isEmpty() && $sessionUserName !== '') {
            $userSummaries = collect([[
                'user_id' => $adminId,
                'user_name' => $sessionUserName,
                'bill_count' => 0,
                'due_bill_id_count' => 0,
                'due_collection_count' => 0,
                'billing_paid_total' => 0.0,
                'due_collection_total' => 0.0,
                'total_collection' => 0.0,
            ]]);
        }

        $billingSources = $sessionBills
            ->groupBy(fn ($bill) => strtolower((string) ($bill->pay_mode ?: 'cash')))
            ->map(function ($rows, $source) use ($lifetimeDueByBilling) {
                $amount = 0.0;
                foreach ($rows as $bill) {
                    $lifetimeDue = (float) ($lifetimeDueByBilling->get($bill->id) ?? 0);
                    $amount += max(0, (float) ($bill->paid_amt ?? 0) - $lifetimeDue);
                }

                return [
                    'source' => (string) $source,
                    'item_count' => $rows->count(),
                    'amount_total' => round($amount, 2),
                ];
            })
            ->values();

        $dueSources = $sessionDueRows
            ->groupBy(fn ($row) => strtolower((string) ($row->payment_method ?: 'cash')))
            ->map(function ($rows, $source) {
                return [
                    'source' => (string) $source,
                    'item_count' => $rows->count(),
                    'amount_total' => round((float) $rows->sum('collected_amount'), 2),
                ];
            })
            ->values();

        $closingAmount = (float) ($session->closing_amount ?? 0);
        $differenceAmount = strtolower((string) $session->status) === 'closed'
            ? round($closingAmount - $expectedAmount, 2)
            : 0.0;

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'opening_amount' => (float) $session->opening_amount,
            'collection_total' => $collectionTotal,
            'billing_paid_total' => $billingPaidTotal,
            'due_collection_total' => $dueCollectedTotal,
            'input_total' => $inputTotal,
            'handover_in_amount' => (float) $session->handover_in_amount,
            'handover_out_amount' => (float) $session->handover_out_amount,
            'expected_amount' => $expectedAmount,
            'closing_amount' => $closingAmount,
            'difference_amount' => $differenceAmount,
            'detailedBillingRows' => $detailedBillingRows,
            'detailedTotals' => [
                'bill_count' => (int) $detailedBillingRows->count(),
                'total_amount' => (float) $detailedBillingRows->sum('total_amount'),
                'discount_amount' => (float) $detailedBillingRows->sum('discount_amount'),
                'extra_discount' => (float) $detailedBillingRows->sum('extra_discount'),
                'net_amount' => (float) $detailedBillingRows->sum('net_amount'),
                'paid_amount' => $billingPaidTotal,
                'due_amount' => (float) $detailedBillingRows->sum('due_amount'),
                'due_collected' => $dueCollectedTotal,
                'total_collected' => $collectionTotal,
            ],
            'userSummaries' => $userSummaries,
            'totals' => [
                'bill_count' => (int) $userSummaries->sum('bill_count'),
                'due_bill_id_count' => (int) $userSummaries->sum('due_bill_id_count'),
                'due_collection_count' => (int) $userSummaries->sum('due_collection_count'),
                'billing_paid_total' => $billingPaidTotal,
                'due_collection_total' => $dueCollectedTotal,
                'total_collection' => $collectionTotal,
            ],
            'sourceBreakdown' => [
                'billing' => $billingSources,
                'due' => $dueSources,
            ],
        ];
    }

    public function getSummary(int $sessionId): array
    {
        $session = CashCounterSession::findOrFail($sessionId);
        $money = $this->getSessionMoneySummary($session);

        return [
            'session' => $session,
            'transactions' => $session->transactions()->latest('id')->get(),
            'opening_amount' => $money['opening_amount'],
            'expected_amount' => $money['expected_amount'],
            'drawer_expected_amount' => $money['expected_amount'],
            'collection_total' => $money['collection_total'],
            'closing_amount' => (float) $session->closing_amount,
            'difference_amount' => (float) $session->difference_amount,
            'handover_in_amount' => $money['handover_in_amount'],
            'handover_out_amount' => $money['handover_out_amount'],
        ];
    }

    protected function forgetAdminCounterCache($adminId): void
    {
        $adminId = (int) $adminId;
        if ($adminId > 0) {
            Cache::forget('cash_counter.info.' . $adminId);
        }
    }

    protected function recordTransaction(CashCounterSession $session, string $type, float $amount, ?string $note): CashCounterTransaction
    {
        return CashCounterTransaction::create([
            'cash_counter_session_id' => $session->id,
            'type' => $type,
            'amount' => $amount,
            'note' => $note,
            'created_by' => auth('admin')->id(),
        ]);
    }

    public function getHandoverPrintSummary(int $sessionId, ?int $actorAdminId = null, bool $includeAllUsers = true): array
    {
        $session = CashCounterSession::findOrFail($sessionId);
        $money = $this->getSessionMoneySummary($session);

        return [
            'session' => $session,
            'period' => $money['period'],
            'userSummaries' => $money['userSummaries'],
            'totals' => $money['totals'],
            'counterTotals' => [
                'opening_amount' => $money['opening_amount'],
                'collection_total' => $money['collection_total'],
                'input_total' => $money['input_total'],
                'expected_amount' => $money['expected_amount'],
                'closing_amount' => $money['closing_amount'],
                'difference_amount' => $money['difference_amount'],
                'handover_in_amount' => $money['handover_in_amount'],
                'handover_out_amount' => $money['handover_out_amount'],
            ],
            'sourceBreakdown' => $money['sourceBreakdown'],
            'detailedBillingRows' => $money['detailedBillingRows'],
            'detailedTotals' => $money['detailedTotals'],
            'transactions' => $session->transactions()->latest('id')->get(),
        ];
    }
}
