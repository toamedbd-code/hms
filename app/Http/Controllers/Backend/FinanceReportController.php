<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\Expense;
use App\Models\BillItem;
use App\Models\ExpenseHead;
use App\Models\Referral;
use App\Models\WebSetting;
use App\Models\Appoinment;
use App\Models\OpdPatient;
use App\Models\IpdPatient;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;


class FinanceReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:finance-report', ['only' => ['reportPage', 'downloadPDF']]);
    }
    public function reportPage(Request $request)
    {
        $reportType = $request->get('report_type', 'daily-transaction');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $reportData = null;
        $tableHeaders = [];
        $dataFields = [];
        $footerTotals = [];

        if ($dateFrom && $dateTo) {
            $dateFromStart = $dateFrom . ' 00:00:00';
            $dateToEnd = $dateTo . ' 23:59:59';

            $reportData = $this->getReportData($reportType, $dateFromStart, $dateToEnd);
            $tableHeaders = $this->getTableHeaders($reportType);
            $dataFields = $this->getDataFields($reportType);
            $footerTotals = $this->getFooterTotals($reportType, $reportData);
        }

        return Inertia::render(
            'Backend/FinanceReport/Index',
            [
                'pageTitle' => 'Finance Report',
                'reportType' => $reportType,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'reportData' => $reportData,
                'tableHeaders' => $tableHeaders,
                'dataFields' => $dataFields,
                'footerTotals' => $footerTotals,
                'datas' => $reportData,
            ]
        );
    }

    private function getTableHeaders($reportType)
    {
        $headers = [
            'daily-transaction' => ['#', 'Date', 'Transactions', 'Total', 'Discount', 'Amount After Discount', 'Cash', 'Non-Cash', 'Commission', 'Paid', 'Due', 'Due Collected'],
            // Make all-transaction use the same format as daily-transaction
            'all-transaction' => ['#', 'Date', 'Transactions', 'Total', 'Discount', 'Amount After Discount', 'Cash', 'Non-Cash', 'Commission', 'Paid', 'Due', 'Due Collected'],
            'pharmacy-transaction' => ['#', 'Date', 'Bill No', 'Mobile', 'Doctor', 'Pay Mode', 'Total', 'Discount', 'Payable', 'Paid', 'Due', 'Due Collected', 'Status'],
            'appointment-transaction' => ['#', 'Date', 'Reference No', 'Mobile', 'Doctor', 'Pay Mode', 'Total', 'Discount', 'Payable', 'Paid', 'Due', 'Due Collected', 'Status'],
            'opd-transaction' => ['#', 'Date', 'Reference No', 'Mobile', 'Doctor', 'Pay Mode', 'Total', 'Discount', 'Payable', 'Paid', 'Due', 'Due Collected', 'Status'],
            'ipd-transaction' => ['#', 'Date', 'Reference No', 'Mobile', 'Doctor', 'Pay Mode', 'Total', 'Discount', 'Payable', 'Paid', 'Due', 'Due Collected', 'Status'],
            'income' => ['#', 'Date', 'Bills', 'Item Name', 'Total Bill', 'Discount', 'Total Income', 'Net Income', 'Due Collected'],
            'expense' => ['#', 'Date', 'Expense Head', 'Transactions', 'Total Expense'],
            'referral' => ['#', 'Date', 'Doctor', 'Bill No', 'Bill Amount', 'Commission'],
            'pending-transaction' => ['#', 'Date', 'Bill No', 'Mobile', 'Doctor', 'Pay Mode', 'Total', 'Discount', 'Payable', 'Paid', 'Due', 'Due Collected', 'Status'],
        ];

        return $headers[$reportType] ?? [];
    }

    private function getDataFields($reportType)
    {
        $fields = [
            'daily-transaction' => [
                ['fieldName' => 'serial', 'class' => 'text-center'],
                ['fieldName' => 'date', 'class' => ''],
                ['fieldName' => 'total_transactions', 'class' => 'text-center'],
                ['fieldName' => 'total_amount', 'class' => 'text-right'],
                ['fieldName' => 'total_discount', 'class' => 'text-right'],
                ['fieldName' => 'amount_after_discount', 'class' => 'text-right'],
                ['fieldName' => 'cash_amount', 'class' => 'text-right'],
                ['fieldName' => 'non_cash_amount', 'class' => 'text-right'],
                ['fieldName' => 'total_commission', 'class' => 'text-right'],
                ['fieldName' => 'total_paid', 'class' => 'text-right'],
                ['fieldName' => 'total_due', 'class' => 'text-right'],
                ['fieldName' => 'total_due_collected', 'class' => 'text-right'],
            ],
            // Reuse daily-transaction fields for all-transaction
            'all-transaction' => [
                ['fieldName' => 'serial', 'class' => 'text-center'],
                ['fieldName' => 'date', 'class' => ''],
                ['fieldName' => 'total_transactions', 'class' => 'text-center'],
                ['fieldName' => 'total_amount', 'class' => 'text-right'],
                ['fieldName' => 'total_discount', 'class' => 'text-right'],
                ['fieldName' => 'amount_after_discount', 'class' => 'text-right'],
                ['fieldName' => 'cash_amount', 'class' => 'text-right'],
                ['fieldName' => 'non_cash_amount', 'class' => 'text-right'],
                ['fieldName' => 'total_commission', 'class' => 'text-right'],
                ['fieldName' => 'total_paid', 'class' => 'text-right'],
                ['fieldName' => 'total_due', 'class' => 'text-right'],
                ['fieldName' => 'total_due_collected', 'class' => 'text-right'],
            ],
            'pharmacy-transaction' => [
                ['fieldName' => 'serial', 'class' => 'text-center'],
                ['fieldName' => 'created_at', 'class' => ''],
                ['fieldName' => 'bill_number', 'class' => ''],
                ['fieldName' => 'patient_mobile', 'class' => ''],
                ['fieldName' => 'doctor_name', 'class' => ''],
                ['fieldName' => 'pay_mode', 'class' => ''],
                ['fieldName' => 'total', 'class' => 'text-right'],
                ['fieldName' => 'discount', 'class' => 'text-right'],
                ['fieldName' => 'payable_amount', 'class' => 'text-right'],
                ['fieldName' => 'paid_amt', 'class' => 'text-right'],
                ['fieldName' => 'due_amount', 'class' => 'text-right'],
                ['fieldName' => 'total_due_collected', 'class' => 'text-right'],
                ['fieldName' => 'payment_status', 'class' => 'text-center'],
            ],
            'appointment-transaction' => [
                ['fieldName' => 'serial', 'class' => 'text-center'],
                ['fieldName' => 'created_at', 'class' => ''],
                ['fieldName' => 'bill_number', 'class' => ''],
                ['fieldName' => 'patient_mobile', 'class' => ''],
                ['fieldName' => 'doctor_name', 'class' => ''],
                ['fieldName' => 'pay_mode', 'class' => ''],
                ['fieldName' => 'total', 'class' => 'text-right'],
                ['fieldName' => 'discount', 'class' => 'text-right'],
                ['fieldName' => 'payable_amount', 'class' => 'text-right'],
                ['fieldName' => 'paid_amt', 'class' => 'text-right'],
                ['fieldName' => 'due_amount', 'class' => 'text-right'],
                ['fieldName' => 'total_due_collected', 'class' => 'text-right'],
                ['fieldName' => 'payment_status', 'class' => 'text-center'],
            ],
            'opd-transaction' => [
                ['fieldName' => 'serial', 'class' => 'text-center'],
                ['fieldName' => 'created_at', 'class' => ''],
                ['fieldName' => 'bill_number', 'class' => ''],
                ['fieldName' => 'patient_mobile', 'class' => ''],
                ['fieldName' => 'doctor_name', 'class' => ''],
                ['fieldName' => 'pay_mode', 'class' => ''],
                ['fieldName' => 'total', 'class' => 'text-right'],
                ['fieldName' => 'discount', 'class' => 'text-right'],
                ['fieldName' => 'payable_amount', 'class' => 'text-right'],
                ['fieldName' => 'paid_amt', 'class' => 'text-right'],
                ['fieldName' => 'due_amount', 'class' => 'text-right'],
                ['fieldName' => 'total_due_collected', 'class' => 'text-right'],
                ['fieldName' => 'payment_status', 'class' => 'text-center'],
            ],
            'ipd-transaction' => [
                ['fieldName' => 'serial', 'class' => 'text-center'],
                ['fieldName' => 'created_at', 'class' => ''],
                ['fieldName' => 'bill_number', 'class' => ''],
                ['fieldName' => 'patient_mobile', 'class' => ''],
                ['fieldName' => 'doctor_name', 'class' => ''],
                ['fieldName' => 'pay_mode', 'class' => ''],
                ['fieldName' => 'total', 'class' => 'text-right'],
                ['fieldName' => 'discount', 'class' => 'text-right'],
                ['fieldName' => 'payable_amount', 'class' => 'text-right'],
                ['fieldName' => 'paid_amt', 'class' => 'text-right'],
                ['fieldName' => 'due_amount', 'class' => 'text-right'],
                ['fieldName' => 'total_due_collected', 'class' => 'text-right'],
                ['fieldName' => 'payment_status', 'class' => 'text-center'],
            ],
            'income' => [
                ['fieldName' => 'serial', 'class' => 'text-center'],
                ['fieldName' => 'date', 'class' => ''],
                ['fieldName' => 'total_bills', 'class' => 'text-center'],
                ['fieldName' => 'item_names', 'class' => ''],
                ['fieldName' => 'total_bill', 'class' => 'text-right'],
                ['fieldName' => 'total_discount', 'class' => 'text-right'],
                ['fieldName' => 'total_income', 'class' => 'text-right'],
                ['fieldName' => 'net_income', 'class' => 'text-right'],
                ['fieldName' => 'total_due_collected', 'class' => 'text-right'],

            ],
            'expense' => [
                ['fieldName' => 'serial', 'class' => 'text-center'],
                ['fieldName' => 'date', 'class' => ''],
                ['fieldName' => 'expense_head_name', 'class' => ''],
                ['fieldName' => 'total_transactions', 'class' => 'text-center'],
                ['fieldName' => 'total_expense', 'class' => 'text-right'],
            ],
            'referral' => [
                ['fieldName' => 'serial', 'class' => 'text-center'],
                ['fieldName' => 'date', 'class' => ''],
                ['fieldName' => 'doctor_name', 'class' => ''],
                ['fieldName' => 'bill_number', 'class' => ''],
                ['fieldName' => 'total_bill_amount', 'class' => 'text-right'],
                ['fieldName' => 'total_commission', 'class' => 'text-right'],
            ],
            'pending-transaction' => [
                ['fieldName' => 'serial', 'class' => 'text-center'],
                ['fieldName' => 'created_at', 'class' => ''],
                ['fieldName' => 'bill_number', 'class' => ''],
                ['fieldName' => 'patient_mobile', 'class' => ''],
                ['fieldName' => 'doctor_name', 'class' => ''],
                ['fieldName' => 'pay_mode', 'class' => ''],
                ['fieldName' => 'total', 'class' => 'text-right'],
                ['fieldName' => 'discount', 'class' => 'text-right'],
                ['fieldName' => 'payable_amount', 'class' => 'text-right'],
                ['fieldName' => 'paid_amt', 'class' => 'text-right'],
                ['fieldName' => 'due_amount', 'class' => 'text-right'],
                ['fieldName' => 'total_due_collected', 'class' => 'text-right'],
                ['fieldName' => 'payment_status', 'class' => 'text-center'],
                
            ],
        ];

        return $fields[$reportType] ?? [];
    }

