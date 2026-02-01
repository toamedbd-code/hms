<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\OpdPatient;
use App\Models\IpdPatient;
use App\Models\WebSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Mpdf\Mpdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:report-list', ['only' => ['index', 'generatePdf']]);

    }

    public function index(Request $request)
    {
        $filters = $request->only(['dateFrom', 'dateTo', 'singleDate', 'module']);
        $reportData = null;
        $hasData = false;

        if ($this->hasFilters($filters)) {
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
            $filters = $request->only(['dateFrom', 'dateTo', 'singleDate', 'module']);

            $reportData = $this->getDailySalesData($filters);

            $data = [
                'title' => $this->getReportTitle($filters['module'] ?? 'all'),
                'dateRange' => $this->getDateRangeString(
                    $filters['dateFrom'] ?? null,
                    $filters['dateTo'] ?? null,
                    $filters['singleDate'] ?? null
                ),
                'dailyData' => $reportData['dailyData'],
                'totals' => $reportData['totals'],
                'moduleDetails' => $reportData['moduleDetails'] ?? [],
                'selectedModule' => $filters['module'] ?? 'all',
                'summary' => $reportData['summary'] ?? []
            ];

            $pdf = $this->generateDailySalesPdf($data);
            $filename = $this->getPdfFileName($filters['module'] ?? 'all');

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf;
            }, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
        } catch (\Exception $e) {
            \Log::error('PDF Generation Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate PDF. Please try again.');
        }
    }

    private function hasFilters($filters)
    {
        return !empty(array_filter($filters));
    }

    private function getReportData($filters)
    {
        $data = [];
        $totalRevenue = 0;
        $totalRecords = 0;
        $dateConditions = $this->getDateConditions($filters);

        if (empty($filters['module']) || $filters['module'] == 'all_module') {
            // Get data from all modules
            $pathologyData = $this->getPathologyData($dateConditions);
            $radiologyData = $this->getRadiologyData($dateConditions);
            $medicineData = $this->getMedicineData($dateConditions);
            $opdData = $this->getOpdData($dateConditions);
            $ipdData = $this->getIpdData($dateConditions);

            $data = array_merge($pathologyData, $radiologyData, $medicineData, $opdData, $ipdData);
        } else {
            switch ($filters['module']) {
                case 'pathology':
                    $data = $this->getPathologyData($dateConditions);
                    break;
                case 'radiology':
                    $data = $this->getRadiologyData($dateConditions);
                    break;
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
        $query = BillItem::where('category', 'Medicine')->where('status', 'Active')->with('billing');
        $this->applyDateFilter($query, $dateConditions);
        $items = $query->get();

        return $items->map(function ($item) {
            return [
                'date' => $item->created_at->format('Y-m-d'),
                'module' => 'medicine',
                'records' => $item->quantity ?? '',
                'revenue' => (float) $item->net_amount,
                'status' => 'completed',
                'item_name' => $item->item_name,
                'quantity' => $item->quantity
            ];
        })->toArray();
    }

    private function getOpdData($dateConditions)
    {
        $query = OpdPatient::where('status', 'Active')->with(['patient', 'consultantDoctor']);
        $this->applyDateFilter($query, $dateConditions, 'appointment_date');
        $patients = $query->get();

        return $patients->map(function ($patient) {
            return [
                'date' => Carbon::parse($patient->appointment_date)->format('Y-m-d'),
                'module' => 'opd',
                'records' => 1,
                'revenue' => (float) $patient->amount,
                'status' => strtolower($patient->payment_status ?? 'pending'),
                'patient_name' => $patient->patient->name ?? 'N/A',
                'doctor_name' => $patient->consultantDoctor->name ?? 'N/A',
                'case_type' => $patient->case
            ];
        })->toArray();
    }

    private function getIpdData($dateConditions)
    {
        $query = IpdPatient::where('status', 'Active')->with(['patient', 'consultantDoctor', 'bed']);
        $this->applyDateFilter($query, $dateConditions, 'admission_date');
        $patients = $query->get();

        return $patients->map(function ($patient) {
            $revenue = (float) ($patient->credit_limit ?? 0);
            return [
                'date' => Carbon::parse($patient->admission_date)->format('Y-m-d'),
                'module' => 'ipd',
                'records' => 1,
                'revenue' => $revenue,
                'status' => 'active',
                'patient_name' => $patient->patient->name ?? 'N/A',
                'doctor_name' => $patient->consultantDoctor->name ?? 'N/A',
                'bed_number' => $patient->bed->bed_number ?? 'N/A',
                'case_type' => $patient->case
            ];
        })->toArray();
    }

    private function getDailySalesData($filters)
    {
        $dateConditions = $this->getDateConditions($filters);
        $moduleDetails = [];
        $summary = [];

        $pathologyData = collect();
        $radiologyData = collect();
        $medicineData = collect();
        $opdData = collect();
        $ipdData = collect();

        if (empty($filters['module']) || $filters['module'] === 'all_module') {
            $pathologyData = $this->getBillItemsDataByDate('Pathology', $dateConditions);
            $radiologyData = $this->getBillItemsDataByDate('Radiology', $dateConditions);
            $medicineData = $this->getBillItemsDataByDate('Medicine', $dateConditions);
            $opdData = $this->getOpdDataByDate($dateConditions);
            $ipdData = $this->getIpdDataByDate($dateConditions);

            $moduleDetails = [
                'pathology' => $this->calculateModuleTotals($pathologyData),
                'radiology' => $this->calculateModuleTotals($radiologyData),
                'medicine' => $this->calculateModuleTotals($medicineData),
                'opd' => $this->calculateModuleTotals($opdData),
                'ipd' => $this->calculateModuleTotals($ipdData)
            ];
        } else {
            // Get specific module data
            switch ($filters['module']) {
                case 'pathology':
                    $pathologyData = $this->getBillItemsDataByDate('Pathology', $dateConditions);
                    break;
                case 'radiology':
                    $radiologyData = $this->getBillItemsDataByDate('Radiology', $dateConditions);
                    break;
                case 'medicine':
                    $medicineData = $this->getBillItemsDataByDate('Medicine', $dateConditions);
                    break;
                case 'opd':
                    $opdData = $this->getOpdDataByDate($dateConditions);
                    break;
                case 'ipd':
                    $ipdData = $this->getIpdDataByDate($dateConditions);
                    break;
            }
        }

        // Combine data based on selected module(s)
        if (empty($filters['module']) || $filters['module'] === 'all_module') {
            $combinedData = $pathologyData->merge($radiologyData)
                ->merge($medicineData)
                ->merge($opdData)
                ->merge($ipdData);
        } else {
            $combinedData = ${$filters['module'] . 'Data'};
        }

        // Group by date and calculate totals
        $groupedData = $combinedData->groupBy('date')->map(function ($dateGroup) {
            return [
                'date' => $dateGroup->first()['date'],
                'qty' => $dateGroup->sum('qty'),
                'amount' => $dateGroup->sum('amount'),
                'discount' => $dateGroup->sum('discount'),
                'net_amount' => $dateGroup->sum('net_amount'),
                'paid_amount' => $dateGroup->sum('paid_amount'),
                'due_amount' => $dateGroup->sum('due_amount'),
                'due_collection' => $dateGroup->sum('due_collection')
            ];
        })->sortBy('date');

        // Calculate totals
        $totals = [
            'qty' => $groupedData->sum('qty'),
            'amount' => $groupedData->sum('amount'),
            'discount' => $groupedData->sum('discount'),
            'net_amount' => $groupedData->sum('net_amount'),
            'paid_amount' => $groupedData->sum('paid_amount'),
            'due_amount' => $groupedData->sum('due_amount'),
            'due_collection' => $groupedData->sum('due_collection')
        ];

        // Create summary
        $summary = $this->createSummary($groupedData, $totals);

        return [
            'dailyData' => $groupedData,
            'totals' => $totals,
            'moduleDetails' => $moduleDetails,
            'summary' => $summary
        ];
    }

    private function getBillItemsDataByDate($category, $dateConditions)
    {
        // Create base query
        $query = BillItem::where('category', $category)
            ->where('status', 'Active')
            ->with(['billing' => function ($q) use ($dateConditions) {
                $this->applyDateFilter($q, $dateConditions, 'created_at');
            }]);

        // Apply date filter to the main BillItem query
        $this->applyDateFilter($query, $dateConditions);

        $items = $query->get();

        return $items->groupBy(function ($item) {
            return $item->created_at->format('Y-m-d');
        })->map(function ($dayItems, $date) {
            // Calculate totals for the day
            $totalAmount = $dayItems->sum('total_amount');
            $totalDiscount = $dayItems->sum('discount');
            $totalNetAmount = $dayItems->sum('net_amount');

            // Calculate payment distributions
            $paidAmount = $dayItems->sum(function ($item) {
                if ($item->billing && $item->billing->payable_amount > 0) {
                    return ($item->billing->paid_amt / $item->billing->payable_amount) * $item->net_amount;
                }
                return 0;
            });

            $dueAmount = $dayItems->sum(function ($item) {
                if ($item->billing && $item->billing->payable_amount > 0) {
                    return ($item->billing->due_amount / $item->billing->payable_amount) * $item->net_amount;
                }
                return 0;
            });

            return [
                'date' => Carbon::parse($date)->format('d-M-Y'),
                'qty' => $dayItems->sum('quantity'),
                'amount' => $totalAmount,
                'discount' => $totalDiscount,
                'net_amount' => $totalNetAmount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'due_collection' => 0 // Adjust based on your business logic
            ];
        });
    }

    private function getOpdDataByDate($dateConditions)
    {
        $query = OpdPatient::where('status', 'Active');
        $this->applyDateFilter($query, $dateConditions, 'appointment_date');
        $patients = $query->get();

        return $patients->groupBy(function ($patient) {
            return Carbon::parse($patient->appointment_date)->format('Y-m-d');
        })->map(function ($dayPatients, $date) {
            $totalAmount = $dayPatients->sum('amount');
            $totalDiscount = $dayPatients->sum('discount');

            return [
                'date' => Carbon::parse($date)->format('d-M-Y'),
                'qty' => $dayPatients->count(),
                'amount' => $totalAmount,
                'discount' => $totalDiscount,
                'net_amount' => $totalAmount - $totalDiscount,
                'paid_amount' => $dayPatients->sum('paid_amount'),
                'due_amount' => $dayPatients->sum('balance_amount'),
                'due_collection' => 0
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
            $revenue = $dayPatients->sum('credit_limit') ?? 0;

            return [
                'date' => Carbon::parse($date)->format('d-M-Y'),
                'qty' => $dayPatients->count(),
                'amount' => $revenue,
                'discount' => 0,
                'net_amount' => $revenue,
                'paid_amount' => 0,
                'due_amount' => $revenue,
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
            'due_collection' => $moduleData->sum('due_collection')
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
            case 'pathology':
                return 'Pathology Sales Report';
            case 'radiology':
                return 'Radiology Sales Report';
            case 'medicine':
                return 'Pharmacy Sales Report';
            case 'opd':
                return 'OPD Revenue Report';
            case 'ipd':
                return 'IPD Revenue Report';
            default:
                return 'Comprehensive Sales Report - All Modules';
        }
    }

    private function getPdfFileName($module)
    {
        $timestamp = now()->format('YmdHis');

        switch ($module) {
            case 'pathology':
                return "Pathology_Report_{$timestamp}.pdf";
            case 'radiology':
                return "Radiology_Report_{$timestamp}.pdf";
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
            'moduleDetails' => $data['moduleDetails'] ?? [],
            'selectedModule' => $data['selectedModule'],
            'summary' => $data['summary'] ?? [],
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
}
