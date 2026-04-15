<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\OpdPatient;
use App\Models\IpdPatient;
use App\Models\WebSetting;
use App\Models\DueCollection;
use App\Models\Expense;
use App\Models\Payment;
use App\Services\ReportAccountingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Mpdf\Mpdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    private ReportAccountingService $accountingService;

    public function __construct(ReportAccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
        $this->middleware('auth:admin');
        $this->middleware('permission:report-list', ['only' => ['index', 'generatePdf']]);

    }

    public function index(Request $request)
    {
        $filters = $this->normalizeFilters($request->only(['dateFrom', 'dateTo', 'singleDate', 'module']));
        $reportData = null;
        $hasData = false;

        $requestedGeneration = $request->hasAny(['dateFrom', 'dateTo', 'singleDate', 'module']);

        if ($requestedGeneration || $this->hasFilters($filters)) {
            $reportData = $this->getReportData($filters);
            $hasData = !empty($reportData['data']);
        }

        return Inertia::render('Backend/Report/Index', [
            'pageTitle' => 'Report List',
            'filters' => $filters,
            'hasData' => $hasData,
            'reportData' => $reportData
        ]);
    }

    public function generatePdf(Request $request)
    {
        try {
            $filters = $this->normalizeFilters($request->only(['dateFrom', 'dateTo', 'singleDate', 'module']));

            // Reduce memory pressure for large datasets
            DB::disableQueryLog();
            @ini_set('memory_limit', '1024M');
            @set_time_limit(0);

            $selectedModule = $filters['module'] ?? 'all_module';
            $fallbackBillingRows = [];

            if ($selectedModule === 'opd') {
                $dateConditions = $this->getDateConditions($filters);
                $opdGrouped = $this->getOpdDataByDate($dateConditions);
                $opdRows = $opdGrouped->values()->all();
                $opdTotals = $this->calculateModuleTotals($opdGrouped);

                if (empty($opdRows)) {
                    $fallbackPatients = OpdPatient::query()
                        ->where(function ($q) {
                            $q->whereNull('status')
                                ->orWhere('status', '!=', 'Deleted');
                        })
                        ->orderByDesc('appointment_date')
                        ->orderByDesc('created_at')
                        ->get();

                    if ($fallbackPatients->isNotEmpty()) {
                        $opdGrouped = $fallbackPatients
                            ->groupBy(function ($patient) {
                                return Carbon::parse($patient->appointment_date ?? $patient->created_at)->format('Y-m-d');
                            })
                            ->map(function ($dayPatients, $date) {
                                $totalAmount = $dayPatients->sum('amount');
                                $totalDiscount = $dayPatients->sum('discount');
                                $totalNetAmount = $totalAmount - $totalDiscount;
                                $totalPaidAmount = max(0, $dayPatients->sum('paid_amount'));
                                $dueCollected = 0;
                                $totalDueAmount = max(0, $totalNetAmount - $totalPaidAmount - $dueCollected);

                                return [
                                    'date' => Carbon::parse($date)->format('d-M-Y'),
                                    'qty' => $dayPatients->count(),
                                    'amount' => $totalAmount,
                                    'discount' => $totalDiscount,
                                    'net_amount' => $totalNetAmount,
                                    'paid_amount' => $totalPaidAmount,
                                    'due_amount' => $totalDueAmount,
                                    'due_collection' => $dueCollected,
                                ];
                            });

                        $opdRows = $opdGrouped->values()->all();
                        $opdTotals = $this->calculateModuleTotals($opdGrouped);
                    }
                }

                $expenseGrouped = $this->getExpenseDataByDate($dateConditions);
                $totalExpense = abs((float) $expenseGrouped->sum('amount'));
                $totalDueCollection = $this->getDueCollectionTotalByDate($dateConditions);
                $finalIncome = (($opdTotals['paid_amount'] ?? 0) + $totalDueCollection) - $totalExpense;

                $data = [
                    'title' => $this->getReportTitle($selectedModule),
                    'dateRange' => $this->getDateRangeString(
                        $filters['dateFrom'] ?? null,
                        $filters['dateTo'] ?? null,
                        $filters['singleDate'] ?? null
                    ),
                    'dailyData' => collect(),
                    'totals' => [
                        'net_amount' => $opdTotals['net_amount'] ?? 0,
                        'paid_amount' => $opdTotals['paid_amount'] ?? 0,
                        'due_amount' => $opdTotals['due_amount'] ?? 0,
                        'actual_due' => $opdTotals['actual_due'] ?? 0,
                        'due_collection' => $totalDueCollection,
                        'total_expense' => $totalExpense,
                        'final_income' => $finalIncome,
                    ],
                    'billRows' => collect(),
                    'billTotals' => [],
                    'moduleDetails' => [],
                    'selectedModule' => $selectedModule,
                    'summary' => [],
                    'reportRows' => [],
                    'fallbackBillingRows' => [],
                    'opdRows' => $opdRows,
                    'opdTotals' => $opdTotals,
                    'allModuleTotals' => [],
                ];

                $pdf = $this->generateDailySalesPdf($data);
                $filename = $this->getPdfFileName($selectedModule);

                // Clean any output buffers to avoid corrupting binary PDF stream
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }

                $pdfSize = is_string($pdf) ? strlen($pdf) : 0;
                Log::info('ReportController: generated PDF size (bytes): ' . $pdfSize . ' for ' . $filename);

                    // Save to temp file and return as download to avoid streaming issues
                    $tmpDir = $this->ensureTempDirectory();
                    $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
                    @file_put_contents($tmpPath, $pdf);
                    if (!file_exists($tmpPath) || filesize($tmpPath) === 0) {
                        Log::error('ReportController: failed to write PDF to tmp path: ' . $tmpPath);
                        return back()->with('error', 'Failed to prepare PDF for download.');
                    }

                    $headers = [
                        'Content-Type' => 'application/pdf',
                        'Content-Length' => $pdfSize,
                        'Content-Transfer-Encoding' => 'binary',
                        'Accept-Ranges' => 'bytes',
                        'Content-Description' => 'File Transfer'
                    ];

                    if ($request->query('inline')) {
                        $headers['Content-Disposition'] = 'inline; filename="' . $filename . '"';
                        return response($pdf, 200, $headers);
                    }

                    return response()->download($tmpPath, $filename, $headers)->deleteFileAfterSend(true);
            }

            if ($selectedModule === 'all_module') {
                $salesData = $this->getDailySalesData($filters);
                $dateConditions = $this->getDateConditions($filters);
                $opdModuleRows = $this->getOpdData($dateConditions);
                $ipdModuleRows = $this->getIpdData($dateConditions);
                $pharmacyModuleRows = $this->getMedicineData($dateConditions);
                $billingModuleRows = $this->getBillingData($dateConditions);

                if (empty($opdModuleRows) && empty($ipdModuleRows) && empty($pharmacyModuleRows) && empty($billingModuleRows)) {
                    $opdModuleRows = $this->getOpdData([]);
                    $ipdModuleRows = $this->getIpdData([]);
                    $pharmacyModuleRows = $this->getMedicineData([]);
                    $billingModuleRows = $this->getBillingData([]);
                }

                $moduleRows = array_merge($opdModuleRows, $ipdModuleRows, $pharmacyModuleRows, $billingModuleRows);

                usort($moduleRows, function ($a, $b) {
                    return strtotime((string)($a['date'] ?? '')) - strtotime((string)($b['date'] ?? ''));
                });

                $moduleRowsCollection = collect($moduleRows);

                $opdRowsCollection = $moduleRowsCollection->filter(function ($row) {
                    return strtolower((string)($row['module'] ?? '')) === 'opd';
                });

                $ipdRowsCollection = $moduleRowsCollection->filter(function ($row) {
                    return strtolower((string)($row['module'] ?? '')) === 'ipd';
                });

                $pharmacyRowsCollection = $moduleRowsCollection->filter(function ($row) {
                    $module = strtolower((string)($row['module'] ?? ''));
                    return in_array($module, ['pharmacy', 'medicine'], true);
                });

                $billingRowsCollection = $moduleRowsCollection->filter(function ($row) {
                    return strtolower((string)($row['module'] ?? '')) === 'billing';
                });

                $pharmacyBillGroups = $pharmacyRowsCollection->groupBy(function ($row) {
                    $dateKey = Carbon::parse($row['date'] ?? now())->format('Y-m-d');
                    $billNo = trim((string)($row['bill_no'] ?? ''));

                    if ($billNo === '') {
                        return $dateKey . '|ITEM|' . md5(($row['item_name'] ?? '') . '|' . ($row['quantity'] ?? 0) . '|' . ($row['revenue'] ?? 0));
                    }

                    return $dateKey . '|BILL|' . $billNo;
                });

                $allModuleTotals = [
                    'opd' => [
                        'label' => 'OPD',
                        'records' => (float)$opdRowsCollection->count(),
                        'revenue' => (float)$opdRowsCollection->sum('revenue'),
                    ],
                    'ipd' => [
                        'label' => 'IPD',
                        'records' => (float)$ipdRowsCollection->count(),
                        'revenue' => (float)$ipdRowsCollection->sum('revenue'),
                    ],
                    'pharmacy' => [
                        'label' => 'Pharmacy',
                        'records' => (float)$pharmacyBillGroups->count(),
                        'revenue' => (float)$pharmacyRowsCollection->sum('revenue'),
                    ],
                    'billing' => [
                        'label' => 'Billing',
                        'records' => (float)$billingRowsCollection->count(),
                        'revenue' => (float)$billingRowsCollection->sum('revenue'),
                    ],
                ];

                $data = [
                    'title' => $this->getReportTitle($selectedModule),
                    'dateRange' => $this->getDateRangeString(
                        $filters['dateFrom'] ?? null,
                        $filters['dateTo'] ?? null,
                        $filters['singleDate'] ?? null
                    ),
                    'dailyData' => collect(),
                    'totals' => $salesData['totals'] ?? [],
                    'billRows' => $salesData['billRows'] ?? collect(),
                    'billTotals' => $salesData['billTotals'] ?? [],
                    'moduleDetails' => $salesData['moduleDetails'] ?? [],
                    'selectedModule' => $selectedModule,
                    'summary' => $salesData['summary'] ?? [],
                    'reportRows' => $moduleRows,
                    'fallbackBillingRows' => [],
                    'allModuleTotals' => $allModuleTotals,
                ];

                $pdf = $this->generateDailySalesPdf($data);
                $filename = $this->getPdfFileName($selectedModule);

                // Clean any output buffers to avoid corrupting binary PDF stream
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }

                $pdfSize = is_string($pdf) ? strlen($pdf) : 0;
                Log::info('ReportController (all_module): generated PDF size (bytes): ' . $pdfSize . ' for ' . $filename);

                    // Save to temp file and return as download
                    $tmpDir = $this->ensureTempDirectory();
                    $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
                    @file_put_contents($tmpPath, $pdf);
                    if (!file_exists($tmpPath) || filesize($tmpPath) === 0) {
                        Log::error('ReportController (all_module): failed to write PDF to tmp path: ' . $tmpPath);
                        return back()->with('error', 'Failed to prepare PDF for download.');
                    }

                    $headers = [
                        'Content-Type' => 'application/pdf',
                        'Content-Length' => $pdfSize,
                        'Content-Transfer-Encoding' => 'binary',
                        'Accept-Ranges' => 'bytes',
                        'Content-Description' => 'File Transfer'
                    ];

                    if ($request->query('inline')) {
                        $headers['Content-Disposition'] = 'inline; filename="' . $filename . '"';
                        return response($pdf, 200, $headers);
                    }

                    return response()->download($tmpPath, $filename, $headers)->deleteFileAfterSend(true);
            }

            $moduleReportData = $this->getReportData($filters);
            $reportRows = $moduleReportData['data'] ?? [];

            if ($selectedModule === 'billing' && empty($reportRows)) {
                $reportRows = $this->getBillingData([]);

                if (!empty($reportRows)) {
                    $totalRevenue = array_sum(array_column($reportRows, 'revenue'));
                    $totalRecords = count($reportRows);

                    $moduleReportData['total'] = $totalRecords;
                    $moduleReportData['revenue'] = $totalRevenue;
                    $moduleReportData['average'] = $totalRecords > 0 ? round($totalRevenue / $totalRecords, 2) : 0;
                }

                if (empty($reportRows)) {
                    $directRows = Billing::withTrashed()
                        ->orderByDesc('created_at')
                        ->get(['bill_number', 'invoice_number', 'payable_amount', 'total', 'payment_status', 'created_at']);

                    $fallbackBillingRows = $directRows->map(function ($billing) {
                        return [
                            'date' => optional($billing->created_at)->format('Y-m-d') ?? now()->format('Y-m-d'),
                            'module' => 'billing',
                            'records' => 1,
                            'revenue' => (float) ($billing->payable_amount ?? $billing->total ?? 0),
                            'status' => strtolower((string) ($billing->payment_status ?? 'pending')),
                            'bill_no' => $billing->bill_number ?? $billing->invoice_number ?? 'N/A',
                        ];
                    })->values()->all();

                    $reportRows = $fallbackBillingRows;

                    $totalRevenue = array_sum(array_column($reportRows, 'revenue'));
                    $totalRecords = count($reportRows);
                    $moduleReportData['total'] = $totalRecords;
                    $moduleReportData['revenue'] = $totalRevenue;
                    $moduleReportData['average'] = $totalRecords > 0 ? round($totalRevenue / $totalRecords, 2) : 0;
                }
            } elseif ($selectedModule === 'billing') {
                $fallbackBillingRows = Billing::withTrashed()
                    ->orderByDesc('created_at')
                    ->get(['bill_number', 'invoice_number', 'payable_amount', 'total', 'payment_status', 'created_at'])
                    ->map(function ($billing) {
                        return [
                            'date' => optional($billing->created_at)->format('Y-m-d') ?? now()->format('Y-m-d'),
                            'module' => 'billing',
                            'records' => 1,
                            'revenue' => (float) ($billing->payable_amount ?? $billing->total ?? 0),
                            'status' => strtolower((string) ($billing->payment_status ?? 'pending')),
                            'bill_no' => $billing->bill_number ?? $billing->invoice_number ?? 'N/A',
                        ];
                    })
                    ->values()
                    ->all();
            }

            $data = [
                'title' => $this->getReportTitle($filters['module'] ?? 'all'),
                'dateRange' => $this->getDateRangeString(
                    $filters['dateFrom'] ?? null,
                    $filters['dateTo'] ?? null,
                    $filters['singleDate'] ?? null
                ),
                'dailyData' => collect(),
                'totals' => [
                    'total_records' => $moduleReportData['total'] ?? 0,
                    'total_revenue' => $moduleReportData['revenue'] ?? 0,
                    'average_revenue' => $moduleReportData['average'] ?? 0,
                ],
                'billRows' => collect(),
                'billTotals' => [],
                'moduleDetails' => [],
                'selectedModule' => $selectedModule,
                'summary' => [],
                'reportRows' => $reportRows,
                'fallbackBillingRows' => $fallbackBillingRows,
                'allModuleTotals' => [],
            ];

            $pdf = $this->generateDailySalesPdf($data);
            $filename = $this->getPdfFileName($filters['module'] ?? 'all');

            // Clean any output buffers to avoid corrupting binary PDF stream
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $pdfSize = is_string($pdf) ? strlen($pdf) : 0;
            Log::info('ReportController (module): generated PDF size (bytes): ' . $pdfSize . ' for ' . $filename);

                // Save to temp file and return as download
                $tmpDir = $this->ensureTempDirectory();
                $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
                @file_put_contents($tmpPath, $pdf);
                if (!file_exists($tmpPath) || filesize($tmpPath) === 0) {
                    Log::error('ReportController (module): failed to write PDF to tmp path: ' . $tmpPath);
                    return back()->with('error', 'Failed to prepare PDF for download.');
                }

                $headers = [
                    'Content-Type' => 'application/pdf',
                    'Content-Length' => $pdfSize,
                    'Content-Transfer-Encoding' => 'binary',
                    'Accept-Ranges' => 'bytes',
                    'Content-Description' => 'File Transfer'
                ];

                if ($request->query('inline')) {
                    $headers['Content-Disposition'] = 'inline; filename="' . $filename . '"';
                    return response($pdf, 200, $headers);
                }

                return response()->download($tmpPath, $filename, $headers)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate PDF. Please try again.');
        }
    }

    private function hasFilters($filters)
    {
        $hasDateFilter = !empty($filters['singleDate']) || !empty($filters['dateFrom']) || !empty($filters['dateTo']);
        $hasModuleFilter = !empty($filters['module']) && $filters['module'] !== 'all_module';

        return $hasDateFilter || $hasModuleFilter;
    }

    private function normalizeFilters(array $filters)
    {
        $filters = array_filter($filters, function ($value) {
            return $value !== null;
        });

        $allowedModules = ['all_module', 'billing', 'pharmacy', 'medicine', 'opd', 'ipd'];
        $selectedModule = $filters['module'] ?? 'all_module';
        $filters['module'] = in_array($selectedModule, $allowedModules, true) ? $selectedModule : 'all_module';

        if ($filters['module'] === 'medicine') {
            $filters['module'] = 'pharmacy';
        }

        if (!empty($filters['singleDate'])) {
            $filters['dateFrom'] = '';
            $filters['dateTo'] = '';
        }

        return $filters;
    }

    private function getReportData($filters)
    {
        $data = [];
        $totalRevenue = 0;
        $totalRecords = 0;
        $dateConditions = $this->getDateConditions($filters);

        if (empty($filters['module']) || $filters['module'] == 'all_module') {
            $billingData = $this->getBillingData($dateConditions);
            $medicineData = $this->getMedicineData($dateConditions);
            $opdData = $this->getOpdData($dateConditions);
            $ipdData = $this->getIpdData($dateConditions);

            $data = array_merge($billingData, $medicineData, $opdData, $ipdData);
        } else {
            switch ($filters['module']) {
                case 'billing':
                    $data = $this->getBillingData($dateConditions);
                    break;
                case 'pharmacy':
                case 'medicine':
                    $data = $this->getMedicineData($dateConditions);
                    break;
                case 'opd':
                    $data = $this->getOpdData($dateConditions);
                    break;
                case 'ipd':
                    $data = $this->getIpdData($dateConditions);
                    break;
            }
        }

        usort($data, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        $totalRecords = count($data);
        $totalRevenue = array_sum(array_column($data, 'revenue'));
        $average = $totalRecords > 0 ? $totalRevenue / $totalRecords : 0;

        return [
            'total' => $totalRecords,
            'revenue' => $totalRevenue,
            'average' => round($average, 2),
            'growth' => 0,
            'data' => $data
        ];
    }

    private function getDateConditions($filters)
    {
        $conditions = [];

        if (!empty($filters['singleDate'])) {
            $conditions['single_date'] = Carbon::parse($filters['singleDate']);
        } elseif (!empty($filters['dateFrom']) && !empty($filters['dateTo'])) {
            $conditions['date_from'] = Carbon::parse($filters['dateFrom'])->startOfDay();
            $conditions['date_to'] = Carbon::parse($filters['dateTo'])->endOfDay();
        } elseif (!empty($filters['dateFrom'])) {
            $conditions['date_from'] = Carbon::parse($filters['dateFrom'])->startOfDay();
        } elseif (!empty($filters['dateTo'])) {
            $conditions['date_to'] = Carbon::parse($filters['dateTo'])->endOfDay();
        }

        return $conditions;
    }

    private function applyDateFilter($query, $dateConditions, $dateField = 'created_at')
    {
        if (isset($dateConditions['single_date'])) {
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

    private function getOutstandingDueTotal(array $dateConditions): float
    {
        if (empty($dateConditions)) {
            $dateConditions['single_date'] = Carbon::today();
        }

        $billingQuery = Billing::where('status', 'Active')
            ->where('due_amount', '>', 0);

        $opdQuery = OpdPatient::query()
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'Deleted');
            })
            ->where('balance_amount', '>', 0);

        $this->applyDateFilter($billingQuery, $dateConditions, 'created_at');

        if (isset($dateConditions['single_date'])) {
            $opdQuery->where(function ($q) use ($dateConditions) {
                $targetDate = $dateConditions['single_date']->toDateString();
                $q->whereDate('appointment_date', $targetDate)
                    ->orWhereDate('created_at', $targetDate);
            });
        } elseif (isset($dateConditions['date_from']) && isset($dateConditions['date_to'])) {
            $from = $dateConditions['date_from']->toDateString();
            $to = $dateConditions['date_to']->toDateString();
            $opdQuery->where(function ($q) use ($from, $to) {
                $q->whereBetween('appointment_date', [$from, $to])
                    ->orWhereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
            });
        } elseif (isset($dateConditions['date_from'])) {
            $from = $dateConditions['date_from']->toDateString();
            $opdQuery->where(function ($q) use ($from) {
                $q->whereDate('appointment_date', '>=', $from)
                    ->orWhereDate('created_at', '>=', $from);
            });
        } elseif (isset($dateConditions['date_to'])) {
            $to = $dateConditions['date_to']->toDateString();
            $opdQuery->where(function ($q) use ($to) {
                $q->whereDate('appointment_date', '<=', $to)
                    ->orWhereDate('created_at', '<=', $to);
            });
        }

        $billingDue = (float) $billingQuery->sum('due_amount');
        $opdDue = (float) $opdQuery->sum('balance_amount');

        return $billingDue + $opdDue;
    }

    private function getPathologyData($dateConditions)
    {
        $query = BillItem::where('category', 'Pathology')->where('status', 'Active')->with('billing');
        $this->applyDateFilter($query, $dateConditions);
        $items = $query->get();

        return $items->map(function ($item) {
            return [
                'date' => $item->created_at->format('Y-m-d'),
                'module' => 'pathology',
                'records' => 1,
                'revenue' => (float) $item->net_amount,
                'status' => 'completed',
                'item_name' => $item->item_name,
                'quantity' => $item->quantity
            ];
        })->toArray();
    }

    private function getRadiologyData($dateConditions)
    {
        $query = BillItem::where('category', 'Radiology')->where('status', 'Active')->with('billing');
        $this->applyDateFilter($query, $dateConditions);
        $items = $query->get();

        return $items->map(function ($item) {
            return [
                'date' => $item->created_at->format('Y-m-d'),
                'module' => 'radiology',
                'records' => 1,
                'revenue' => (float) $item->net_amount,
                'status' => 'completed',
                'item_name' => $item->item_name,
                'quantity' => $item->quantity
            ];
        })->toArray();
    }

    private function getMedicineData($dateConditions)
    {
        // Prefer explicit PharmacyBill records when present (these represent
        // actual pharmacy module transactions). Fall back to BillItem rows
        // categorized as 'Medicine' when no PharmacyBill rows are found.
        $pharmacyQuery = \App\Models\PharmacyBill::query()->where('status', 'Active');
        $this->applyDateFilter($pharmacyQuery, $dateConditions, 'date');
        $pharmacyRows = $pharmacyQuery->get();

        if ($pharmacyRows->isNotEmpty()) {
            return $pharmacyRows->map(function ($row) {
                $billNo = trim((string) ($row->bill_no ?? ''));
                if ($billNo === '') {
                    $billNo = 'PHARMACY-' . str_pad((string)$row->id, 6, '0', STR_PAD_LEFT);
                }

                // Prefer explicit amounts on PharmacyBill. If those are zero
                // (some installs store per-item amounts only), fall back to
                // summing `BillItem` entries for this billing's medicine items.
                $revenue = (float) ($row->net_amount ?? $row->total_amount ?? $row->total ?? $row->payable ?? $row->paid_amount ?? 0);
                if (empty($revenue) && !empty($billNo)) {
                    $billing = \App\Models\Billing::where('bill_number', $billNo)
                        ->orWhere('invoice_number', $billNo)
                        ->first(['id']);
                    if ($billing) {
                        $billItems = \App\Models\BillItem::where('billing_id', $billing->id)
                            ->whereRaw('LOWER(category) IN (?,?)', ['pharmacy', 'medicine'])
                            ->get();

                        $revenue = (float) $billItems->sum(function ($it) {
                            if (!empty($it->total_amount)) return (float) $it->total_amount;
                            if (!empty($it->total)) return (float) $it->total;
                            if (!empty($it->amount)) return (float) $it->amount;
                            if (!empty($it->net_amount)) return (float) $it->net_amount;
                            if (isset($it->price) && isset($it->qty)) return (float) $it->price * (float) $it->qty;
                            if (!empty($it->price)) return (float) $it->price;
                            return 0;
                        });
                    }
                }

                return [
                    'date' => optional($row->date ?? $row->created_at)->format('Y-m-d') ?? now()->format('Y-m-d'),
                    'module' => 'pharmacy',
                    'records' => 1,
                    'revenue' => $revenue,
                    'status' => strtolower((string)($row->status ?? 'completed')),
                    'item_name' => $row->patient_name ?? null,
                    'quantity' => 1,
                    'bill_no' => $billNo
                ];
            })->toArray();
        }

        // Fallback: per-item medicine entries from bill_items
        $query = BillItem::where('category', 'Medicine')->where('status', 'Active')->with('billing');
        $this->applyDateFilter($query, $dateConditions);
        $items = $query->get();

        return $items->map(function ($item) {
            $billNo = trim((string) (optional($item->billing)->bill_number ?? ''));
            if ($billNo === '') {
                $billNo = trim((string) (optional($item->billing)->invoice_number ?? ''));
            }
            if ($billNo === '') {
                $billNo = 'PHARMACY-' . str_pad((string)$item->id, 6, '0', STR_PAD_LEFT);
            }

            return [
                'date' => $item->created_at->format('Y-m-d'),
                'module' => 'pharmacy',
                'records' => $item->quantity ?? '',
                'revenue' => (float) $item->net_amount,
                'status' => 'completed',
                'item_name' => $item->item_name,
                'quantity' => $item->quantity,
                'bill_no' => $billNo
            ];
        })->toArray();
    }

    private function getBillingData($dateConditions)
    {
        $billRows = $this->accountingService->getBillRowsByDate($dateConditions);

        if ($billRows->isNotEmpty()) {
            return $billRows->map(function ($row) {
                $status = ((float) ($row['due_amount'] ?? 0)) > 0 ? 'pending' : 'completed';
                return [
                    'bill_no' => $row['bill_no'] ?? 'N/A',
                    'date' => Carbon::parse($row['billing_date'])->format('Y-m-d'),
                    'module' => 'billing',
                    'records' => 1,
                    'total_amount' => (float) ($row['total_amount'] ?? 0),
                    'discount_amount' => (float) ($row['discount_amount'] ?? 0),
                    'extra_discount' => (float) ($row['extra_discount'] ?? 0),
                    'net_amount' => (float) ($row['net_amount'] ?? 0),
                    'revenue' => (float) ($row['net_amount'] ?? 0),
                    'paid_amount' => (float) ($row['paid_amount'] ?? 0),
                    'due_amount' => (float) ($row['due_amount'] ?? 0),
                    'due_collected' => (float) ($row['due_collected'] ?? 0),
                    'status' => $status,
                ];
            })->toArray();
        }

        $query = Billing::query();
        $this->applyBillingDateFilter($query, $dateConditions);
        $billings = $query->get();

        return $billings->map(function ($billing) {
            $totalAmount = (float) ($billing->total ?? $billing->payable_amount ?? 0);
            $discountAmount = (float) ($billing->discount ?? 0);
            $extraDiscount = (float) ($billing->extra_flat_discount ?? 0);
            $netAmount = (float) ($billing->payable_amount ?? ($totalAmount - $discountAmount - $extraDiscount));
            $paidAmount = (float) ($billing->paid_amount ?? 0);
            $dueAmount = (float) ($billing->due_amount ?? ($netAmount - $paidAmount));
            return [
                'bill_no' => $billing->bill_no ?? $billing->bill_number ?? $billing->invoice_number ?? 'N/A',
                'date' => $billing->created_at->format('Y-m-d'),
                'module' => 'billing',
                'records' => 1,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'extra_discount' => $extraDiscount,
                'net_amount' => $netAmount,
                'revenue' => $netAmount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'due_collected' => 0,
                'status' => strtolower((string) ($billing->payment_status ?? 'pending')),
            ];
        })->toArray();
    }

    private function applyBillingDateFilter($query, $dateConditions)
    {
        if (isset($dateConditions['single_date'])) {
            $date = $dateConditions['single_date']->toDateString();
            $query->where(function ($q) use ($date) {
                $q->whereDate('created_at', $date)
                    ->orWhereDate('delivery_date', $date);
            });
        } elseif (isset($dateConditions['date_from']) && isset($dateConditions['date_to'])) {
            $from = $dateConditions['date_from']->toDateString();
            $to = $dateConditions['date_to']->toDateString();
            $query->where(function ($q) use ($from, $to) {
                $q->where(function ($sub) use ($from, $to) {
                    $sub->whereDate('created_at', '>=', $from)
                        ->whereDate('created_at', '<=', $to);
                })->orWhere(function ($sub) use ($from, $to) {
                    $sub->whereDate('delivery_date', '>=', $from)
                        ->whereDate('delivery_date', '<=', $to);
                });
            });
        } elseif (isset($dateConditions['date_from'])) {
            $from = $dateConditions['date_from']->toDateString();
            $query->where(function ($q) use ($from) {
                $q->whereDate('created_at', '>=', $from)
                    ->orWhereDate('delivery_date', '>=', $from);
            });
        } elseif (isset($dateConditions['date_to'])) {
            $to = $dateConditions['date_to']->toDateString();
            $query->where(function ($q) use ($to) {
                $q->whereDate('created_at', '<=', $to)
                    ->orWhereDate('delivery_date', '<=', $to);
            });
        }

        return $query;
    }

    private function getOpdData($dateConditions)
    {
        $query = OpdPatient::query()
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'Deleted');
            })
            ->with(['patient', 'consultantDoctor']);
        $this->applyDateFilter($query, $dateConditions, 'appointment_date');
        $patients = $query->get();

        if ($patients->isEmpty()) {
            $fallbackQuery = OpdPatient::query()
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhere('status', '!=', 'Deleted');
                })
                ->with(['patient', 'consultantDoctor']);

            $this->applyDateFilter($fallbackQuery, $dateConditions, 'created_at');
            $patients = $fallbackQuery->get();
        }

        return $patients->map(function ($patient) {
            $opdReference = trim((string)($patient->reference ?? ''));
            $isLikelyBillNo = preg_match('/\d/', $opdReference) === 1;
            $resolvedOpdBillNo = ($opdReference !== '' && $isLikelyBillNo)
                ? $opdReference
                : ('OPD-' . str_pad((string)$patient->id, 6, '0', STR_PAD_LEFT));

            return [
                'date' => Carbon::parse($patient->appointment_date ?? $patient->created_at)->format('Y-m-d'),
                                'module' => 'opd',
                'records' => 1,
                'revenue' => (float) $patient->amount,
                'status' => strtolower((string)($patient->payment_status ?? 'pending')),

                'patient_name' => optional($patient->patient)->name ?? 'N/A',
                'doctor_name' => optional($patient->consultantDoctor)->name ?? 'N/A',
                'case_type' => $patient->case,
                'bill_no' => $resolvedOpdBillNo
            ];
        })->toArray();
    }

    private function getIpdData($dateConditions)
    {
        $query = IpdPatient::where('status', 'Active')->with(['patient', 'consultantDoctor', 'bed']);
        $this->applyDateFilter($query, $dateConditions, 'admission_date');
        $patients = $query->get();

        if ($patients->isEmpty()) {
            return [];
        }

        return $patients->map(function ($patient) {
            $revenue = (float) ($patient->credit_limit ?? 0);
            $ipdReference = trim((string)($patient->reference ?? ''));
            $isLikelyBillNo = preg_match('/\d/', $ipdReference) === 1;
            $resolvedIpdBillNo = ($ipdReference !== '' && $isLikelyBillNo)
                ? $ipdReference
                : ('IPD-' . str_pad((string)$patient->id, 6, '0', STR_PAD_LEFT));

            return [
                'date' => $patient->admission_date ? Carbon::parse($patient->admission_date)->format('Y-m-d') : 'N/A',
                'module' => 'ipd',
                'records' => 1,
                'revenue' => $revenue,
                'status' => strtolower((string)($patient->payment_status ?? $patient->status ?? 'pending')),
                'patient_name' => optional($patient->patient)->name ?? 'N/A',
                'doctor_name' => optional($patient->consultantDoctor)->name ?? 'N/A',
                'bed_number' => optional($patient->bed)->bed_number ?? 'N/A',
                'case_type' => $patient->case ?? 'N/A',
                'bill_no' => $resolvedIpdBillNo
            ];
        })->toArray();
    }

    private function getExpenseData($dateConditions)
    {
        $query = Expense::where('status', 'Active')->with('expenseHead');
        $this->applyDateFilter($query, $dateConditions, 'date');
        $expenses = $query->get();

        return $expenses->map(function ($expense) {
            return [
                'date' => Carbon::parse($expense->date)->format('Y-m-d'),
                'module' => 'expense',
                'records' => 1,
                'revenue' => -1 * (float) $expense->amount,  // Show as negative expense
                                'status' => 'expense',
                'expense_head' => optional($expense->expenseHead)->name ?? 'N/A',
                'description' => $expense->description ?? 'N/A',
                'amount' => (float) $expense->amount
            ];
        })->toArray();
    }

    private function getDailySalesData($filters)
    {
        $dateConditions = $this->getDateConditions($filters);
        $billRows = $this->accountingService->getBillRowsByDate($dateConditions);
        $billTotals = $this->accountingService->calculateBillTotals($billRows);

        $totalNetAmount = $billTotals['net_amount'] ?? 0;
        $totalPaidAmount = $billTotals['paid_amount'] ?? 0;
        $totalDueAmount = $this->getOutstandingDueTotal($dateConditions);
        $billDueCollection = (float) ($billTotals['due_collected'] ?? 0);
        $directDueCollection = $this->getDueCollectionTotalByDate($dateConditions);

        // Use the direct due-collection total (entries in DueCollection within
        // the selected date range) as the canonical due-collection figure for
        // the report to avoid inflated numbers caused by taking the max of
        // two overlapping sources.
        $totalDueCollection = $directDueCollection;

        $incomeTotals = $this->accountingService->calculateFinalIncomeTotals($billRows, $dateConditions);
        $totalExpense = $incomeTotals['total_expense'] ?? 0;
        $finalIncome = ($totalPaidAmount + $totalDueCollection) - $totalExpense;
        $actualDue = $totalDueAmount;

        $totals = [
            'net_amount' => $totalNetAmount,
            'paid_amount' => $totalPaidAmount,
            'due_amount' => max(0, $totalDueAmount),
            'due_collection' => $totalDueCollection,
            'total_expense' => $totalExpense,
            'final_income' => $finalIncome,
            'actual_due' => $actualDue,
            'total_amount' => $billTotals['total_amount'] ?? 0,
            'discount_amount' => $billTotals['discount_amount'] ?? 0,
            'extra_discount' => $billTotals['extra_discount'] ?? 0
        ];

        return [
            'dailyData' => collect(),
            'totals' => $totals,
            'moduleDetails' => [],
            'summary' => [],
            'billRows' => $billRows,
            'billTotals' => $billTotals
        ];
    }

    private function getDueCollectionTotalByDate(array $dateConditions): float
    {
        if (empty($dateConditions)) {
            $dateConditions['single_date'] = Carbon::today();
        }

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
                return strtolower((string)($row->payment_method ?? '')) === 'opd' && empty($row->billing_id);
            })
            ->map(function ($row) {
                $matches = [];
                preg_match('/opd_patient_id:\s*(\d+)/i', (string)($row->note ?? ''), $matches);
                return isset($matches[1]) ? (int)$matches[1] : null;
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
                return $activeBillingMap->has((int)$billingId)
                    ? (float)($row->collected_amount ?? 0)
                    : 0;
            }

            if (strtolower((string)($row->payment_method ?? '')) === 'opd') {
                $matches = [];
                preg_match('/opd_patient_id:\s*(\d+)/i', (string)($row->note ?? ''), $matches);
                $opdId = isset($matches[1]) ? (int)$matches[1] : null;

                if ($opdId && !$activeOpdMap->has($opdId)) {
                    return 0;
                }
            }

            return (float)($row->collected_amount ?? 0);
        });
    }

    private function getOpdDataByDate($dateConditions)
    {
        $query = OpdPatient::query()->where(function ($q) {
            $q->whereNull('status')
                ->orWhere('status', '!=', 'Deleted');
        });
        $this->applyDateFilter($query, $dateConditions, 'appointment_date');
        $patients = $query->get();

        if ($patients->isEmpty()) {
            $fallbackQuery = OpdPatient::query()->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'Deleted');
            });
            $this->applyDateFilter($fallbackQuery, $dateConditions, 'created_at');
            $patients = $fallbackQuery->get();
        }

        return $patients->groupBy(function ($patient) {
            return Carbon::parse($patient->appointment_date ?? $patient->created_at)->format('Y-m-d');
        })->map(function ($dayPatients, $date) {
            $totalAmount = $dayPatients->sum('amount');
            $totalDiscount = $dayPatients->sum('discount');
            $totalNetAmount = $totalAmount - $totalDiscount;
            $totalPaidAmount = max(0, $dayPatients->sum('paid_amount'));
            
            // OPD does not use billing-linked due collections here
            $dueCollected = 0;

            // Outstanding due = Net amount - Paid amount - Due Collected
            $totalDueAmount = max(0, $totalNetAmount - $totalPaidAmount - $dueCollected);

            return [
                'date' => Carbon::parse($date)->format('d-M-Y'),
                'qty' => $dayPatients->count(),
                'amount' => $totalAmount,
                'discount' => $totalDiscount,
                'net_amount' => $totalNetAmount,
                'paid_amount' => $totalPaidAmount,
                'due_amount' => $totalDueAmount,
                'due_collection' => $dueCollected
            ];
        });
    }

    private function getIpdDataByDate($dateConditions)
    {
        $query = IpdPatient::where('status', 'Active');
        $this->applyDateFilter($query, $dateConditions, 'admission_date');
        $patients = $query->get();

        return $patients->groupBy(function ($patient) {
            return Carbon::parse($patient->admission_date)->format('Y-m-d');
        })->map(function ($dayPatients, $date) {
            $revenue = max(0, $dayPatients->sum('credit_limit') ?? 0);
            
            // IPD does not use billing-linked due collections here
            $dueCollected = 0;

            // Outstanding due = Revenue - Due Collected
            $outstandingDue = max(0, $revenue - $dueCollected);

            return [
                'date' => Carbon::parse($date)->format('d-M-Y'),
                'qty' => $dayPatients->count(),
                'amount' => $revenue,
                'discount' => 0,
                'net_amount' => $revenue,
                'paid_amount' => 0,
                'due_amount' => $outstandingDue,
                'due_collection' => $dueCollected
            ];
        });
    }

    private function getExpenseDataByDate($dateConditions)
    {
        $query = Expense::where('status', 'Active')->with('expenseHead');
        $this->applyDateFilter($query, $dateConditions, 'date');
        $expenses = $query->get();

        return $expenses->groupBy(function ($expense) {
            return $expense->date;
        })->map(function ($dayExpenses, $date) {
            $totalExpense = $dayExpenses->sum('amount');

            return [
                'date' => Carbon::parse($date)->format('d-M-Y'),
                'qty' => $dayExpenses->count(),
                'amount' => -1 * $totalExpense,  // Show as negative expense
                'discount' => 0,
                'net_amount' => -1 * $totalExpense,
                'paid_amount' => 0,
                'due_amount' => 0,
                'due_collection' => 0
            ];
        });
    }

    private function calculateModuleTotals($moduleData)
    {
        return [
            'qty' => $moduleData->sum('qty'),
            'amount' => $moduleData->sum('amount'),
            'discount' => $moduleData->sum('discount'),
            'net_amount' => $moduleData->sum('net_amount'),
            'paid_amount' => $moduleData->sum('paid_amount'),
            'due_amount' => $moduleData->sum('due_amount'),
            'due_collection' => $moduleData->sum('due_collection'),
            'actual_due' => $moduleData->sum('due_amount')
        ];
    }

    private function calculateExpenseTotals($moduleData)
    {
        return [
            'qty' => $moduleData->sum('qty'),
            'amount' => $moduleData->sum('amount'),  // Will be negative
            'discount' => 0,
            'net_amount' => $moduleData->sum('net_amount'),  // Will be negative
            'paid_amount' => 0,
            'due_amount' => 0,
            'due_collection' => 0,
            'actual_due' => 0
        ];
    }

    private function createSummary($groupedData, $totals)
    {
        $totalDays = $groupedData->count();
        $avgDailyRevenue = $totalDays > 0 ? $totals['net_amount'] / $totalDays : 0;

        return [
            'total_days' => $totalDays,
            'avg_daily_revenue' => round($avgDailyRevenue, 2),
            'highest_day' => $groupedData->sortByDesc('net_amount')->first(),
            'lowest_day' => $groupedData->sortBy('net_amount')->first(),
            'collection_rate' => $totals['net_amount'] > 0 ? round(($totals['paid_amount'] / $totals['net_amount']) * 100, 2) : 0
        ];
    }

    private function getReportTitle($module)
    {
        switch ($module) {
            case 'billing':
                return 'Billing Report';
            case 'pharmacy':
            case 'medicine':
                return 'Pharmacy Sales Report';
            case 'opd':
                return 'OPD Revenue Report';
            case 'ipd':
                return 'IPD Revenue Report';
            default:
                return 'Comprehensive Sales & Expense Report - All Modules';
        }
    }

    private function getPdfFileName($module)
    {
        $timestamp = now()->format('YmdHis');

        switch ($module) {
            case 'billing':
                return "Billing_Report_{$timestamp}.pdf";
            case 'pharmacy':
            case 'medicine':
                return "Pharmacy_Report_{$timestamp}.pdf";
            case 'opd':
                return "OPD_Report_{$timestamp}.pdf";
            case 'ipd':
                return "IPD_Report_{$timestamp}.pdf";
            default:
                return "All_Modules_Report_{$timestamp}.pdf";
        }
    }

    private function getDateRangeString($dateFrom, $dateTo, $singleDate)
    {
        if ($singleDate) {
            return Carbon::parse($singleDate)->format('d-M-Y');
        } elseif ($dateFrom && $dateTo) {
            return Carbon::parse($dateFrom)->format('d-M-Y') . ' To ' . Carbon::parse($dateTo)->format('d-M-Y');
        } elseif ($dateFrom) {
            return 'From ' . Carbon::parse($dateFrom)->format('d-M-Y');
        } elseif ($dateTo) {
            return 'Until ' . Carbon::parse($dateTo)->format('d-M-Y');
        }
        return Carbon::now()->format('d-M-Y');
    }

    private function generateDailySalesPdf($data)
    {
        $websetting = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_header' => 5,
            'margin_footer' => 5,
            'default_font' => 'dejavusanscondensed',
            'default_font_size' => 9,
            'orientation' => 'P',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
            'showWatermarkText' => false,
            'tempDir' => $this->ensureTempDirectory(),
        ]);

        $mpdf->SetTitle($data['title']);
        $mpdf->SetAuthor(config('app.name'));
        $mpdf->SetCreator(config('app.name'));
        $mpdf->SetSubject('Sales Report');
        $mpdf->SetKeywords('report, sales, revenue');

        $mpdf->WriteHTML('<meta name="viewport" content="width=device-width, initial-scale=1.0">');

        $html = view('reports.general_report', [
            'title' => $data['title'],
            'dateRange' => $data['dateRange'],
            'dailyData' => $data['dailyData'],
            'totals' => $data['totals'],
            'billRows' => $data['billRows'] ?? collect(),
            'billTotals' => $data['billTotals'] ?? [],
            'moduleDetails' => $data['moduleDetails'] ?? [],
            'selectedModule' => $data['selectedModule'],
            'summary' => $data['summary'] ?? [],
            'reportRows' => $data['reportRows'] ?? [],
            'fallbackBillingRows' => $data['fallbackBillingRows'] ?? [],
            'opdRows' => $data['opdRows'] ?? [],
            'opdTotals' => $data['opdTotals'] ?? [],
            'allModuleTotals' => $data['allModuleTotals'] ?? [],
            'websetting' => $websetting,
        ])->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function ensureTempDirectory()
    {
        $tempDir = storage_path('app/tmp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        return $tempDir;
    }

    // Temporary debug helper - not for production. Returns PDF size and a sample of bytes.
    public function debugPdf(Request $request)
    {
        try {
            $filters = $this->normalizeFilters($request->only(['dateFrom', 'dateTo', 'singleDate', 'module']));
            $selectedModule = $filters['module'] ?? 'all_module';

            // Build minimal data similar to generatePdf for the selected module
            $data = [
                'title' => $this->getReportTitle($selectedModule),
                'dateRange' => $this->getDateRangeString($filters['dateFrom'] ?? null, $filters['dateTo'] ?? null, $filters['singleDate'] ?? null),
                'dailyData' => collect(),
                'totals' => [],
                'billRows' => collect(),
                'billTotals' => [],
                'moduleDetails' => [],
                'selectedModule' => $selectedModule,
                'summary' => [],
                'reportRows' => [],
                'fallbackBillingRows' => [],
                'allModuleTotals' => []
            ];

            $pdf = $this->generateDailySalesPdf($data);

            $size = is_string($pdf) ? strlen($pdf) : 0;
            $sample = '';
            if ($size > 0) {
                $sample = base64_encode(substr($pdf, 0, min(512, $size)));
            }

            return response()->json([
                'ok' => true,
                'filename' => $this->getPdfFileName($selectedModule),
                'size_bytes' => $size,
                'sample_base64_first_512_bytes' => $sample
            ]);
        } catch (\Exception $e) {
            Log::error('debugPdf error: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
