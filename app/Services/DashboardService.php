<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Billing;
use App\Models\BillItem;
use Illuminate\Support\Facades\Schema;
use App\Models\DueCollection;
use App\Models\Expense;
use App\Models\ProductReturn;
use App\Models\Referral;
use App\Models\OpdPatient;
use App\Models\IpdPatient;
use App\Models\PharmacyBill;
use Carbon\Carbon;
use App\Services\ReportAccountingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    protected ReportAccountingService $accountingService;

    public function __construct(ReportAccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function todayDate()
    {
        return Carbon::today();
    }

    public function getDate($day = 'today')
    {
        if ($day === 'yesterday') {
            return Carbon::yesterday();
        }
        return Carbon::today();
    }

    public function getDateRange($day = 'today')
    {
        $appTimezone = config('app.timezone');
        $dbTimezone = config('database.connections.mysql.timezone_offset') ?: '+00:00';

        $date = $day === 'yesterday'
            ? Carbon::now($appTimezone)->subDay()
            : Carbon::now($appTimezone);

        $start = $date->copy()->startOfDay()->setTimezone($dbTimezone);
        $end = $date->copy()->endOfDay()->setTimezone($dbTimezone);

        return [$start, $end];
    }

    public function resolveDashboardFilter(?string $filterType, ?string $fromDate, ?string $toDate)
    {
        $allowedTypes = ['daily', 'monthly', 'yearly', 'custom'];
        $type = in_array($filterType, $allowedTypes, true) ? $filterType : 'daily';

        $appTimezone = config('app.timezone');
        $dbTimezone = config('database.connections.mysql.timezone_offset') ?: '+00:00';
        $now = Carbon::now($appTimezone);

        switch ($type) {
            case 'monthly':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
            case 'yearly':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                break;
            case 'custom':
                if ($fromDate && $toDate) {
                    $start = Carbon::parse($fromDate, $appTimezone)->startOfDay();
                    $end = Carbon::parse($toDate, $appTimezone)->endOfDay();
                    break;
                }
                $type = 'daily';
            default:
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
        }

        $appRange = [$start->copy(), $end->copy()];
        $dbRange = [$start->copy()->setTimezone($dbTimezone), $end->copy()->setTimezone($dbTimezone)];

        return [
            'type' => $type,
            'appRange' => $appRange,
            'dbRange' => $dbRange,
            'from' => $appRange[0]->toDateString(),
            'to' => $appRange[1]->toDateString(),
        ];
    }

    // ✔ Active Users
    public function countActiveUser()
    {
        return Admin::whereNull('deleted_at')
            ->where('status', 'Active')
            ->count();
    }

    // ✔ Inactive Users
    public function countInActiveUser()
    {
        return Admin::whereNull('deleted_at')
            ->where('status', 'Inactive')
            ->count();
    }

    // ✔ Pharmacy Income (Pharmacy module only) - collected amounts
    public function countPharmacyIncome(array $dbRange, array $dateRange)
    {
        // Identify pharmacy bills in the selected date range, then locate the
        // corresponding Billing records (by bill number) and sum payments + due-collections
        // recorded against those billings. This matches the report rows the user sees.
        $pharmacyRows = PharmacyBill::query()
            ->where('status', 'Active')
            // Use DB-range (timezone-aware) when querying pharmacy bill dates
            ->whereBetween('date', [
                $dbRange[0]->toDateString(),
                $dbRange[1]->toDateString(),
            ])
            // fetch safe fields only; amount fields may not exist on all schemas
            ->get(['id', 'bill_no', 'date', 'net_amount']);

        if ($pharmacyRows->isEmpty()) {
            return 0;
        }

        $returnDeduction = (float) ProductReturn::query()
            ->where('return_type', 'customer')
            ->whereIn('status', ['approved', 'processed'])
            ->whereBetween('return_date', [
                $dateRange[0]->toDateString(),
                $dateRange[1]->toDateString(),
            ])
            ->sum('total_amount');

        $billNos = $pharmacyRows->pluck('bill_no')->filter()->unique()->values()->toArray();
        if (empty($billNos)) {
            return 0;
        }

        // Try to compute pharmacy income directly from PharmacyBill totals (preferred).
        // Some installations record pharmacy totals on `pharmacybills` table, which
        // represents the actual pharmacy amount (e.g., small retail sums). If those
        // values exist, return their sum. Otherwise fall back to the previous
        // approach (summing payments recorded against linked Billing records).
        $pharmacySum = $pharmacyRows->sum(function ($row) {
            return (float) ($row->net_amount ?? $row->total ?? $row->net ?? $row->paid_amount ?? 0);
        });

        if ($pharmacySum > 0) {
            return max(0, $pharmacySum - $returnDeduction);
        }

        // Next preference: compute pharmacy amount from BillItem rows with category 'Pharmacy'
        $billingIds = Billing::whereIn('bill_number', $billNos)->pluck('id')->toArray();
        if (!empty($billingIds)) {
            // select only columns that exist in the current schema to avoid errors
            $cols = ['billing_id', 'id', 'category', 'item_id'];
            foreach (['total_amount', 'total', 'amount', 'net_amount', 'price', 'qty'] as $c) {
                if (Schema::hasColumn('bill_items', $c)) {
                    $cols[] = $c;
                }
            }

            // Treat both 'pharmacy' and 'medicine' categories as pharmacy items
                $pharmacyItems = BillItem::whereRaw('LOWER(category) IN (?,?)', ['pharmacy', 'medicine'])
                ->where('status', 'Active')
                ->whereIn('billing_id', $billingIds)
                ->get($cols);

            if ($pharmacyItems->isNotEmpty()) {
                // Sum per-billing pharmacy totals
                // Compute each item's effective total using any available field
                $pharmacyTotalsByBilling = $pharmacyItems->groupBy('billing_id')->map(function ($items) {
                        return $items->sum(function ($it) {
                            if ($it->net_amount !== null) {
                                return (float) $it->net_amount;
                            }
                        $val = 0;
                        if (!empty($it->total_amount)) {
                            $val = (float) $it->total_amount;
                        } elseif (!empty($it->total)) {
                            $val = (float) $it->total;
                        } elseif (!empty($it->amount)) {
                            $val = (float) $it->amount;
                        } elseif (!empty($it->net_amount)) {
                            $val = (float) $it->net_amount;
                        } elseif (isset($it->price) && isset($it->qty)) {
                            $val = (float) $it->price * (float) $it->qty;
                        } elseif (!empty($it->price)) {
                            $val = (float) $it->price;
                        }
                        return $val;
                    });
                });

                $netPharmacy = (float) $pharmacyTotalsByBilling->sum();

                if ($netPharmacy > 0) {
                    return max(0, $netPharmacy - $returnDeduction);
                }
            }
        }

        // Final fallback: sum payments + due collections against linked billings
        if (empty($billingIds)) {
            return 0;
        }

        $paymentsSum = (float) \App\Models\Payment::whereIn('billing_id', $billingIds)->sum('amount');
        $dueCollectionSum = (float) \App\Models\DueCollection::whereIn('billing_id', $billingIds)->sum('collected_amount');

        return max(0, ($paymentsSum + $dueCollectionSum) - $returnDeduction);
    }

    // ✔ Blood Bank Income - Net Income After Discount (from Billing)
    public function countBloodBankIncome(array $dbRange, array $dateRange)
    {
        $billItems = BillItem::where('category', 'Blood Bank')
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                    ->where('payment_status', '!=', 'Pending');
            })->get();

        if ($billItems->isEmpty()) {
            return 0;
        }

        $billingIds = $billItems->pluck('billing_id')->unique();
        $billings = Billing::whereIn('id', $billingIds)->get();

        return round((float) $billItems->sum('total_amount'), 2);
    }

    // ✔ Pathology Income - Net Income After Discount (from Billing)
    public function countPathologyIncome(array $dbRange, array $dateRange)
    {
        return (float) $this->getPathologyIncomeSummary($dbRange)['net'];
    }

    public function getPathologyIncomeSummary(array $dbRange): array
    {
        $billItems = BillItem::where('category', 'Pathology')
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                  ->where('status', 'Active')
                  ->where(function ($paymentQuery) {
                      $paymentQuery->where('payment_status', '!=', 'Pending')
                          ->orWhere('due_amount', '<=', 0)
                          ->orWhere('paid_amt', '>', 0);
                  });
            })->get();

        if ($billItems->isEmpty()) {
            return ['total' => 0.0, 'discount' => 0.0, 'net' => 0.0];
        }

        $total = (float) $billItems->sum('total_amount');
        $itemNet = (float) $billItems->sum(function ($item) {
            return $item->net_amount !== null
                ? $item->net_amount
                : ((float) $item->total_amount - (float) ($item->discount ?? 0));
        });
        $billings = Billing::whereIn('id', $billItems->pluck('billing_id')->unique())
            ->get(['id', 'extra_flat_discount']);
        $extraDiscount = (float) $billings->sum('extra_flat_discount');
        $discount = max(0, $total - $itemNet + $extraDiscount);

        return [
            'total' => round($total, 2),
            'discount' => round($discount, 2),
            'net' => round(max(0, $itemNet - $extraDiscount), 2),
        ];
    }

    // ✔ Radiology Income - Net Income After Discount (from Billing)
    public function countRadiologyIncome(array $dbRange, array $dateRange)
    {
        $billItems = BillItem::where('category', 'Radiology')
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                  ->where('payment_status', '!=', 'Pending');
            })->get();

        if ($billItems->isEmpty()) {
            return 0;
        }

        return round((float) $billItems->sum(function ($item) {
            return $item->net_amount !== null
                ? $item->net_amount
                : ((float) $item->total_amount - (float) ($item->discount ?? 0));
        }), 2);
    }

    // ✔ OPD Income (Today paid) - Actual paid amount
    public function countOpdIncome(array $dateRange)
    {
        // Primary source: OPD appointments (OpdPatient paid_amount)
        $opdPaid = OpdPatient::where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('payment_status', '!=', 'Pending')
            ->whereBetween('appointment_date', [
                $dateRange[0]->toDateString(),
                $dateRange[1]->toDateString(),
            ])
            ->sum('paid_amount');

        // Additional source: Bill items categorized as 'OPD' or 'Appointment'
        $billItemQuery = BillItem::whereRaw('LOWER(category) IN (?,?)', ['opd', 'appointment'])
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange[0], $dateRange[1]])
                    ->where('status', 'Active')
                    ->where('payment_status', '!=', 'Pending');
            });

        if ($billItemQuery->exists()) {
            $items = $billItemQuery->get();
            $totalsByBilling = $items->groupBy('billing_id')->map(function ($items) {
                return (float) $items->sum('total_amount');
            });

            $net = 0.0;
            $net = (float) $items->sum(function ($item) {
                return $item->net_amount !== null
                    ? $item->net_amount
                    : ((float) $item->total_amount - (float) ($item->discount ?? 0));
            });

            $opdPaid += round($net, 2);
        }

        return (float) $opdPaid;
    }

    // ✔ Disposable Income - Net Income After Discount (from Billing items categorized as Disposable)
    public function countDisposableIncome(array $dbRange, array $dateRange)
    {
        $billItems = BillItem::whereRaw('LOWER(category) = ?', ['disposable'])
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                    ->where('status', 'Active')
                    ->where('payment_status', '!=', 'Pending');
            })->get();

        if ($billItems->isEmpty()) {
            return 0;
        }

        return round((float) $billItems->sum('total_amount'), 2);
    }

    // ✔ IPD Income (from IPD billing) - Collected amount
    public function countIpdIncome(array $dbRange)
    {
        // Collect billing IDs linked to IPD patients within the requested DB range.
        $billingIdsFromIpd = IpdPatient::query()
            ->whereIn('status', ['Active', 'Inactive'])
            ->whereNotNull('billing_id')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                    ->where('status', 'Active');
            })->pluck('billing_id')->unique()->values()->toArray();

        // Also include billing IDs which contain bill_items categorized as 'IPD'
        $itemBillingIds = BillItem::query()
            ->whereRaw('LOWER(category) = ?', ['ipd'])
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                    ->where('status', 'Active');
            })->pluck('billing_id')->unique()->values()->toArray();

        // Merge both sources of billing ids (from ipd_patient links and from IPD bill items)
        $billingIds = array_values(array_unique(array_merge($billingIdsFromIpd, $itemBillingIds)));

        if (!empty($itemBillingIds)) {
            $ipdItems = BillItem::whereRaw('LOWER(category) = ?', ['ipd'])
                ->where('status', 'Active')
                ->whereIn('billing_id', $itemBillingIds)
                ->get(['total_amount', 'discount', 'net_amount']);

            return round((float) $ipdItems->sum(function ($item) {
                return $item->net_amount !== null
                    ? $item->net_amount
                    : ((float) $item->total_amount - (float) ($item->discount ?? 0));
            }), 2);
        }

        // Sum payments that are either explicitly linked to an IPD patient OR
        // are linked to a billing that belongs to an IPD discharge billing.
        $paymentQuery = \App\Models\Payment::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->whereBetween('created_at', $dbRange)
            ->where(function ($q) use ($billingIds) {
                $q->whereNotNull('ipd_patient_id');
                if (!empty($billingIds)) {
                    $q->orWhereIn('billing_id', $billingIds);
                }
            });

        $paymentsSum = (float) $paymentQuery->sum('amount');

        // Removed verbose debug logging to reduce log volume

        if ($paymentsSum > 0) {
            return $paymentsSum;
        }

        // Final fallback: sum paid_amt on billings linked to ipdpatients (legacy)
        if (empty($billingIds)) {
            return 0.0;
        }

        $billingPaidSum = (float) \App\Models\Billing::whereIn('id', $billingIds)
            ->where('status', 'Active')
            ->where('payment_status', '!=', 'Pending')
            ->sum('paid_amt');

        // Removed fallback logging to keep logs concise

        return $billingPaidSum;
    }

    // ✔ Pending Income (outstanding dues for the current dashboard filter range)
    // Billing due amounts use created_at, OPD balances use appointment_date or created_at.
    public function countPendingIncome(array $dbRange = null, array $dateRange = null)
    {
        $billingQuery = Billing::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('due_amount', '>', 0);

        if (!empty($dbRange)) {
            $billingQuery->whereBetween('created_at', $dbRange);
        }

        $billingDue = (float) $billingQuery->sum('due_amount');

        $opdQuery = OpdPatient::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('balance_amount', '>', 0);

        if (!empty($dateRange)) {
            $opdQuery->where(function ($query) use ($dateRange) {
                $query->whereBetween('appointment_date', [
                    $dateRange[0]->toDateString(),
                    $dateRange[1]->toDateString(),
                ])
                ->orWhereBetween('created_at', [
                    $dateRange[0]->toDateTimeString(),
                    $dateRange[1]->toDateTimeString(),
                ]);
            });
        }

        $opdDue = (float) $opdQuery->sum('balance_amount');

        return max(0, $billingDue + $opdDue);
    }

    // ✔ Total Income (module incomes + pending dues)
    public function countTotalIncome(array $dbRange, array $dateRange)
    {
        $dateConditions = [
            'single_date_range' => $dbRange,
        ];

        return $this->accountingService->getIncomeReportNetIncome($dateConditions);
    }

    // ✔ Total Discount (Today)
    public function countTotalDiscount(array $dbRange)
    {
        $dateConditions = [
            'single_date_range' => $dbRange,
        ];

        $billRows = $this->accountingService->getBillRowsByDate($dateConditions);
        $billTotals = $this->accountingService->calculateBillTotals($billRows);

        return (float) (($billTotals['discount_amount'] ?? 0) + ($billTotals['extra_discount'] ?? 0));
    }

    // ✔ Expense (Daily)
    public function countExpense(array $dateRange)
    {
        return $this->accountingService->getExpenseTotal([
            'single_date_range' => $dateRange,
        ]);
    }

    // ✔ Net Income uses the same paid/due/expense/refund basis as reports.
    public function countNetIncome(array $dbRange, array $dateRange)
    {
        $dateConditions = [
            'single_date_range' => $dbRange
        ];

        $billRows = $this->accountingService->getBillRowsByDate($dateConditions);
        $totalPaid = (float) $billRows->sum('paid_amount');
        $totalDueCollected = (float) $this->accountingService->getDueCollectionTotal($dateConditions);
        $totalExpense = (float) $this->accountingService->getExpenseTotal($dateConditions);
        $totalRefund = (float) $this->accountingService->getRefundTotal($dateConditions);

        return round($totalPaid + $totalDueCollected - $totalExpense - $totalRefund, 2);
    }

    // ✔ Total Refunds (Daily) = Sum of processed refunds from refund transactions
    public function countRefunds(array $dbRange, array $dateRange)
    {
        $dateConditions = [
            'single_date_range' => $dbRange,
        ];

        return $this->accountingService->getRefundTotal($dateConditions);
    }

    // Referral Commission (dashboard card): pending commission amount
    // Sum of (total_commission_amount - paid_amount) for active referrals.
    public function countReferralCommissionPending(array $dbRange = null): float
    {
        $query = Referral::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at');

        if (!empty($dbRange)) {
            $query->whereBetween('created_at', $dbRange);
        }

        $rows = $query->get(['total_commission_amount', 'paid_amount']);

        $pending = $rows->sum(function ($row) {
            $total = (float) ($row->total_commission_amount ?? 0);
            $paid = (float) ($row->paid_amount ?? 0);
            return max(0, $total - $paid);
        });

        return (float) round($pending, 2);
    }

    // Distinct referral payee IDs that still have pending commission.
    public function countReferralCommissionPendingPayees(array $dbRange = null): int
    {
        $query = Referral::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at');

        if (!empty($dbRange)) {
            $query->whereBetween('created_at', $dbRange);
        }

        $rows = $query->get(['payee_id', 'total_commission_amount', 'paid_amount']);

        return (int) $rows
            ->filter(function ($row) {
                $total = (float) ($row->total_commission_amount ?? 0);
                $paid = (float) ($row->paid_amount ?? 0);
                return max(0, $total - $paid) > 0;
            })
            ->pluck('payee_id')
            ->filter()
            ->unique()
            ->count();
    }
}
