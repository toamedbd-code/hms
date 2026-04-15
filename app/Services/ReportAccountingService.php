<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\Expense;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
            'due_amount'
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
                'due_amount'
            ])
            ->sortBy('created_at')
            ->values();

        $billingDateById = $billings->mapWithKeys(function ($billing) {
            return [$billing->id => Carbon::parse($billing->created_at)->format('d-M-Y')];
        });

        $payments = Payment::whereIn('billing_id', $billingIds)
            ->get(['billing_id', 'amount', 'created_at']);

        $paidAtBillingById = $payments->groupBy('billing_id')->map(function ($items, $billingId) use ($billingDateById) {
            $billingDate = $billingDateById->get($billingId);
            return $items->filter(function ($payment) use ($billingDate) {
                return Carbon::parse($payment->created_at)->format('d-M-Y') === $billingDate;
            })->sum('amount');
        });

        $dueCollectionsAll = DueCollection::whereIn('billing_id', $billingIds)
            ->selectRaw('billing_id, SUM(collected_amount) as total_collected')
            ->groupBy('billing_id')
            ->pluck('total_collected', 'billing_id');

        $dueCollectionsInRange = DueCollection::whereIn('billing_id', $billingIds);
        $this->applyDateFilter($dueCollectionsInRange, $dateConditions, 'collected_at');
        $dueCollectionsByBillingInRange = $dueCollectionsInRange
            ->selectRaw('billing_id, SUM(collected_amount) as total_collected')
            ->groupBy('billing_id')
            ->pluck('total_collected', 'billing_id');

        return $billings->map(function ($billing) use ($paidAtBillingById, $dueCollectionsAll, $dueCollectionsByBillingInRange) {
            $discountAmount = $billing->discount_type === 'percentage'
                ? (($billing->total * $billing->discount) / 100)
                : $billing->discount;

            $discountAmount = max(0, (float) $discountAmount);
            $extraDiscount = max(0, (float) $billing->extra_flat_discount);
            $netAmount = max(0, (float) $billing->total - $discountAmount - $extraDiscount);

            $paidAmount = (float) $paidAtBillingById->get($billing->id, 0);
            $dueCollectedTotal = (float) $dueCollectionsAll->get($billing->id, 0);
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

            return [
                'bill_no' => $billing->bill_number ?? $billing->invoice_number ?? 'N/A',
                'billing_date' => Carbon::parse($billing->created_at)->format('d-M-Y'),
                'total_amount' => round((float) $billing->total, 2),
                'discount_amount' => round($discountAmount, 2),
                'extra_discount' => round($extraDiscount, 2),
                'net_amount' => round($netAmount, 2),
                'paid_amount' => round($paidAmount, 2),
                'due_amount' => round($dueAmount, 2),
                'due_collected' => round($dueCollectedInRange, 2),
                'due_collected_total' => round($dueCollectedTotal, 2)
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

        $finalIncome = ($totalPaidAmount + $totalDueCollected) - $totalExpense;

        return [
            'total_paid' => $totalPaidAmount,
            'total_due_collected' => $totalDueCollected,
            'total_expense' => $totalExpense,
            'final_income' => $finalIncome
        ];
    }

    private function applyDateFilter($query, array $dateConditions, string $dateField = 'created_at')
    {
        if (isset($dateConditions['single_date_range'])) {
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