private function getFooterTotals($reportType, $reportData)
{
    if (!$reportData || empty($reportData)) {
        return [];
    }

    $totals = [];

    switch ($reportType) {

        case 'daily-transaction':
            $totals = [
                'total_transactions' => collect($reportData)->sum('total_transactions'),
                'total_amount' => collect($reportData)->sum('total_amount_raw'),
                'total_discount' => collect($reportData)->sum('total_discount_raw'),
                'amount_after_discount' => collect($reportData)->sum('amount_after_discount_raw'),
                'cash_amount' => collect($reportData)->sum('cash_amount_raw'),
                'non_cash_amount' => collect($reportData)->sum('non_cash_amount_raw'),
                'total_commission' => collect($reportData)->sum('total_commission_raw'),
                'total_paid' => collect($reportData)->sum('total_paid_raw'),
                'total_due' => collect($reportData)->sum('total_due_raw'),

                // ⭐ FINAL FIX
                'total_due_collected' =>
                    collect($reportData)->sum(fn($r) =>
                        $r['total_due_collected_raw'] ?? 0
                    ),
            ];
            break;

        case 'all-transaction':
            // Use same footer shape as daily-transaction so totals align with daily-format table
            $totals = [
                'total_transactions' => collect($reportData)->sum('total_transactions'),
                'total_amount' => collect($reportData)->sum('total_amount_raw'),
                'total_discount' => collect($reportData)->sum('total_discount_raw'),
                'amount_after_discount' => collect($reportData)->sum('amount_after_discount_raw'),
                'cash_amount' => collect($reportData)->sum('cash_amount_raw'),
                'non_cash_amount' => collect($reportData)->sum('non_cash_amount_raw'),
                'total_commission' => collect($reportData)->sum('total_commission_raw'),
                'total_paid' => collect($reportData)->sum('total_paid_raw'),
                'total_due' => collect($reportData)->sum('total_due_raw'),
                'total_due_collected' =>
                    collect($reportData)->sum(fn($r) =>
                        $r['total_due_collected_raw'] ?? 0
                    ),
            ];
            break;

        case 'pharmacy-transaction':
            $totals = [
                'total' => collect($reportData)->sum('total_raw'),
                'discount' => collect($reportData)->sum('discount_raw'),
                'payable_amount' => collect($reportData)->sum('payable_amount_raw'),
                'paid_amt' => collect($reportData)->sum('paid_amt_raw'),
                'due_amount' => collect($reportData)->sum('due_amount_raw'),
                'total_due_collected' =>
                    collect($reportData)->sum(fn($r) =>
                        $r['total_due_collected_raw'] ?? 0
                    ),
            ];
            break;

        case 'appointment-transaction':
            $totals = [
                'total' => collect($reportData)->sum('total_raw'),
                'discount' => collect($reportData)->sum('discount_raw'),
                'payable_amount' => collect($reportData)->sum('payable_amount_raw'),
                'paid_amt' => collect($reportData)->sum('paid_amt_raw'),
                'due_amount' => collect($reportData)->sum('due_amount_raw'),
                'total_due_collected' =>
                    collect($reportData)->sum(fn($r) =>
                        $r['total_due_collected_raw'] ?? 0
                    ),
            ];
            break;

        case 'opd-transaction':
            $totals = [
                'total' => collect($reportData)->sum('total_raw'),
                'discount' => collect($reportData)->sum('discount_raw'),
                'payable_amount' => collect($reportData)->sum('payable_amount_raw'),
                'paid_amt' => collect($reportData)->sum('paid_amt_raw'),
                'due_amount' => collect($reportData)->sum('due_amount_raw'),
                'total_due_collected' =>
                    collect($reportData)->sum(fn($r) =>
                        $r['total_due_collected_raw'] ?? 0
                    ),
            ];
            break;

        case 'ipd-transaction':
            $totals = [
                'total' => collect($reportData)->sum('total_raw'),
                'discount' => collect($reportData)->sum('discount_raw'),
                'payable_amount' => collect($reportData)->sum('payable_amount_raw'),
                'paid_amt' => collect($reportData)->sum('paid_amt_raw'),
                'due_amount' => collect($reportData)->sum('due_amount_raw'),
                'total_due_collected' =>
                    collect($reportData)->sum(fn($r) =>
                        $r['total_due_collected_raw'] ?? 0
                    ),
            ];
            break;

        case 'income':
            $totals = [
                'total_bills' => collect($reportData)->sum('total_bills'),
                'total_bill' => collect($reportData)->sum('total_bill_raw'),
                'total_discount' => collect($reportData)->sum('total_discount_raw'),
                'total_income' => collect($reportData)->sum('total_income_raw'),
                'net_income' => collect($reportData)->sum('net_income_raw'),

                // ⭐ FINAL FIX
                'total_due_collected' =>
                    collect($reportData)->sum(fn($r) =>
                        $r['total_due_collected_raw'] ?? 0
                    ),
            ];
            break;

        case 'expense':
            $totals = [
                'total_transactions' => collect($reportData)->sum('total_transactions'),
                'total_expense' => collect($reportData)->sum('total_expense_raw'),
            ];
            break;

        case 'referral':
            $totals = [
                'total_bill_amount' => collect($reportData)->sum('total_bill_amount_raw'),
                'total_commission' => collect($reportData)->sum('total_commission_raw'),
            ];
            break;

        case 'pending-transaction':
            $totals = [
                'total' => collect($reportData)->sum('total_raw'),
                'discount' => collect($reportData)->sum('discount_raw'),
                'payable_amount' => collect($reportData)->sum('payable_amount_raw'),
                'paid_amt' => collect($reportData)->sum('paid_amt_raw'),
                'due_amount' => collect($reportData)->sum('due_amount_raw'),
                'total_due_collected' =>
                    collect($reportData)->sum(fn($r) =>
                        $r['total_due_collected_raw'] ?? 0
                    ),
            ];
            break;
    }

    return $totals;
}


