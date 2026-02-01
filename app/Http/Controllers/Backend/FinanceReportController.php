<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Expense;
use App\Models\BillItem;
use App\Models\ExpenseHead;
use App\Models\Referral;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'daily-transaction' => ['#', 'Date', 'Transactions', 'Total', 'Discount', 'Amount After Discount', 'Cash', 'Non-Cash', 'Commission', 'Paid', 'Due'],
            'all-transaction' => ['#', 'Date', 'Bill No', 'Mobile', 'Doctor', 'Pay Mode', 'Total', 'Discount', 'Payable', 'Paid', 'Due', 'Status'],
            'income' => ['#', 'Date', 'Bills', 'Item Name', 'Total Bill', 'Discount', 'Total Income', 'Net Income'],
            'expense' => ['#', 'Date', 'Expense Head', 'Transactions', 'Total Expense'],
            'referral' => ['#', 'Date', 'Doctor', 'Bill No', 'Bill Amount', 'Commission'],
            'pending-transaction' => ['#', 'Date', 'Bill No', 'Mobile', 'Doctor', 'Pay Mode', 'Total', 'Discount', 'Payable', 'Paid', 'Due', 'Status'],
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
            ],
            'all-transaction' => [
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
                ];
                break;

            case 'all-transaction':
                $totals = [
                    'total' => collect($reportData)->sum('total_raw'),
                    'discount' => collect($reportData)->sum('discount_raw'),
                    'payable_amount' => collect($reportData)->sum('payable_amount_raw'),
                    'paid_amt' => collect($reportData)->sum('paid_amt_raw'),
                    'due_amount' => collect($reportData)->sum('due_amount_raw'),
                ];
                break;

            case 'income':
                $totals = [
                    'total_bills' => collect($reportData)->sum('total_bills'),
                    'total_bill' => collect($reportData)->sum('total_bill_raw'),
                    'total_discount' => collect($reportData)->sum('total_discount_raw'),
                    'total_income' => collect($reportData)->sum('total_income_raw'),
                    'net_income' => collect($reportData)->sum('net_income_raw'),
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
                return $this->getAllTransactionReport($dateFrom, $dateTo);

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
                $calculatedDiscount = 0;

                if ($bill->discount_type == 'percentage') {
                    $calculatedDiscount = ($bill->total * $bill->discount) / 100;
                } else {
                    $calculatedDiscount = $bill->discount;
                }

                return $calculatedDiscount + $bill->extra_flat_discount;
            });

            $totalAmount = $group->sum('total');
            $amountAfterDiscount = $totalAmount - $totalDiscount;

            return [
                'date' => $date,
                'total_transactions' => $group->count(),
                'total_amount_raw' => $totalAmount,
                'total_discount_raw' => $totalDiscount,
                'amount_after_discount_raw' => $amountAfterDiscount,
                'cash_amount_raw' => $group->sum(function ($bill) {
                    return $bill->pay_mode == 'Cash' ? $bill->payable_amount : 0;
                }),
                'non_cash_amount_raw' => $group->sum(function ($bill) {
                    return $bill->pay_mode != 'Cash' ? $bill->payable_amount : 0;
                }),
                'total_commission_raw' => $totalCommission,
                'total_paid_raw' => $group->sum('paid_amt'),
                'total_due_raw' => $group->sum('due_amount'),
            ];
        })->sortByDesc('date')->values();

        $serial = 1;
        $groupedData->transform(function ($item) use (&$serial) {
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
            return (object)$item;
        });

        return $groupedData->toArray();
    }

    private function getAllTransactionReport($dateFrom, $dateTo)
    {
        $data = Billing::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'Active')
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
                'created_at'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $serial = 1;
        return $data->map(function ($item) use (&$serial) {
            $calculatedDiscount = 0;

            if ($item->discount_type == 'percentage') {
                $calculatedDiscount = ($item->total_raw * $item->discount) / 100;
            } else {
                $calculatedDiscount = $item->discount;
            }

            $totalDiscount = $calculatedDiscount + $item->extra_flat_discount;

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
                'created_at' => Carbon::parse($item->created_at)->format('d/m/Y'),
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
            ->groupBy(function ($billing) {
                return Carbon::parse($billing->created_at)->format('Y-m-d');
            })
            ->map(function ($group, $date) {
                $itemNames = $group->flatMap(function ($billing) {
                    return $billing->billItems->map(function ($billItem) {
                        return $billItem->item_name ?? 'N/A';
                    });
                })->unique()->take(5)->implode(', ');

                if ($group->flatMap->billItems->count() > 5) {
                    $itemNames .= '...';
                }

                $totalDiscount = $group->sum(function ($bill) {
                    $calculatedDiscount = 0;

                    if ($bill->discount_type == 'percentage') {
                        $calculatedDiscount = ($bill->total * $bill->discount) / 100;
                    } else {
                        $calculatedDiscount = $bill->discount;
                    }

                    return $calculatedDiscount + $bill->extra_flat_discount;
                });

                return (object) [
                    'date' => $date,
                    'total_bills' => $group->count(),
                    'item_names' => $itemNames ?: 'N/A',
                    'total_bill_raw' => $group->sum('total'),
                    'total_income_raw' => $group->sum('paid_amt'),
                    'total_discount_raw' => $totalDiscount,
                    'net_income_raw' => $group->sum('paid_amt'),
                ];
            })
            ->sortByDesc('date')
            ->values();

        $serial = 1;
        $data->transform(function ($item) use (&$serial) {
            $item->serial = $serial++;
            $item->date = Carbon::parse($item->date)->format('d/m/Y');
            $item->total_bill = $this->formatCurrency($item->total_bill_raw);
            $item->total_income = $this->formatCurrency($item->total_income_raw);
            $item->total_discount = $this->formatCurrency($item->total_discount_raw);
            $item->net_income = $this->formatCurrency($item->net_income_raw);
            return $item;
        });

        return $data->toArray();
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
        return $data->map(function ($item) use (&$serial) {

            $calculatedDiscount = 0;

            if ($item->discount_type == 'percentage') {
                $calculatedDiscount = ($item->total_raw * $item->discount) / 100;
            } else {
                $calculatedDiscount = $item->discount;
            }

            $totalDiscount = $calculatedDiscount + $item->extra_flat_discount;

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
                'discount_raw' => $item->discount + $item->extra_flat_discount,
                'payable_amount' => $item->payable_amount_raw,
                'payable_amount_raw' => $item->payable_amount_raw,
                'paid_amt' => $item->paid_amt_raw,
                'paid_amt_raw' => $item->paid_amt_raw,
                'due_amount' => $item->due_amount_raw,
                'due_amount_raw' => $item->due_amount_raw,
                'payment_status' => $this->getStatusBadge($item->payment_status),
                'delivery_date' => $item->delivery_date,
                'created_at' => Carbon::parse($item->created_at)->format('d/m/Y'),
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

            return $mpdf->Output($fileName, 'D');
        } catch (\Exception $e) {
            \Log::error('PDF Generation Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
        }
    }

    private function generatePDFHTML($reportType, $reportData, $tableHeaders, $dataFields, $footerTotals, $dateFrom, $dateTo)
    {
        $reportTitle = $this->getReportTitle($reportType);
        $currentDateTime = now()->format('d/m/Y H:i');

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

        if (!empty($footerTotals) && $this->shouldShowFooter($reportType, $footerTotals)) {
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
                ';
                    break;

                case 'all-transaction':
                    $html .= '
                    <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['total'] ?? 0) . '</td>
                    <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['discount'] ?? 0) . '</td>
                    <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['payable_amount'] ?? 0) . '</td>
                    <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['paid_amt'] ?? 0) . '</td>
                    <td class="text-right">' . $this->formatCurrencyForPDF($footerTotals['due_amount'] ?? 0) . '</td>
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
                    <td class="text-center">—</td>
                ';
                    break;

                default:
                    $remainingColumns = count($tableHeaders) - $footerColumnSpan;
                    for ($i = 0; $i < $remainingColumns; $i++) {
                        $html .= '<td></td>';
                    }
                    break;
            }

            $html .= '</tr>';
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
        if (empty($footerTotals)) {
            return false;
        }

        foreach ($footerTotals as $key => $value) {
            if ($value != 0) {
                return true;
            }
        }

        return false;
    }

    private function getReportTitle($reportType)
    {
        $titles = [
            'daily-transaction' => 'Daily Transaction Report',
            'all-transaction' => 'All Transaction Report',
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
        $spans = [
            'daily-transaction' => 2,
            'all-transaction' => 6,
            'income' => 2,
            'expense' => 3,
            'referral' => 4,
            'pending-transaction' => 6,
        ];
        return $spans[$reportType] ?? 1;
    }
}
