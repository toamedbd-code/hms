<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Billing;
use App\Models\CashCounterSession;
use App\Models\CashCounterTransaction;
use App\Models\DueCollection;
use Illuminate\Support\Facades\DB;

class CashCounterService
{
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

            if (!empty($data['opening_note'])) {
                $this->recordTransaction($session, 'opening', (float) ($data['opening_amount'] ?? 0), $data['opening_note']);
            }

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

            return $transaction;
        });
    }

    public function closeSession(int $sessionId, float $closingAmount, string $note = null): CashCounterSession
    {
        return DB::transaction(function () use ($sessionId, $closingAmount, $note) {
            $session = CashCounterSession::findOrFail($sessionId);
            $session->closing_amount = (float) $closingAmount;
            $session->difference_amount = (float) $closingAmount - (float) $session->expected_amount;
            $session->status = 'closed';
            $session->closed_at = now();
            $session->save();

            $this->recordTransaction($session, 'close', $closingAmount, $note);

            return $session;
        });
    }

    public function getSummary(int $sessionId): array
    {
        $session = CashCounterSession::findOrFail($sessionId);

        return [
            'session' => $session,
            'transactions' => $session->transactions()->latest('id')->get(),
            'opening_amount' => (float) $session->opening_amount,
            'expected_amount' => (float) $session->expected_amount,
            'closing_amount' => (float) $session->closing_amount,
            'difference_amount' => (float) $session->difference_amount,
            'handover_in_amount' => (float) $session->handover_in_amount,
            'handover_out_amount' => (float) $session->handover_out_amount,
        ];
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
        $from = $session->opened_at ?? $session->created_at;
        $to = $session->closed_at ?? now();

        $scopeToActorOnly = !$includeAllUsers && !empty($actorAdminId);

        $billingRows = Billing::query()
            ->select([
                'created_by',
                DB::raw('COUNT(*) as bill_count'),
                DB::raw('COALESCE(SUM(paid_amt), 0) as billing_paid_total'),
                DB::raw('COALESCE(SUM(due_amount), 0) as billing_due_total'),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->when($scopeToActorOnly, function ($query) use ($actorAdminId) {
                $query->where('created_by', $actorAdminId);
            })
            ->groupBy('created_by')
            ->get();

        $dueRows = DueCollection::query()
            ->leftJoin('billings', 'billings.id', '=', 'due_collections.billing_id')
            ->select([
                DB::raw('COALESCE(due_collections.created_by, billings.created_by) as created_by'),
                DB::raw('COUNT(DISTINCT due_collections.billing_id) as due_bill_id_count'),
                DB::raw('COUNT(*) as due_collection_count'),
                DB::raw('COALESCE(SUM(due_collections.collected_amount), 0) as due_collection_total'),
            ])
            ->whereBetween('due_collections.created_at', [$from, $to])
            ->when($scopeToActorOnly, function ($query) use ($actorAdminId) {
                $query->where(function ($nestedQuery) use ($actorAdminId) {
                    $nestedQuery->where('due_collections.created_by', $actorAdminId)
                        ->orWhere(function ($fallbackQuery) use ($actorAdminId) {
                            $fallbackQuery->whereNull('due_collections.created_by')
                                ->where('billings.created_by', $actorAdminId);
                        });
                });
            })
            ->groupBy('created_by')
            ->get();

        $userIds = collect()
            ->merge($billingRows->pluck('created_by'))
            ->merge($dueRows->pluck('created_by'))
            ->filter()
            ->unique()
            ->values();

        $admins = Admin::query()
            ->select('id', 'first_name', 'last_name')
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $detailRows = Billing::query()
            ->select([
                'id',
                'bill_number',
                'created_by',
                'created_at',
                'total',
                'discount',
                'extra_flat_discount',
                'payable_amount',
                'paid_amt',
                'due_amount',
            ])
            ->whereBetween('created_at', [$from, $to])
            ->when($scopeToActorOnly, function ($query) use ($actorAdminId) {
                $query->where('created_by', $actorAdminId);
            })
            ->orderBy('created_at')
            ->get();

        $dueCollectedByBilling = DueCollection::query()
            ->leftJoin('billings', 'billings.id', '=', 'due_collections.billing_id')
            ->select([
                'due_collections.billing_id',
                DB::raw('COALESCE(SUM(due_collections.collected_amount), 0) as due_collected'),
            ])
            ->whereNotNull('due_collections.billing_id')
            ->whereBetween('due_collections.created_at', [$from, $to])
            ->when($scopeToActorOnly, function ($query) use ($actorAdminId) {
                $query->where(function ($nestedQuery) use ($actorAdminId) {
                    $nestedQuery->where('due_collections.created_by', $actorAdminId)
                        ->orWhere(function ($fallbackQuery) use ($actorAdminId) {
                            $fallbackQuery->whereNull('due_collections.created_by')
                                ->where('billings.created_by', $actorAdminId);
                        });
                });
            })
            ->groupBy('due_collections.billing_id')
            ->pluck('due_collected', 'due_collections.billing_id');

        $detailUserIds = $detailRows->pluck('created_by')->filter()->unique()->values();
        if ($detailUserIds->isNotEmpty()) {
            $extraAdmins = Admin::query()
                ->select('id', 'first_name', 'last_name')
                ->whereIn('id', $detailUserIds)
                ->get()
                ->keyBy('id');
            $admins = $admins->merge($extraAdmins);
        }

        $sessionUserName = trim((string) ($session->user_name ?? ''));

        $detailedBillingRows = $detailRows->map(function ($row) use ($dueCollectedByBilling, $admins, $session, $sessionUserName) {
            $dueCollected = (float) ($dueCollectedByBilling->get($row->id) ?? 0);
            $admin = $admins->get($row->created_by);
            $userName = trim((string) (($admin?->first_name ?? '') . ' ' . ($admin?->last_name ?? '')));
            if ($userName === '' && (int) ($row->created_by ?? 0) === (int) ($session->created_by ?? 0) && $sessionUserName !== '') {
                $userName = $sessionUserName;
            }

            $paid = (float) ($row->paid_amt ?? 0);

            return [
                'billing_id' => (int) $row->id,
                'bill_number' => (string) ($row->bill_number ?? ('BILL-' . $row->id)),
                'billing_date' => $row->created_at,
                'user_name' => $userName !== '' ? $userName : ('User #' . (int) ($row->created_by ?? 0)),
                'total_amount' => (float) ($row->total ?? 0),
                'discount_amount' => (float) ($row->discount ?? 0),
                'extra_discount' => (float) ($row->extra_flat_discount ?? 0),
                'net_amount' => (float) ($row->payable_amount ?? 0),
                'paid_amount' => $paid,
                'due_amount' => (float) ($row->due_amount ?? 0),
                'due_collected' => $dueCollected,
                'total_collected' => $paid + $dueCollected,
            ];
        })->values();

        $detailedTotals = [
            'bill_count' => (int) $detailedBillingRows->count(),
            'total_amount' => (float) $detailedBillingRows->sum('total_amount'),
            'discount_amount' => (float) $detailedBillingRows->sum('discount_amount'),
            'extra_discount' => (float) $detailedBillingRows->sum('extra_discount'),
            'net_amount' => (float) $detailedBillingRows->sum('net_amount'),
            'paid_amount' => (float) $detailedBillingRows->sum('paid_amount'),
            'due_amount' => (float) $detailedBillingRows->sum('due_amount'),
            'due_collected' => (float) $detailedBillingRows->sum('due_collected'),
            'total_collected' => (float) $detailedBillingRows->sum('total_collected'),
        ];

        $billingByUser = $billingRows->keyBy('created_by');
        $dueByUser = $dueRows->keyBy('created_by');
        $userSummaries = $userIds->map(function ($userId) use ($admins, $billingByUser, $dueByUser, $session, $sessionUserName) {
            $billing = $billingByUser->get($userId);
            $due = $dueByUser->get($userId);

            $billingPaid = (float) ($billing->billing_paid_total ?? 0);
            $dueCollected = (float) ($due->due_collection_total ?? 0);

            $admin = $admins->get($userId);
            $resolvedName = trim((string) (($admin?->first_name ?? '') . ' ' . ($admin?->last_name ?? '')));
            if ($resolvedName === '' && (int) $userId === (int) ($session->created_by ?? 0) && $sessionUserName !== '') {
                $resolvedName = $sessionUserName;
            }

            return [
                'user_id' => (int) $userId,
                'user_name' => $resolvedName !== '' ? $resolvedName : ('User #' . $userId),
                'bill_count' => (int) ($billing->bill_count ?? 0),
                'due_bill_id_count' => (int) ($due->due_bill_id_count ?? 0),
                'due_collection_count' => (int) ($due->due_collection_count ?? 0),
                'billing_paid_total' => $billingPaid,
                'due_collection_total' => $dueCollected,
                'total_collection' => $billingPaid + $dueCollected,
            ];
        })->sortByDesc('total_collection')->values();

        $totals = [
            'bill_count' => (int) $userSummaries->sum('bill_count'),
            'due_bill_id_count' => (int) $userSummaries->sum('due_bill_id_count'),
            'due_collection_count' => (int) $userSummaries->sum('due_collection_count'),
            'billing_paid_total' => (float) $userSummaries->sum('billing_paid_total'),
            'due_collection_total' => (float) $userSummaries->sum('due_collection_total'),
            'total_collection' => (float) $userSummaries->sum('total_collection'),
        ];

        $counterTransactions = $session->transactions()->latest('id')->get();

        $billingSourceRows = Billing::query()
            ->select([
                DB::raw("LOWER(COALESCE(NULLIF(pay_mode, ''), 'unknown')) as source"),
                DB::raw('COUNT(*) as item_count'),
                DB::raw('COALESCE(SUM(paid_amt), 0) as amount_total'),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->when($scopeToActorOnly, function ($query) use ($actorAdminId) {
                $query->where('created_by', $actorAdminId);
            })
            ->groupBy('source')
            ->orderByDesc('amount_total')
            ->get();

        $dueSourceRows = DueCollection::query()
            ->select([
                DB::raw("LOWER(COALESCE(NULLIF(payment_method, ''), 'due')) as source"),
                DB::raw('COUNT(*) as item_count'),
                DB::raw('COALESCE(SUM(collected_amount), 0) as amount_total'),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->when($scopeToActorOnly, function ($query) use ($actorAdminId) {
                $query->where('created_by', $actorAdminId);
            })
            ->groupBy('source')
            ->orderByDesc('amount_total')
            ->get();

        return [
            'session' => $session,
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'userSummaries' => $userSummaries,
            'totals' => $totals,
            'counterTotals' => [
                'opening_amount' => (float) $session->opening_amount,
                'expected_amount' => (float) $session->expected_amount,
                'closing_amount' => (float) $session->closing_amount,
                'difference_amount' => (float) $session->difference_amount,
                'handover_in_amount' => (float) $session->handover_in_amount,
                'handover_out_amount' => (float) $session->handover_out_amount,
            ],
            'sourceBreakdown' => [
                'billing' => $billingSourceRows->map(function ($row) {
                    return [
                        'source' => (string) $row->source,
                        'item_count' => (int) $row->item_count,
                        'amount_total' => (float) $row->amount_total,
                    ];
                })->values(),
                'due' => $dueSourceRows->map(function ($row) {
                    return [
                        'source' => (string) $row->source,
                        'item_count' => (int) $row->item_count,
                        'amount_total' => (float) $row->amount_total,
                    ];
                })->values(),
            ],
            'detailedBillingRows' => $detailedBillingRows,
            'detailedTotals' => $detailedTotals,
            'transactions' => $counterTransactions,
        ];
    }
}