private function getReportData($reportType, $dateFrom, $dateTo)
{
    switch ($reportType) {

        case 'daily-transaction':
            return $this->getDailyTransactionReport($dateFrom, $dateTo);

        case 'all-transaction':
            // Use the daily transaction aggregation format for "all-transaction"
            return $this->getDailyTransactionReport($dateFrom, $dateTo);

        case 'pharmacy-transaction':
            return $this->getPharmacyTransactionReport($dateFrom, $dateTo);

        case 'appointment-transaction':
            return $this->getAppointmentTransactionReport($dateFrom, $dateTo);

        case 'opd-transaction':
            return $this->getOpdTransactionReport($dateFrom, $dateTo);

        case 'ipd-transaction':
            return $this->getIpdTransactionReport($dateFrom, $dateTo);

        case 'income':
            return $this->getIncomeReport($dateFrom, $dateTo);

        case 'expense':
            return $this->getExpenseReport($dateFrom, $dateTo);

        case 'referral':
            return $this->getReferralReport($dateFrom, $dateTo);

        case 'pending-transaction':
            return $this->getPendingTransactionReport($dateFrom, $dateTo);

        default:
            return null;
    }
}


private function formatCurrency($amount)
{
    return '৳' . number_format($amount ?? 0, 2);
}


private function getStatusBadge($status)
{
    $classes = [
        'Paid' => 'bg-green-100 text-green-800',
        'Partial' => 'bg-yellow-100 text-yellow-800',
        'Pending' => 'bg-red-100 text-red-800'
    ];

    $class = $classes[$status] ?? 'bg-gray-100 text-gray-800';

    return "<span class='px-2 py-1 rounded-full text-xs font-semibold {$class}'>{$status}</span>";
}


