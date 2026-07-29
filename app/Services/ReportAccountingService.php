<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\Expense;
use App\Models\OpdPatient;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportAccountingService
{
    public function getBillRowsByDate(array $dateConditions): Collection
    {
        $billingsQuery = Billing::where('status', 'Active');
        $this->applyDateFilter($billingsQuery, $dateConditions, 'created_at');
        $billingsByDate = $billingsQuery->get([
            'id',
            'bill_number',
            'invoice_number',
            'created_at',
            'case_number',
            'total',
            'discount',
            'discount_type',
            'extra_flat_discount',
            'payable_amount',
            'due_amount',
            'return_amt'
        ]);

        $dueCollectionsInRange = DueCollection::query();
        $this->applyDateFilter($dueCollectionsInRange, $dateConditions, 'collected_at');
        $dueCollectionIds = $dueCollectionsInRange->pluck('billing_id');

        $billingIds = $billingsByDate->pluck('id')
            ->merge($dueCollectionIds)
            ->unique();

        if ($billingIds->isEmpty()) {
            return collect();
        }

        $billings = Billing::whereIn('id', $billingIds)
            ->where('status', 'Active')
            ->get([
                'id',
                'bill_number',
                'invoice_number',
                'created_at',
                'case_number',
                'total',
                'discount',
                'discount_type',
                'extra_flat_discount',
                'payable_amount',
                'due_amount',
                'return_amt'
            ])
            ->sortBy('created_at')
            ->values();

        $billingDateById = $billings->mapWithKeys(function ($billing) {
            return [$billing->id => Carbon::parse($billing->created_at)->format('d-M-Y')];
        });

        $useDateRangeAmounts = $this->hasDateFilter($dateConditions);

        $paymentsQuery = Payment::whereIn('billing_id', $billingIds);
        $payments = $paymentsQuery->get(['billing_id', 'amount', 'created_at']);

        $paymentsInRangeQuery = Payment::whereIn('billing_id', $billingIds);
        $this->applyDateFilter($paymentsInRangeQuery, $dateConditions, 'created_at');
        $paymentsInRange = $paymentsInRangeQuery->get(['billing_id', 'amount', 'created_at']);

        $paidAtBillingById = $payments->groupBy('billing_id')->map(function ($items) {
            return $items->sum('amount');
        });

        $paidAtBillingByIdInRange = $paymentsInRange->groupBy('billing_id')->map(function ($items) {
            return $items->sum('amount');
        });

        $dueCollectionsAll = DueCollection::whereIn('billing_id', $billingIds)
            ->selectRaw('billing_id, SUM(collected_amount) as total_collected')
            ->groupBy('billing_id')
            ->pluck('total_collected', 'billing_id');

        $dueCollectionsInRangeQuery = DueCollection::whereIn('billing_id', $billingIds);
        $this->applyDateFilter($dueCollectionsInRangeQuery, $dateConditions, 'collected_at');
        $dueCollectionsByBillingInRange = $dueCollectionsInRangeQuery
            ->selectRaw('billing_id, SUM(collected_amount) as total_collected')
            ->groupBy('billing_id')
            ->pluck('total_collected', 'billing_id');

        return $billings->map(function ($billing) use ($useDateRangeAmounts, $paidAtBillingById, $paidAtBillingByIdInRange, $dueCollectionsAll, $dueCollectionsByBillingInRange) {
            $discountAmount = $billing->discount_type === 'percentage'
                ? (($billing->total * $billing->discount) / 100)
                : $billing->discount;

            $discountAmount = max(0, (float) $discountAmount);
            $extraDiscount = max(0, (float) $billing->extra_flat_discount);
            $netAmount = max(0, (float) $billing->total - $discountAmount - $extraDiscount);

            $paidAmount = (float) ($useDateRangeAmounts
                ? $paidAtBillingByIdInRange->get($billing->id, 0)
                : $paidAtBillingById->get($billing->id, 0));
            $dueCollectedTotal = (float) ($useDateRangeAmounts
                ? $dueCollectionsByBillingInRange->get($billing->id, 0)
                : $dueCollectionsAll->get($billing->id, 0));
            $dueCollectedInRange = (float) $dueCollectionsByBillingInRange->get($billing->id, 0);
            $computedDueAmount = max(0, $netAmount - $paidAmount - $dueCollectedTotal);
            $storedDueAmount = $billing->due_amount;

            // If there are any payments or due-collections applied, prefer the computed
            // due amount so the displayed due reflects collected amounts.
            if ($dueCollectedTotal > 0 || $paidAmount > 0) {
                $dueAmount = $computedDueAmount;
            } else {
                $dueAmount = $storedDueAmount !== null
                    ? max(0, (float) $storedDueAmount)
                    : $computedDueAmount;
            }

            $vatPercent = max(0, (float) ($billing->vat_percentage ?? 0));
            $vatAmount = max(0, (float) ($billing->vat_amount ?? 0));
            $computedPayable = max(0, $netAmount + $vatAmount);

            return [
                'billing_id' => $billing->id,
                'bill_no' => $billing->bill_number ?? $billing->invoice_number ?? 'N/A',
                'billing_date' => Carbon::parse($billing->created_at)->format('d-M-Y'),
                'total_amount' => round((float) $billing->total, 2),
                'discount_amount' => round($discountAmount, 2),
                'extra_discount' => round($extraDiscount, 2),
                'vat_percent' => round($vatPercent, 2),
                'vat_amount' => round($vatAmount, 2),
                'net_amount' => round($computedPayable, 2),
                'paid_amount' => round($paidAmount, 2),
                'due_amount' => round($dueAmount, 2),
                'due_collected' => round($dueCollectedInRange, 2),
                'due_collected_total' => round($dueCollectedTotal, 2),
                'return_amt' => round((float) $billing->return_amt, 2)
            ];
        });
    }

    public function calculateBillTotals(Collection $billRows): array
    {
        if ($billRows->isEmpty()) {
            return [];
        }

        return [
            'total_amount' => $billRows->sum('total_amount'),
            'discount_amount' => $billRows->sum('discount_amount'),
            'extra_discount' => $billRows->sum('extra_discount'),
            'vat_amount' => $billRows->sum('vat_amount'),
            'net_amount' => $billRows->sum('net_amount'),
            'paid_amount' => $billRows->sum('paid_amount'),
            'due_amount' => $billRows->sum('due_amount'),
            'due_collected' => $billRows->sum('due_collected')
        ];
    }

    public function calculateFinalIncomeTotals(Collection $billRows, array $dateConditions): array
    {
        $totalPaidAmount = $billRows->sum('paid_amount');
        $totalDueCollected = $billRows->sum('due_collected');

        $expenseQuery = Expense::where('status', 'Active');
        $this->applyDateFilter($expenseQuery, $dateConditions, 'date');
        $totalExpense = $expenseQuery->sum('amount');

        // Calculate total processed refunds from refund transactions in the selected date range.
        // Some callers pass single_date/date_from/date_to conditions instead of single_date_range,
        // so use the shared date filter rather than assuming the array key is always present.
        $refundQuery = DB::table('refund_transactions');
        $this->applyDateFilter($refundQuery, $dateConditions, 'created_at');
        $totalRefundAmount = (float) $refundQuery->sum('refund_amount');

        // Net Income = (Paid + Due Collected) - Expenses - Processed Refunds
        $finalIncome = ($totalPaidAmount + $totalDueCollected) - $totalExpense - $totalRefundAmount;

        return [
            'total_paid' => $totalPaidAmount,
            'total_due_collected' => $totalDueCollected,
            'total_expense' => $totalExpense,
            // Keep legacy key but also provide the controller-expected key
            'total_return' => $totalRefundAmount,
            'total_return_amount' => $totalRefundAmount,
            'final_income' => $finalIncome
        ];
    }

    public function getExpenseTotal(array $dateConditions): float
    {
        $expenseQuery = Expense::where('status', 'Active');
        $this->applyDateFilter($expenseQuery, $dateConditions, 'date');

        return (float) $expenseQuery->sum('amount');
    }

    public function getRefundTotal(array $dateConditions): float
    {
        $refundQuery = DB::table('refund_transactions');
        $this->applyDateFilter($refundQuery, $dateConditions, 'created_at');

        return (float) $refundQuery->sum('refund_amount');
    }

    public function getDueCollectionTotal(array $dateConditions): float
    {
        $query = DueCollection::query();
        $this->applyDateFilter($query, $dateConditions, 'collected_at');
        $rows = $query->get(['billing_id', 'collected_amount', 'payment_method', 'note']);

        $billingIds = $rows
            ->pluck('billing_id')
            ->filter()
            ->unique()
            ->values();

        $activeBillingMap = Billing::query()
            ->whereIn('id', $billingIds)
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->flip();

        $opdIds = $rows
            ->filter(function ($row) {
                return strtolower((string) ($row->payment_method ?? '')) === 'opd' && empty($row->billing_id);
            })
            ->map(function ($row) {
                $matches = [];
                preg_match('/opd_patient_id:\s*(\d+)/i', (string) ($row->note ?? ''), $matches);
                return isset($matches[1]) ? (int) $matches[1] : null;
            })
            ->filter()
            ->unique()
            ->values();

        $activeOpdMap = OpdPatient::query()
            ->whereIn('id', $opdIds)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->flip();

        return (float) $rows->sum(function ($row) use ($activeBillingMap, $activeOpdMap) {
            $billingId = $row->billing_id;
            if (!empty($billingId)) {
                return $activeBillingMap->has((int) $billingId)
                    ? (float) ($row->collected_amount ?? 0)
                    : 0;
            }

            if (strtolower((string) ($row->payment_method ?? '')) === 'opd') {
                $matches = [];
                preg_match('/opd_patient_id:\s*(\d+)/i', (string) ($row->note ?? ''), $matches);
                $opdId = isset($matches[1]) ? (int) $matches[1] : null;

                if ($opdId && !$activeOpdMap->has($opdId)) {
                    return 0;
                }
            }

            return (float) ($row->collected_amount ?? 0);
        });
    }

    public function getIncomeReportNetIncome(array $dateConditions): float
    {
        $billingQuery = Billing::query()
            ->where('status', 'Active')
            ->where('payment_status', 'Paid');

        $this->applyDateFilter($billingQuery, $dateConditions, 'created_at');

        $billings = $billingQuery->get([
            'paid_amt',
            'total',
            'discount',
            'discount_type',
            'extra_flat_discount',
            'return_amt',
        ]);

        $totalPaidAmount = (float) $billings->sum('paid_amt');

        $totalDiscount = (float) $billings->sum(function ($billing) {
            $discountAmount = ($billing->discount_type === 'percentage')
                ? (($billing->total ?? 0) * $billing->discount) / 100
                : $billing->discount;

            return max(0, (float) $discountAmount) + max(0, (float) ($billing->extra_flat_discount ?? 0));
        });

        $totalReturnAmount = (float) $billings->sum(fn ($billing) => (float) ($billing->return_amt ?? 0));
        $totalDueCollected = $this->getDueCollectionTotal($dateConditions);

        return $totalPaidAmount + $totalDueCollected - $totalDiscount - $totalReturnAmount;
    }

    public function getTotalIncome(array $dateConditions): float
    {
        $billRows = $this->getBillRowsByDate($dateConditions);
        $totals = $this->calculateFinalIncomeTotals($billRows, $dateConditions);

        return (float) (($totals['total_paid'] ?? 0) + ($totals['total_due_collected'] ?? 0));
    }

    private function hasDateFilter(array $dateConditions): bool
    {
        return !empty($dateConditions['single_date_range'])
            || isset($dateConditions['single_date'])
            || isset($dateConditions['date_from'])
            || isset($dateConditions['date_to']);
    }

    private function applyDateFilter($query, array $dateConditions, string $dateField = 'created_at')
    {
        if (isset($dateConditions['single_date_range']) && is_array($dateConditions['single_date_range']) && count($dateConditions['single_date_range']) === 2) {
            [$start, $end] = $dateConditions['single_date_range'];
            $query->whereBetween($dateField, [$start, $end]);
        } elseif (isset($dateConditions['single_date'])) {
            $query->whereDate($dateField, $dateConditions['single_date']->toDateString());
        } elseif (isset($dateConditions['date_from']) && isset($dateConditions['date_to'])) {
            $query->where(function ($q) use ($dateField, $dateConditions) {
                $q->whereDate($dateField, '>=', $dateConditions['date_from']->toDateString())
                    ->whereDate($dateField, '<=', $dateConditions['date_to']->toDateString());
            });
        } elseif (isset($dateConditions['date_from'])) {
            $query->whereDate($dateField, '>=', $dateConditions['date_from']->toDateString());
        } elseif (isset($dateConditions['date_to'])) {
            $query->whereDate($dateField, '<=', $dateConditions['date_to']->toDateString());
        }

        return $query;
    }
}