private function getDailyTransactionReport($dateFrom, $dateTo)
{
    $rawData = Billing::whereBetween('created_at', [$dateFrom, $dateTo])
        ->where('status', 'Active')
        ->get();

    $groupedData = $rawData->groupBy(function ($billing) {
        return Carbon::parse($billing->created_at)->format('Y-m-d');
    })->map(function ($group, $date) {

        $billingIds = $group->pluck('id');

        $totalCommission = Referral::whereIn('billing_id', $billingIds)
            ->sum('total_commission_amount');

        $totalDiscount = $group->sum(function ($bill) {
            return ($bill->discount_type == 'percentage'
                    ? ($bill->total * $bill->discount) / 100
                    : $bill->discount)
                + $bill->extra_flat_discount;
        });

        $totalAmount = $group->sum('total');
        $amountAfterDiscount = $totalAmount - $totalDiscount;

        $dueCollected = \App\Models\DueCollection::
            whereDate('collected_at', $date)
            ->sum('collected_amount');

        return [
            'date' => $date,
            'total_transactions' => $group->count(),
            'total_amount_raw' => $totalAmount,
            'total_discount_raw' => $totalDiscount,
            'amount_after_discount_raw' => $amountAfterDiscount,
            'cash_amount_raw' => $group->sum(fn($bill) =>
                $bill->pay_mode == 'Cash' ? $bill->payable_amount : 0),
            'non_cash_amount_raw' => $group->sum(fn($bill) =>
                $bill->pay_mode != 'Cash' ? $bill->payable_amount : 0),
            'total_commission_raw' => $totalCommission,
            'total_paid_raw' => $group->sum('paid_amt'),
            'total_due_raw' => $group->sum('due_amount'),
            'total_due_collected_raw' => $dueCollected,
        ];
    });

    $serial = 1;

    return $groupedData->map(function ($item) use (&$serial) {
        $item['serial'] = $serial++;
        $item['date'] = Carbon::parse($item['date'])->format('d/m/Y');
        $item['total_amount'] = $this->formatCurrency($item['total_amount_raw']);
        $item['total_discount'] = $this->formatCurrency($item['total_discount_raw']);
        $item['amount_after_discount'] = $this->formatCurrency($item['amount_after_discount_raw']);
        $item['cash_amount'] = $this->formatCurrency($item['cash_amount_raw']);
        $item['non_cash_amount'] = $this->formatCurrency($item['non_cash_amount_raw']);
        $item['total_commission'] = $this->formatCurrency($item['total_commission_raw']);
        $item['total_paid'] = $this->formatCurrency($item['total_paid_raw']);
        $item['total_due'] = $this->formatCurrency($item['total_due_raw']);
        $item['total_due_collected'] = $this->formatCurrency($item['total_due_collected_raw']);

        return $item;
    })->values()->toArray();
}


   private function getAllTransactionReport($dateFrom, $dateTo)
{
    $data = Billing::whereBetween('created_at', [$dateFrom, $dateTo])
        ->where('status', 'Active')
        ->orderBy('created_at', 'desc')
        ->get();

    $serial = 1;

    return $data->map(function ($item) use (&$serial, $dateFrom, $dateTo) {

        $discount = ($item->discount_type == 'percentage'
            ? ($item->total * $item->discount) / 100
            : $item->discount) + $item->extra_flat_discount;

        // Only include due collections that occurred within the report date range
        $dueCollected = \App\Models\DueCollection::
            where('billing_id', $item->id)
            ->whereBetween('collected_at', [$dateFrom, $dateTo])
            ->sum('collected_amount');

        return [
            'id' => $item->id,
            'serial' => $serial++,
            'bill_number' => $item->bill_number,
            'patient_mobile' => $item->patient_mobile,
            'doctor_name' => $item->doctor_name ?? 'Walk-in',
            'pay_mode' => $item->pay_mode ?? 'N/A',
            'total' => $this->formatCurrency($item->total),
            'total_raw' => $item->total,
            'discount' => $this->formatCurrency($discount),
            'discount_raw' => $discount,
            'payable_amount' => $this->formatCurrency($item->payable_amount),
            'payable_amount_raw' => $item->payable_amount,
            'paid_amt' => $this->formatCurrency($item->paid_amt),
            'paid_amt_raw' => $item->paid_amt,
            'due_amount' => $this->formatCurrency($item->due_amount),
            'due_amount_raw' => $item->due_amount,
            'payment_status' => $this->getStatusBadge($item->payment_status),
            'created_at' => Carbon::parse($item->created_at)->format('d/m/Y'),
            'total_due_collected' => $this->formatCurrency($dueCollected),
            'total_due_collected_raw' => $dueCollected,
        ];
    })->toArray();
}



private function getIncomeReport($dateFrom, $dateTo)
{
    $data = Billing::with('billItems')
        ->whereBetween('created_at', [$dateFrom, $dateTo])
        ->where('status', 'Active')
        ->where('payment_status', 'Paid')
        ->get()
        ->groupBy(fn($billing) =>
            Carbon::parse($billing->created_at)->format('Y-m-d'))
        ->map(function ($group, $date) {

            $totalDiscount = $group->sum(fn($bill) =>
                ($bill->discount_type == 'percentage'
                    ? ($bill->total * $bill->discount) / 100
                    : $bill->discount)
                + $bill->extra_flat_discount
            );

            $dueCollected = \App\Models\DueCollection::
                whereDate('collected_at', $date)
                ->sum('collected_amount');

            return [
                'date' => $date,
                'total_bills' => $group->count(),
                'total_bill_raw' => $group->sum('total'),
                'total_bill' => $this->formatCurrency($group->sum('total')),
                'total_income_raw' => $group->sum('paid_amt'),
                'total_income' => $this->formatCurrency($group->sum('paid_amt')),
                'total_discount_raw' => $totalDiscount,
                'total_discount' => $this->formatCurrency($totalDiscount),
                'total_income_raw' => $group->sum('paid_amt'),
                'net_income_raw' => $group->sum('paid_amt') - $totalDiscount,
                'net_income' => $this->formatCurrency($group->sum('paid_amt') - $totalDiscount),
                'total_due_raw' => $group->sum('due_amount'),
                'total_due' => $this->formatCurrency($group->sum('due_amount')),
                'total_due_collected_raw' => $dueCollected,
                'total_due_collected' => $this->formatCurrency($dueCollected),
                'item_names' => '—',
            ];
        });

    $serial = 1;

    return $data->map(function ($item) use (&$serial) {
        $item['serial'] = $serial++;
        $item['date'] = Carbon::parse($item['date'])->format('d/m/Y');
        return $item;
    })->values()->toArray();
}

private function getPharmacyTransactionReport($dateFrom, $dateTo)
{
    $data = Billing::whereBetween('created_at', [$dateFrom, $dateTo])
        ->where('status', 'Active')
        ->whereHas('billItems', function ($query) {
            $query->where('category', 'Medicine');
        })
        ->orderBy('created_at', 'desc')
        ->get();

    $serial = 1;

    return $data->map(function ($item) use (&$serial, $dateFrom, $dateTo) {
        $discount = ($item->discount_type == 'percentage'
            ? ($item->total * $item->discount) / 100
            : $item->discount) + $item->extra_flat_discount;

        $dueCollected = DueCollection::query()
            ->where('billing_id', $item->id)
            ->whereBetween('collected_at', [$dateFrom, $dateTo])
            ->sum('collected_amount');

        return [
            'id' => $item->id,
            'serial' => $serial++,
            'bill_number' => $item->bill_number,
            'patient_mobile' => $item->patient_mobile,
            'doctor_name' => $item->doctor_name ?? 'Walk-in',
            'pay_mode' => $item->pay_mode ?? 'N/A',
            'total' => $this->formatCurrency($item->total),
            'total_raw' => $item->total,
            'discount' => $this->formatCurrency($discount),
            'discount_raw' => $discount,
            'payable_amount' => $this->formatCurrency($item->payable_amount),
            'payable_amount_raw' => $item->payable_amount,
            'paid_amt' => $this->formatCurrency($item->paid_amt),
            'paid_amt_raw' => $item->paid_amt,
            'due_amount' => $this->formatCurrency($item->due_amount),
            'due_amount_raw' => $item->due_amount,
            'payment_status' => $this->getStatusBadge($item->payment_status),
            'created_at' => Carbon::parse($item->created_at)->format('d/m/Y'),
            'total_due_collected' => $this->formatCurrency($dueCollected),
            'total_due_collected_raw' => $dueCollected,
        ];
    })->toArray();
}

private function getAppointmentTransactionReport($dateFrom, $dateTo)
{
    $data = Appoinment::with(['patient', 'admin'])
        ->whereBetween('appoinment_date', [$dateFrom, $dateTo])
        ->where('status', 'Active')
        ->orderBy('appoinment_date', 'desc')
        ->get();

    $serial = 1;

    return $data->map(function ($item) use (&$serial) {
        $total = (float) ($item->doctor_fee ?? 0);
        $discountPercent = (float) ($item->discount_percentage ?? 0);
        $discount = $discountPercent > 0 ? (($total * $discountPercent) / 100) : 0;
        $payable = max($total - $discount, 0);
        $paid = $payable;
        $due = 0;

        $status = in_array((string) $item->appoinment_status, ['Approved', 'Completed'], true)
            ? 'Paid'
            : 'Pending';

        return [
            'id' => $item->id,
            'serial' => $serial++,
            'bill_number' => $item->transaction_id ?? ('APP-' . $item->id),
            'patient_mobile' => $item->patient?->mobile ?? $item->patient?->phone ?? 'N/A',
            'doctor_name' => $item->admin?->name ?? 'N/A',
            'pay_mode' => $item->payment_mode ?? 'N/A',
            'total' => $this->formatCurrency($total),
            'total_raw' => $total,
            'discount' => $this->formatCurrency($discount),
            'discount_raw' => $discount,
            'payable_amount' => $this->formatCurrency($payable),
            'payable_amount_raw' => $payable,
            'paid_amt' => $this->formatCurrency($paid),
            'paid_amt_raw' => $paid,
            'due_amount' => $this->formatCurrency($due),
            'due_amount_raw' => $due,
            'payment_status' => $this->getStatusBadge($status),
            'created_at' => Carbon::parse($item->appoinment_date)->format('d/m/Y'),
            'total_due_collected' => $this->formatCurrency(0),
            'total_due_collected_raw' => 0,
        ];
    })->toArray();
}

private function getOpdTransactionReport($dateFrom, $dateTo)
{
    $data = OpdPatient::with(['patient', 'consultantDoctor'])
        ->whereBetween('appointment_date', [$dateFrom, $dateTo])
        ->where('status', 'Active')
        ->orderBy('appointment_date', 'desc')
        ->get();

    $serial = 1;

    return $data->map(function ($item) use (&$serial, $dateFrom, $dateTo) {
        $total = (float) ($item->amount ?? 0);
        $discount = 0;
        $payable = $total;
        $paid = (float) ($item->paid_amount ?? 0);
        $due = (float) ($item->balance_amount ?? max($payable - $paid, 0));

        $dueCollected = DueCollection::query()
            ->where('payment_method', 'opd')
            ->whereBetween('collected_at', [$dateFrom, $dateTo])
            ->where(function ($query) use ($item) {
                $query->where('note', 'like', '%opd_patient_id:' . $item->id . '%')
                    ->orWhere('note', 'like', '%opd_patient_id: ' . $item->id . '%');
            })
            ->sum('collected_amount');

        return [
            'id' => $item->id,
            'serial' => $serial++,
            'bill_number' => 'OPD-' . $item->id,
            'patient_mobile' => $item->patient?->mobile ?? $item->patient?->phone ?? 'N/A',
            'doctor_name' => $item->consultantDoctor?->name ?? 'N/A',
            'pay_mode' => $item->payment_mode ?? 'N/A',
            'total' => $this->formatCurrency($total),
            'total_raw' => $total,
            'discount' => $this->formatCurrency($discount),
            'discount_raw' => $discount,
            'payable_amount' => $this->formatCurrency($payable),
            'payable_amount_raw' => $payable,
            'paid_amt' => $this->formatCurrency($paid),
            'paid_amt_raw' => $paid,
            'due_amount' => $this->formatCurrency($due),
            'due_amount_raw' => $due,
            'payment_status' => $this->getStatusBadge((string) ($item->payment_status ?? 'Pending')),
            'created_at' => Carbon::parse($item->appointment_date)->format('d/m/Y'),
            'total_due_collected' => $this->formatCurrency($dueCollected),
            'total_due_collected_raw' => $dueCollected,
        ];
    })->toArray();
}

private function getIpdTransactionReport($dateFrom, $dateTo)
{
    $data = IpdPatient::with(['patient', 'consultantDoctor', 'billing'])
        ->whereBetween('admission_date', [$dateFrom, $dateTo])
        ->whereIn('status', ['Active', 'Inactive'])
        ->orderBy('admission_date', 'desc')
        ->get();

    $serial = 1;

    return $data->map(function ($item) use (&$serial, $dateFrom, $dateTo) {
        $billing = $item->billing;

        $ipdPayments = Payment::query()
            ->where('ipd_patient_id', $item->id)
            ->whereNull('deleted_at')
            ->where('status', 'Active');

        $ipdPaidAmount = (float) $ipdPayments->sum('amount');
        $ipdLastPayMode = $ipdPayments->latest('id')->value('payment_method');

        $total = (float) ($billing?->total ?? 0);
        $discount = (($billing?->discount_type ?? null) === 'percentage'
            ? (($total * (float) ($billing?->discount ?? 0)) / 100)
            : (float) ($billing?->discount ?? 0)) + (float) ($billing?->extra_flat_discount ?? 0);

        // Fallback when billing is not generated yet: show running payment transaction summary.
        if (!$billing) {
            $total = $ipdPaidAmount;
            $discount = 0;
        }

        $payable = (float) ($billing?->payable_amount ?? max($total - $discount, 0));
        $paid = (float) ($billing?->paid_amt ?? $ipdPaidAmount);
        $due = (float) ($billing?->due_amount ?? max($payable - $paid, 0));

        $dueCollected = 0;
        if (!empty($billing?->id)) {
            $dueCollected = DueCollection::query()
                ->where('billing_id', $billing->id)
                ->whereBetween('collected_at', [$dateFrom, $dateTo])
                ->sum('collected_amount');
        }

        return [
            'id' => $item->id,
            'serial' => $serial++,
            'bill_number' => $billing?->bill_number ?? ('IPD-' . $item->id),
            'patient_mobile' => $item->patient?->mobile ?? $item->patient?->phone ?? 'N/A',
            'doctor_name' => $item->consultantDoctor?->name ?? $billing?->doctor_name ?? 'N/A',
            'pay_mode' => $billing?->pay_mode ?? ($ipdLastPayMode ?: 'N/A'),
            'total' => $this->formatCurrency($total),
            'total_raw' => $total,
            'discount' => $this->formatCurrency($discount),
            'discount_raw' => $discount,
            'payable_amount' => $this->formatCurrency($payable),
            'payable_amount_raw' => $payable,
            'paid_amt' => $this->formatCurrency($paid),
            'paid_amt_raw' => $paid,
            'due_amount' => $this->formatCurrency($due),
            'due_amount_raw' => $due,
            'payment_status' => $this->getStatusBadge((string) ($billing?->payment_status ?? ($paid > 0 ? 'Paid' : 'Pending'))),
            'created_at' => Carbon::parse($item->admission_date)->format('d/m/Y'),
            'total_due_collected' => $this->formatCurrency($dueCollected),
            'total_due_collected_raw' => $dueCollected,
        ];
    })->toArray();
}


    private function getPendingTransactionReport($dateFrom, $dateTo)
    {
        $data = Billing::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'Active')
            ->whereIn('payment_status', ['Pending', 'Partial'])
            ->select([
                'id',
                'bill_number',
                'patient_mobile',
                'doctor_name',
                'pay_mode',
                'total as total_raw',
                'discount',
                'discount_type',
                'extra_flat_discount',
                'payable_amount as payable_amount_raw',
                'paid_amt as paid_amt_raw',
                'due_amount as due_amount_raw',
                'payment_status',
                'delivery_date',
                'created_at'
            ])
            ->orderBy('due_amount', 'desc')
            ->get();


        $serial = 1;
        return $data->map(function ($item) use (&$serial, $dateFrom, $dateTo) {

            $calculatedDiscount = 0;

            if ($item->discount_type == 'percentage') {
                $calculatedDiscount = ($item->total_raw * $item->discount) / 100;
            } else {
                $calculatedDiscount = $item->discount;
            }

            $totalDiscount = $calculatedDiscount + $item->extra_flat_discount;

            // Only include due collections within the requested date range
            $dueCollected = \App\Models\DueCollection::
                where('billing_id', $item->id)
                ->whereBetween('collected_at', [$dateFrom, $dateTo])
                ->sum('collected_amount');

            return [
                'id' => $item->id,
                'serial' => $serial++,
                'bill_number' => $item->bill_number,
                'patient_mobile' => $item->patient_mobile,
                'doctor_name' => $item->doctor_name ?? 'Walk-in',
                'pay_mode' => $item->pay_mode ?? 'N/A',
                'total' => $item->total_raw,
                'total_raw' => $item->total_raw,
                'discount' => $totalDiscount,
                'discount_raw' => $totalDiscount,
                'payable_amount' => $item->payable_amount_raw,
                'payable_amount_raw' => $item->payable_amount_raw,
                'paid_amt' => $item->paid_amt_raw,
                'paid_amt_raw' => $item->paid_amt_raw,
                'due_amount' => $item->due_amount_raw,
                'due_amount_raw' => $item->due_amount_raw,
                'payment_status' => $this->getStatusBadge($item->payment_status),
                'delivery_date' => $item->delivery_date,
                'created_at' => Carbon::parse($item->created_at)->format('d/m/Y'),
                'total_due_collected' => $this->formatCurrency($dueCollected),
                'total_due_collected_raw' => $dueCollected,
            ];
        })->toArray();
    }

    private function getExpenseReport($dateFrom, $dateTo)
    {
        $data = Expense::with('expenseHead')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->where('status', 'Active')
            ->get()
            ->groupBy(function ($expense) {
                return date('Y-m-d', strtotime($expense->date)) . '|' . ($expense->expenseHead->name ?? 'N/A');
            })
            ->map(function ($group, $key) {
                $parts = explode('|', $key);
                $date = $parts[0];
                $expenseHeadName = $parts[1] ?? 'N/A';

                $totalExpense = $group->sum('amount');

                return (object) [
                    'date' => Carbon::parse($date)->format('Y-m-d'),
                    'expense_head_name' => $expenseHeadName,
                    'total_transactions' => $group->count(),
                    'total_expense_raw' => $totalExpense,
                ];
            })
            ->sortByDesc('date')
            ->values();

        $serial = 1;
        $data->transform(function ($item) use (&$serial) {
            $item->serial = $serial++;
            $item->date = Carbon::parse($item->date)->format('d/m/Y');
            $item->total_expense = $this->formatCurrency($item->total_expense_raw);
            return $item;
        });

        return $data->toArray();
    }

    private function getReferralReport($dateFrom, $dateTo)
    {
        $data = Referral::with(['billing', 'payee'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->where('status', 'Active')
            ->orderBy('date', 'desc')
            ->get();

        $serial = 1;
        return $data->map(function ($referral) use (&$serial) {
            return [
                'serial' => $serial++,
                'date' => Carbon::parse($referral->date)->format('d/m/Y'),
                'doctor_name' => $referral->payee->name ?? 'Unknown',
                'bill_number' => $referral->billing->bill_number ?? 'N/A',
                'total_bill_amount_raw' => $referral->total_bill_amount,
                'total_bill_amount' => $this->formatCurrency($referral->total_bill_amount),
                'total_commission_raw' => $referral->total_commission_amount,
                'total_commission' => $this->formatCurrency($referral->total_commission_amount),
            ];
        })->toArray();
    }

    public function downloadPDF(Request $request)
    {
        $reportType = $request->get('report_type', 'daily-transaction');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (!$dateFrom || !$dateTo) {
            return response()->json(['error' => 'Date range is required'], 400);
        }

        $dateFromStart = $dateFrom . ' 00:00:00';
        $dateToEnd = $dateTo . ' 23:59:59';

        $reportData = $this->getReportData($reportType, $dateFromStart, $dateToEnd);
        $tableHeaders = $this->getTableHeaders($reportType);
        $dataFields = $this->getDataFields($reportType);
        $footerTotals = $this->getFooterTotals($reportType, $reportData);

        if (empty($reportData)) {
            return response()->json(['error' => 'No data available for download'], 404);
        }

        try {
            $defaultConfig = (new ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_header' => 5,
                'margin_footer' => 5,
                'fontDir' => array_merge($fontDirs, [
                    base_path('storage/fonts'),
                ]),
                'fontdata' => $fontData + [
                    'bangla' => [
                        'R' => 'SolaimanLipi.ttf',
                        'useOTL' => 0xFF,
                    ]
                ],
                'default_font' => 'helvetica',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
            ]);

            $mpdf->SetDisplayMode('fullpage');
            $mpdf->setAutoTopMargin = 'stretch';
            $mpdf->setAutoBottomMargin = 'stretch';

            $html = $this->generatePDFHTML($reportType, $reportData, $tableHeaders, $dataFields, $footerTotals, $dateFrom, $dateTo);

            $mpdf->WriteHTML($html);

            $fileName = "{$reportType}_report_{$dateFrom}_to_{$dateTo}.pdf";

            return $mpdf->Output($fileName, 'I');
        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
        }
    }

    private function generatePDFHTML($reportType, $reportData, $tableHeaders, $dataFields, $footerTotals, $dateFrom, $dateTo)
    {
        $reportTitle = $this->getReportTitle($reportType);
        $currentDateTime = now()->format('d/m/Y H:i');
        $websetting = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();

        $hospitalName = $websetting?->company_name ?? config('app.name', 'Hospital');
        $hospitalAddress = $websetting?->address ?? $websetting?->report_title ?? 'N/A';

        // (No special-case for 'all-transaction' here — use the generic HTML below so
        // 'all-transaction' renders with the same table and calculations as daily-transaction.)

        $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <style>
            body { 
                font-family: helvetica, Arial, sans-serif; 
                font-size: 10pt; 
                color: #333;
                line-height: 1.2;
            }
            .header { 
                text-align: center; 
                margin-bottom: 15px;
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
            .title { 
                font-size: 16pt; 
                font-weight: bold; 
                margin-bottom: 5px;
                text-transform: uppercase;
            }
            .subtitle { 
                font-size: 10pt; 
                margin-bottom: 3px;
                color: #666;
            }
            .summary { 
                margin: 10px 0; 
                padding: 8px;
                background: #f8f9fa;
                border-radius: 4px;
                font-size: 9pt;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 10px 0;
                font-size: 8pt;
            }
            th { 
                background-color: #f8f9fa; 
                border: 1px solid #dee2e6; 
                padding: 6px 4px; 
                text-align: left;
                font-weight: bold;
            }
            td { 
                border: 1px solid #dee2e6; 
                padding: 5px 4px; 
                vertical-align: top;
            }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .footer-totals { 
                background-color: #e1e5eaff !important; 
                color: black; 
                font-weight: bold;
            }
            .page-break { page-break-after: always; }
            .even-row { background-color: #f8f9fa; }
            .odd-row { background-color: white; }
            .summary-row { 
                background-color: #e9ecef; 
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="subtitle"><strong>' . htmlspecialchars($hospitalName) . '</strong></div>
            <div class="subtitle">' . htmlspecialchars($hospitalAddress) . '</div>
            <div class="title">' . htmlspecialchars($reportTitle) . '</div>
            <div class="subtitle">Generated on: ' . $currentDateTime . '</div>
            <div class="subtitle">Date Range: ' . $this->formatDateForDisplay($dateFrom) . ' to ' . $this->formatDateForDisplay($dateTo) . '</div>
        </div>';

        $html .= '
        <div class="summary">
            <strong>Total Records:</strong> ' . count($reportData);

        if (isset($footerTotals['total_transactions'])) {
            $html .= ' | <strong>Total Transactions:</strong> ' . $footerTotals['total_transactions'];
        }

        $html .= '
        </div>';

        $html .= '
        <table>
            <thead>
                <tr>';

        foreach ($tableHeaders as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }

        $html .= '
                </tr>
            </thead>
            <tbody>';

        foreach ($reportData as $index => $row) {
            $rowClass = ($index % 2 === 0) ? 'even-row' : 'odd-row';
            $html .= '<tr class="' . $rowClass . '">';

            foreach ($dataFields as $field) {
                $fieldName = $field['fieldName'];
                $value = $row->$fieldName ?? $row[$fieldName] ?? '';

                // Clean HTML from status badges
                if (is_string($value) && strpos($value, '<') !== false) {
                    $value = strip_tags($value);
                }

                if (is_string($value) && strpos($value, '৳') === 0) {
                    $value = str_replace('৳', '', $value);
                }

                $alignment = 'text-left';
                if (strpos($field['class'] ?? '', 'text-right') !== false) {
                    $alignment = 'text-right';
                } elseif (strpos($field['class'] ?? '', 'text-center') !== false) {
                    $alignment = 'text-center';
                }

                $html .= '<td class="' . $alignment . '">' . htmlspecialchars($value) . '</td>';
            }

            $html .= '</tr>';
        }

        // Always show GRAND TOTAL footer
if (true) {

            // ===== ALWAYS SHOW GRAND TOTAL =====
$html .= '<tr class="footer-totals">';

$footerColumnSpan = $this->getFooterColumnSpan($reportType);
$html .= '<td colspan="' . $footerColumnSpan . '">GRAND TOTAL</td>';

switch ($reportType) {

    case 'daily-transaction':
        $html .= '
        <td class="text-center">' . ($footerTotals['total_transactions'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_discount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['amount_after_discount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['cash_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['non_cash_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_commission'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_paid'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_due'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_due_collected'] ?? 0) . '</td>
        ';
        break;

    case 'all-transaction':
        $html .= '
        <td class="text-center">' . ($footerTotals['total_transactions'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_discount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['amount_after_discount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['cash_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['non_cash_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_commission'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_paid'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_due'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_due_collected'] ?? 0) . '</td>
        ';
        break;

    case 'pharmacy-transaction':
        $html .= '
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['discount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['payable_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['paid_amt'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['due_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_due_collected'] ?? 0) . '</td>
        <td class="text-center">—</td>
        ';
        break;

    case 'appointment-transaction':
    case 'opd-transaction':
    case 'ipd-transaction':
        $html .= '
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['discount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['payable_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['paid_amt'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['due_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_due_collected'] ?? 0) . '</td>
        <td class="text-center">—</td>
        ';
        break;

    case 'income':
        $html .= '
        <td class="text-center">' . ($footerTotals['total_bills'] ?? 0) . '</td>
        <td>—</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_bill'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_discount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_income'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['net_income'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_due_collected'] ?? 0) . '</td>
        ';
        break;

    case 'expense':
        $html .= '
        <td class="text-center">' . ($footerTotals['total_transactions'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_expense'] ?? 0) . '</td>
        ';
        break;

    case 'referral':
        $html .= '
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_bill_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_commission'] ?? 0) . '</td>
        ';
        break;

    case 'pending-transaction':
        $html .= '
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['discount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['payable_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['paid_amt'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['due_amount'] ?? 0) . '</td>
        <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total_due_collected'] ?? 0) . '</td>
        <td class="text-center">—</td>
        ';
        break;
}

$html .= '</tr>';
// ===== END GRAND TOTAL =====

        }

        $html .= '
            </tbody>
        </table>
    </body>
    </html>';

        return $html;
    }

  private function shouldShowFooter($reportType, $footerTotals)
{
    // totals null বা array না হলে show করবে না
    if (!is_array($footerTotals)) {
        return false;
    }

    // array empty হলেও footer show করবে
    // কারণ GRAND TOTAL row always দেখাতে হবে
    return true;
}


private function getReportTitle($reportType)
{
    $titles = [
        'daily-transaction' => 'Daily Transaction Report',
        'all-transaction' => 'All Transaction Report',
        'pharmacy-transaction' => 'Pharmacy Transaction Report',
        'appointment-transaction' => 'Appointment Transaction Report',
        'opd-transaction' => 'OPD Transaction Report',
        'ipd-transaction' => 'IPD Transaction Report',
        'income' => 'Income Report',
        'expense' => 'Expense Report',
        'referral' => 'Referral Report',
        'pending-transaction' => 'Pending Transaction Report',
    ];

    return $titles[$reportType] ?? 'Finance Report';
}


private function formatDateForDisplay($date)
{
    return Carbon::parse($date)->format('d/m/Y');
}


private function formatCurrencyForPDF($amount)
{
    return '৳' . number_format($amount ?? 0, 2);
}


private function getFooterColumnSpan($reportType)
{
    // Column spans are calculated to ensure GRAND TOTAL label aligns correctly
    // with the footer total values, accounting for all table columns

    $spans = [
        'daily-transaction' => 2,
        'all-transaction' => 2,
        'pharmacy-transaction' => 6,
        'appointment-transaction' => 6,
        'opd-transaction' => 6,
        'ipd-transaction' => 6,
        'income' => 2,
        'expense' => 3,
        'referral' => 4,
        'pending-transaction' => 6,
    ];

    return $spans[$reportType] ?? 6;
}
}