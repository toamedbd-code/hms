<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\DueCollection;
use App\Models\InvoiceDesign;
use App\Models\IpdPatient;
use App\Models\Payment;
use App\Models\ProductReturn;
use App\Models\WebSetting;
use Illuminate\Http\Request;
use App\Services\AdminService;
use App\Services\AppoinmentService;
use App\Services\BillingService;
use App\Services\MedicineInventoryService;
use App\Services\OpdPatientService;
use App\Services\PatientService;
use App\Services\ReferralPersonService;
use App\Services\IpdDischargeBillingService;
use App\Traits\SystemTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use ValueError;
use Throwable;


class InvoiceController extends Controller
{
    use SystemTrait;

    protected $billingService, $medicineInventoryService, $adminService, $patientService, $referrerService, $opdService, $appoinmentService;

    private const ALLOWED_BILLING_MODULES = [
        'billing',
        'pathology',
        'radiology',
        'pharmacy',
        'reporting',
    ];

    public function __construct(BillingService $billingService, MedicineInventoryService $medicineInventoryService, AdminService $adminService, PatientService $patientService, ReferralPersonService $referrerService, OpdPatientService $opdService, AppoinmentService $appoinmentService)
    {
        $this->billingService = $billingService;
        $this->medicineInventoryService = $medicineInventoryService;
        $this->adminService = $adminService;
        $this->patientService = $patientService;
        $this->referrerService = $referrerService;
        $this->opdService = $opdService;
        $this->appoinmentService = $appoinmentService;
    }

    public function downloadInvoice(Request $request)
    {
        $printToken = trim((string) ($request->input('print_token') ?? ''));
        $fastOpen = $request->boolean('fast_open');
        $autoPrint = $request->boolean('auto_print') || ($fastOpen && $printToken !== '');

        if (!$request->filled('id') && $printToken !== '') {
            $resolvedBillId = Cache::get('print_token_' . $printToken);
            if ($resolvedBillId) {
                $request->merge(['id' => (int) $resolvedBillId]);
            } else {
                $module = (string) ($request->input('module') ?? 'billing');
                $safeModule = in_array($module, self::ALLOWED_BILLING_MODULES, true) ? $module : 'billing';
                $refreshUrl = route('backend.download.invoice', [
                    'print_token' => $printToken,
                    'module' => $safeModule,
                    'fast_open' => $fastOpen ? 1 : 0,
                    'auto_print' => $autoPrint ? 1 : 0,
                    '_ts' => now()->timestamp,
                ]);

                return response(
                    '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice</title><style>html,body{margin:0;padding:0;background:#fff;}</style><script>(function(){var base=' . json_encode($refreshUrl) . ';var tick=function(){try{window.location.replace(base+"&_r="+Date.now());}catch(e){window.location.href=base+"&_r="+Date.now();}};setTimeout(tick,30);})();</script><noscript><meta http-equiv="refresh" content="0.2;url=' . e($refreshUrl) . '"></noscript></head><body></body></html>',
                    200,
                    ['Content-Type' => 'text/html; charset=UTF-8']
                );
            }
        }

        // Backward-compatible: older callers may send module as an empty string.
        $request->merge([
            'module' => $request->input('module') ?: null,
        ]);

        $requestedModule = (string) ($request->input('module') ?? '');
        if ($requestedModule === 'reporting') {
            return redirect()->route('backend.download.report', [
                'id' => $request->input('id'),
                'module' => 'reporting',
            ]);
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:billings,id'],
            'module' => ['nullable', 'string', 'in:' . implode(',', self::ALLOWED_BILLING_MODULES)],
        ]);

        $module = $validated['module'] ?? 'billing';

        $billing = Billing::query()
            ->with([
                'patient',
                // load billItems with trashed so we can decide to restore if needed
                'billItems' => function ($q) {
                    $q->withTrashed();
                },
                'dueCollections',
                'payments',
                'admin',
            ])
            ->findOrFail($validated['id']);

        // If this billing was generated from an IPD discharge, ensure any
        // payments recorded against the IPD (but not yet linked to billing)
        // are attached so invoice totals correctly reflect paid/return amounts.
        try {
            $remarks = (string) ($billing->remarks ?? '');
            if (preg_match('/IPD#(\d+)/i', $remarks, $m)) {
                $ipdId = (int) ($m[1] ?? 0);
                if ($ipdId > 0) {
                    \App\Models\Payment::query()
                        ->whereNull('deleted_at')
                        ->where('status', 'Active')
                        ->where('ipd_patient_id', $ipdId)
                        ->whereNull('billing_id')
                        ->update(['billing_id' => $billing->id]);

                    // Reload relationships after attaching payments
                    $billing->loadMissing(['payments', 'dueCollections', 'billItems']);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('InvoiceController::downloadInvoice - failed to attach IPD payments to billing', ['err' => $e->getMessage(), 'billing_id' => $billing->id]);
        }

        $invoiceDateTime = $billing->created_at
            ? $billing->created_at->format('d-M-Y h:i:s A')
            : now()->format('d-M-Y h:i:s A');

        // If active bill items are missing but there are trashed IPD items
        // (Admission, surgeon fees etc.), restore those trashed IPD items
        // so they appear on the invoice rather than being hidden.
        try {
            // If there are trashed IPD-type bill items but no active IPD items,
            // restore only the trashed IPD items. This handles the case where
            // bed charges exist (active) but admission/surgeon IPD lines are
            // soft-deleted and therefore missing from the invoice.
            $activeIpdCount = $billing->billItems()->whereNull('deleted_at')
                ->whereRaw('LOWER(COALESCE(category, "")) = ?', ['ipd'])
                ->count();

            $trashedIpd = \App\Models\BillItem::withTrashed()
                ->where('billing_id', $billing->id)
                ->whereNotNull('deleted_at')
                ->whereRaw('LOWER(COALESCE(category, "")) = ?', ['ipd'])
                ->get();

            if ($trashedIpd->isNotEmpty() && $activeIpdCount === 0) {
                foreach ($trashedIpd as $t) {
                    try {
                        $t->restore();
                    } catch (\Throwable $inner) {
                        // ignore restore failures for individual rows
                    }
                }

                // Reload bill items after restore
                $billing->loadMissing(['billItems']);
            }
        } catch (\Throwable $e) {
            Log::warning('InvoiceController::downloadInvoice - failed to restore trashed IPD bill items', ['err' => $e->getMessage(), 'billing_id' => $billing->id]);
        }

        // For display prefer active (non-deleted) bill items so duplicates
        // from previous regenerations (soft-deleted) don't appear multiple times.
        $rawBillItems = $billing->billItems()->whereNull('deleted_at')->get() ?? collect();

        $billItems = $this->filterBillItemsByModule($rawBillItems, $module);

        // If module is billing, collapse duplicate bill items (same name+category)
        // to avoid duplicate bed-charge lines created by multiple regenerations.
        if ($module === 'billing') {
            $grouped = collect($billItems)->groupBy(function ($it) {
                $name = trim((string) ($it->item_name ?? $it->description ?? ''));
                $cat = trim((string) ($it->category ?? ''));
                return strtolower($name . '||' . $cat);
            })->map(function ($group) {
                $first = $group->first();
                $quantity = (float) $group->sum(function ($g) { return (float) ($g->quantity ?? $g->qty ?? 1); });
                $totalAmount = (float) $group->sum(function ($g) { return (float) ($g->total_amount ?? $g->net_amount ?? 0); });

                return (object) [
                    'item_name' => $first->item_name ?? ($first->description ?? 'Item'),
                    'quantity' => $quantity,
                    'total_amount' => $totalAmount,
                    'category' => $first->category ?? '',
                ];
            })->values();

            $billItems = $grouped;
        }

        $patient = $billing->patient;

        $invoiceDesign = $this->resolveInvoiceDesign($module);
        $designAssets = $this->getInvoiceDesignAssets($invoiceDesign);

        $barcode = '';
        if ($billing) {
            $barcodeSource = $billing->bill_number
                ?? $billing->invoice_number
                ?? ('BILLING-' . $billing->id);
            $barcode = $barcodeSource !== '' ? $this->generateBarcode($barcodeSource) : '';
        }

        $totals = $this->calculateFilteredTotals($billItems, $billing, $module);

        // Compute a deterministic base total derived from the rendered bill items so
        // invoice templates can always display the pre-discount/pre-vat item sum.
        // Prefer the grouped billItems totals (used for display) when available.
        $baseTotalFromItems = 0.0;
        try {
            $baseTotalFromItems = (float) collect($billItems)->sum(function ($it) {
                return (float) ($it->total_amount ?? $it->net_amount ?? 0);
            });
        } catch (\Throwable $e) {
            $baseTotalFromItems = (float) ($totals['total_amount'] ?? 0);
        }

        $baseTotalFromItems = round($baseTotalFromItems, 2);

        $productReturnAmount = (float) ProductReturn::query()
            ->where('billing_id', $billing->id)
            ->whereIn('status', ['approved', 'processed'])
            ->sum('total_amount');
        $returnAmount = $this->resolveInvoiceReturnAmount($billing, $request, $productReturnAmount);
        $adjustedDue = max(0, (float) $totals['due'] - $returnAmount);

        // Respect global VAT enable/disable setting: if VAT is disabled in WebSetting,
        // do not expose VAT values even if they are stored on the billing record.
        $ws = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();
        $globalVatEnabled = !empty($ws) ? (bool) ($ws->vat_enabled ?? false) : true;

        $vatPercentage = $globalVatEnabled ? (float) ($billing->vat_percentage ?? 0) : 0.0;
        $vatAmount = $globalVatEnabled ? (float) ($billing->vat_amount ?? 0) : 0.0;

        $data = [
            'billing' => $billing,
            'bill_number' => $billing->bill_number ?? '',
            'invoiceDateTime' => $invoiceDateTime,
            'printed_at' => now()->timezone('Asia/Dhaka')->format('d F, Y h:i:s a'),
            'patient_name' => $patient->name ?? 'N/A',
            'age' => $patient->age ?? 'N/A',
            'contact_no' => $billing->patient_mobile,
            'gender' => $billing->gender,
            'refd_by' => $billing->doctor_name ?? 'N/A',
            'bill_items' => $billItems,
            'total_amount' => $totals['total_amount'],
            // Provide an explicit base_total (sum of displayed items) so templates
            // can consistently show the original items total before discounts/VAT.
            'base_total' => $baseTotalFromItems,
            'vat' => $vatAmount,
            'vat_percentage' => $vatPercentage,
            'net_payable' => $totals['net_payable'],
            'discount' => $totals['discount'],
            'discount_type' => $billing->discount_type,
            'extra_flat_discount' => $billing->extra_flat_discount,
            'paid' => $totals['paid'],
            'due' => $totals['due'],
            'return_amount' => round($returnAmount, 2),
            'adjusted_due' => round($adjustedDue, 2),
            'delivery_date' => $billing->delivery_date,
            'remarks' => $billing->remarks ?? '',
            'prepared_by' => $billing?->admin?->name ?? '',
            'amount_in_words' => $this->numberToWords($totals['net_payable']),
            'header_image' => $designAssets['header_image'],
            'footer_image' => $designAssets['footer_image'],
            'footer_content' => $designAssets['footer_content'],
                'footer_content_position' => $designAssets['footer_content_position'] ?? 'above',
                'footer_font_size' => $designAssets['footer_font_size'] ?? 14,
                'header_height' => $designAssets['header_height'],
                'footer_height' => $designAssets['footer_height'],
            'barcode' => $barcode,
            'module' => $module,
            'auto_print' => $autoPrint,
            'is_fast_open' => $fastOpen,
        ];

        // Prefer web-served Bengali font URL when available so PDF renderer can fetch it.
        $banglaFontUrl = '';
        $banglaFontFile = public_path('fonts/NotoSansBengali-Regular.ttf');
        if (is_file($banglaFontFile)) {
            $banglaFontUrl = asset('fonts/NotoSansBengali-Regular.ttf');
        }

        // Expose normalized absolute path so blade can embed font as data-uri reliably on Windows/Linux.
        $banglaFontPath = '';
        if (is_file($banglaFontFile)) {
            $banglaFontPath = str_replace('\\', '/', $banglaFontFile);
        }

        $data['banglaFontUrl'] = $banglaFontUrl;
        $data['banglaFontPath'] = $banglaFontPath;

        // Read invoice-specific settings from WebSetting (do NOT apply reporting toggles to invoices)
        $websettingActive = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();
        $attendanceOptions = $websettingActive?->attendance_device_options ?? [];
        if (!is_array($attendanceOptions)) {
            try {
                $attendanceOptions = is_string($attendanceOptions) && trim($attendanceOptions) !== '' ? json_decode($attendanceOptions, true) : [];
            } catch (\Throwable $e) {
                $attendanceOptions = [];
            }
        }
        $attendanceOptions = is_array($attendanceOptions) ? $attendanceOptions : [];
        // Invoice should use `invoice` options when present; otherwise use invoice design defaults
        $invoiceOptions = data_get($attendanceOptions, 'invoice', []);
        // Prefer explicit show_header/show_footer flags if available in invoice options
        $settingShowHeader = array_key_exists('show_header', $invoiceOptions) ? (bool) $invoiceOptions['show_header'] : null;
        $settingShowFooter = array_key_exists('show_footer', $invoiceOptions) ? (bool) $invoiceOptions['show_footer'] : null;
        if ($settingShowHeader !== null || $settingShowFooter !== null) {
            $showHeader = $settingShowHeader !== null ? $settingShowHeader : true;
            $showFooter = $settingShowFooter !== null ? $settingShowFooter : true;
            $showHeaderFooter = $showHeader && $showFooter;
        } else {
            // default: invoices show header/footer
            $showHeaderFooter = true;
        }
        // expose both camelCase and snake_case variables to views
        $data['showHeaderFooter'] = $showHeaderFooter;
        $data['show_header_footer'] = $showHeaderFooter;
        // invoice layout heights (px) — prefer invoice options layout, fall back to invoice design values
        $layout = data_get($invoiceOptions ?? [], 'layout', []);
        $reportHeaderHeightPx = max(0, (int) ($layout['header_height'] ?? $designAssets['header_height'] ?? 115));
        $reportFooterHeightPx = max(0, (int) ($layout['footer_height'] ?? $designAssets['footer_height'] ?? 70));
        if (! $showHeaderFooter) {
            $reportHeaderHeightPx = 0;
            $reportFooterHeightPx = 0;
        }
        $data['reportHeaderHeight'] = $reportHeaderHeightPx;
        $data['reportFooterHeight'] = $reportFooterHeightPx;

        $safeBillNo = Str::of((string) ($billing->bill_number ?? $billing->id))
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
            ->toString();
        $safeModule = Str::of((string) $module)
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
            ->toString();
        $filename = 'invoice_' . $safeBillNo . '_' . $safeModule . '.pdf';

        // Fast open mode: return full invoice Blade HTML directly to avoid
        // PDF rendering delay while keeping the same invoice layout.
        if ($fastOpen) {
            return response(view('frontend.invoice.pdf', $data)->render(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        try {
            // Keep original invoice look with DejaVu Sans as primary font.
            $pdfOutput = $this->buildInvoicePdf($data, 'dejavu sans')->output();
        } catch (ValueError $domPdfException) {
            // Retry once with a core font and no dynamic footer HTML if font parsing fails.
            $fallbackData = $data;
            $fallbackData['footer_content'] = '';

            try {
                $pdfOutput = $this->buildInvoicePdf($fallbackData, 'helvetica')->output();
            } catch (Throwable $fallbackException) {
                Log::error('Invoice PDF fallback generation failed after ValueError.', [
                    'billing_id' => $billing->id ?? null,
                    'module' => $module,
                    'error' => $fallbackException->getMessage(),
                ]);

                try {
                    $renderedHtml = view('frontend.invoice.pdf', $fallbackData)->render();
                    $sanitizedHtml = $this->sanitizeRenderedHtmlForPdf($renderedHtml);
                    $pdfOutput = $this->buildInvoicePdfWithMpdfFromHtml($sanitizedHtml);
                } catch (Throwable $mpdfException) {
                    Log::error('Invoice PDF mPDF fallback failed after ValueError.', [
                        'billing_id' => $billing->id ?? null,
                        'module' => $module,
                        'error' => $mpdfException->getMessage(),
                    ]);

                    $emergencyHtml = $this->buildEmergencyInvoiceHtml($fallbackData);
                    $pdfOutput = $this->buildInvoicePdfFromHtml($emergencyHtml, 'helvetica')->output();
                }
            }
        } catch (Throwable $pdfException) {
            Log::error('Invoice PDF generation failed.', [
                'billing_id' => $billing->id ?? null,
                'module' => $module,
                'error' => $pdfException->getMessage(),
            ]);

            try {
                $renderedHtml = view('frontend.invoice.pdf', $data)->render();
                $sanitizedHtml = $this->sanitizeRenderedHtmlForPdf($renderedHtml);
                $pdfOutput = $this->buildInvoicePdfWithMpdfFromHtml($sanitizedHtml);
            } catch (Throwable $mpdfException) {
                Log::error('Invoice PDF mPDF fallback failed.', [
                    'billing_id' => $billing->id ?? null,
                    'module' => $module,
                    'error' => $mpdfException->getMessage(),
                ]);

                $emergencyHtml = $this->buildEmergencyInvoiceHtml($data);
                $pdfOutput = $this->buildInvoicePdfFromHtml($emergencyHtml, 'helvetica')->output();
            }
        }

        return response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function buildInvoicePdf(array $data, string $defaultFont)
    {
        $pdf = Pdf::loadView('frontend.invoice.pdf', $data);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => $defaultFont,
            'dpi' => 96,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => false,
        ]);

        return $pdf;
    }

    private function buildInvoicePdfFromHtml(string $html, string $defaultFont)
    {
        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => $defaultFont,
            'dpi' => 96,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => false,
        ]);

        return $pdf;
    }

    private function isEmptyPathValueError(ValueError $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'path cannot be empty');
    }

    private function filterBillItemsByModule($billItems, $module)
    {
        $module = $module ?: 'billing';
        $billItems = $billItems instanceof \Illuminate\Support\Collection ? $billItems : collect($billItems);

        if ($module === 'billing') {
            return $billItems;
        }

        $moduleMapping = [
            'pathology' => 'Pathology',
            'radiology' => 'Radiology',
            'pharmacy' => 'Medicine'
        ];

        $categoryFilter = $moduleMapping[$module] ?? null;

        if (!$categoryFilter) {
            return $billItems;
        }

        return $billItems->filter(function ($item) use ($categoryFilter) {
            return $item->category === $categoryFilter;
        });
    }

    private function calculateFilteredTotals($filteredItems, $billing, $module)
    {
        $filteredItems = $filteredItems instanceof \Illuminate\Support\Collection ? $filteredItems : collect($filteredItems);

        // Billing (full invoice) totals
        if ($module === 'billing') {
            // Prefer authoritative bill items sum when available (use active items only)
            $totalFromItems = (float) \App\Models\BillItem::where('billing_id', $billing->id)->sum('net_amount');
            $total = $totalFromItems > 0 ? $totalFromItems : (float) ($billing->total ?? 0);

            // When payable_amount exists on the billing, it represents the actual
            // post-discount invoice amount and should drive the final due logic.
            $billingPayableAmount = max(0, (float) ($billing->payable_amount ?? 0));
            $extraDiscount = max(0, (float) ($billing->extra_flat_discount ?? 0));
            $vatAmount = (float) ($billing->vat_amount ?? 0);

            if ($billingPayableAmount > 0) {
                $discountPercent = $billing->discount_type === 'percentage'
                    ? (float) ($billing->discount ?? 0)
                    : null;
                $discountAmount = $billing->discount_type === 'percentage'
                    ? max(0, ($total * $discountPercent) / 100)
                    : max(0, (float) ($billing->discount ?? 0));

                $netPayable = $billingPayableAmount;
            } elseif ($totalFromItems > 0) {
                // Bill items already include discounts; do not subtract billing->discount again.
                $discountPercent = null;
                $discountAmount = 0;
                $netPayable = max(0, $total - $extraDiscount + $vatAmount);
            } else {
                if ($billing->discount_type === 'percentage') {
                    $discountPercent = (float) ($billing->discount ?? 0);
                    $discountAmount = max(0, ($total * $discountPercent) / 100);
                } else {
                    $discountPercent = null;
                    $discountAmount = max(0, (float) ($billing->discount ?? 0));
                }

                $netPayable = max(0, $total - $discountAmount - $extraDiscount + $vatAmount);
            }

            // Determine payments as of invoice creation time (so "Paid (Invoice Time)" shows
            // amounts paid before/at the bill's created_at). Also compute total paid
            // including any due collections/payments after invoice time for reporting.
            $invoiceTime = $billing->created_at ?? now();

            $paymentsAtInvoice = (float) \App\Models\Payment::where('billing_id', $billing->id)
                ->where('created_at', '<=', $invoiceTime)
                ->sum('amount');

            $dueCollectedAtInvoice = (float) \App\Models\DueCollection::where('billing_id', $billing->id)
                ->where('created_at', '<=', $invoiceTime)
                ->sum('collected_amount');

            $paidAtInvoice = max(0, $paymentsAtInvoice + $dueCollectedAtInvoice);

            $paymentsSum = (float) \App\Models\Payment::where('billing_id', $billing->id)->whereNull('deleted_at')->sum('amount');
            $dueCollected = (float) \App\Models\DueCollection::where('billing_id', $billing->id)->sum('collected_amount');

            // Use the largest reliable paid amount source and then subtract any
            // return/refund amount. This ensures invoices reflect both normal
            // payments and later due-collections without undercounting the paid total.
            $billingPaid = (float) ($billing->paid_amt ?? 0);
            $receivingAmt = (float) ($billing->receiving_amt ?? 0);
            $billingReturnAmt = (float) ($billing->return_amt ?? $billing->return_amount ?? 0);

            $grossPaidFromReceiving = max(0, $receivingAmt);
            $grossPaidFromTransactions = max(0, $paymentsSum + $dueCollected);
            $grossPaidFromBilling = max(0, $billingPaid);
            $grossPaid = max($grossPaidFromReceiving, $grossPaidFromTransactions, $grossPaidFromBilling);

            // `return_amt` already exists on billing, so subtract it to compute net paid.
            $totalPaid = max(0, round($grossPaid - $billingReturnAmt, 2));
            $computedDue = max(0, round($netPayable - $totalPaid, 2));

            // For display: show the billing-level discount value for clarity
            $displayDiscount = $billing->discount_type === 'percentage'
                ? round((float) ($billing->discount ?? 0), 2)
                : round((float) ($billing->discount ?? $discountAmount), 2);

            return [
                'total_amount' => round($total, 2),
                'discount' => $displayDiscount,
                'vat' => round($vatAmount, 2),
                'net_payable' => round($netPayable, 2),
                'paid_at_invoice' => round($paidAtInvoice, 2),
                'paid' => round($totalPaid, 2),
                'due' => round($computedDue, 2)
            ];
        }

        // Module-filtered totals (pathology/pharmacy/radiology)
        $itemTotal = $filteredItems->sum(function ($item) {
            if (is_array($item)) {
                return (float) ($item['total_amount'] ?? $item['net_amount'] ?? 0);
            }

            return (float) ($item->total_amount ?? $item->net_amount ?? 0);
        });
        $itemDiscount = $filteredItems->sum(function ($item) {
            if (is_array($item)) {
                return (float) ($item['discount'] ?? 0);
            }

            return (float) ($item->discount ?? 0);
        });

        // Allocate billing-level discount appropriately
        $proportionalDiscountAmount = 0;
        $discountPercent = null;
        if ($billing->total > 0 && (float) ($billing->discount ?? 0) > 0) {
            if ($billing->discount_type === 'percentage') {
                $discountPercent = (float) $billing->discount;
                $proportionalDiscountAmount = ($itemTotal * $discountPercent) / 100;
            } else {
                $proportionalDiscountAmount = ($itemTotal / $billing->total) * (float) $billing->discount;
            }
        }

        $netPayable = $itemTotal - $itemDiscount - $proportionalDiscountAmount;

        $paidAmount = max(0, (float) ($billing->receiving_amt ?? $billing->paid_amt ?? 0));

        // IPD final bill is assembled from the live running-bill lines, so its total
        // should follow the running summary directly. Using billing->payable_amount
        // as a proportional denominator is incorrect when the stored billing record
        // still carries stale totals from an older snapshot.
        $paid = $module === 'ipd'
            ? min($paidAmount, $netPayable)
            : (function () use ($billing, $netPayable) {
                $proportionalPaid = 0;
                if ((float) ($billing->payable_amount ?? 0) > 0 && (float) ($billing->paid_amt ?? 0) > 0) {
                    $proportionalPaid = ($netPayable / (float) ($billing->payable_amount ?? 0)) * (float) ($billing->paid_amt ?? 0);
                }

                return $proportionalPaid;
            })();

        $due = $netPayable - $paid;

        return [
            'total_amount' => round($itemTotal, 2),
            'discount' => $billing->discount_type === 'percentage' ? round($discountPercent ?? 0, 2) : round($itemDiscount + $proportionalDiscountAmount, 2),
            'net_payable' => round($netPayable, 2),
            'paid' => round($paid, 2),
            'due' => max(0, round($due, 2))
        ];
    }

    private function calculateFastOpenTotals($filteredItems, Billing $billing): array
    {
        $totalAmountFromItems = (float) $filteredItems->sum(function ($item) {
            return (float) ($item->amount ?? 0) * (float) ($item->qty ?? 1);
        });

        $totalAmount = $totalAmountFromItems > 0
            ? $totalAmountFromItems
            : (float) ($billing->total ?? $billing->invoice_amount ?? 0);

        $netPayable = (float) ($billing->invoice_amount ?? $totalAmount);
        if (($billing->discount_type ?? '') === 'percentage') {
            $discount = (float) ($billing->discount ?? 0);
        } else {
            $discount = max(0, $totalAmount - $netPayable);
        }
        $paid = max(0, (float) ($billing->receiving_amt ?? 0));
        $due = max(0, $netPayable - $paid);

        return [
            'total_amount' => $totalAmount,
            'net_payable' => $netPayable,
            'discount' => $discount,
            'paid' => $paid,
            'paid_at_invoice' => $paid,
            'due' => $due,
        ];
    }

    private function generateBarcode($billNumber)
    {
        $dns1d = new DNS1D();
        $barcode = $dns1d->getBarcodePNG($billNumber, 'C128', 3, 60);
        return 'data:image/png;base64,' . $barcode;
    }

    protected function resolveInvoiceReturnAmount(Billing $billing, Request $request, float $productReturnAmount = 0.0): float
    {
        $hasRequestedReturn = $request->exists('return_amount') || $request->exists('return_amt');
        $requestedReturn = (float) ($request->input('return_amount') ?? $request->input('return_amt') ?? 0);
        if ($hasRequestedReturn) {
            return round($requestedReturn, 2);
        }

        $persistedReturn = (float) ($billing->return_amt ?? $billing->return_amount ?? 0);
        if ($persistedReturn > 0) {
            return round($persistedReturn, 2);
        }

        $invoiceAmount = (float) ($billing->invoice_amount ?? $billing->payable_amount ?? 0);
        $receivingAmount = (float) ($billing->receiving_amt ?? 0);
        $payableAmount = (float) ($billing->payable_amount ?? $billing->total ?? 0);
        $paidAmount = (float) ($billing->paid_amt ?? 0);

        $cashReturnAmount = 0.0;
        if ($invoiceAmount > 0) {
            $cashReturnAmount = max(0, $receivingAmount - $invoiceAmount);
        }

        $overpaymentReturn = max(0, $paidAmount - $payableAmount);
        $derivedReturn = $productReturnAmount + max($cashReturnAmount, $overpaymentReturn);

        return round($derivedReturn, 2);
    }

    protected function resolveIpdInvoiceLineItems($billingLineItems, array $runningLineItems = []): array
    {
        $normalizeLine = function ($line) {
            if (is_array($line)) {
                return [
                    'item_name' => $line['item_name'] ?? ($line['description'] ?? 'Item'),
                    'quantity' => isset($line['quantity']) ? (float) $line['quantity'] : 1,
                    'unit_price' => isset($line['unit_price']) ? (float) $line['unit_price'] : 0,
                    'net_amount' => isset($line['net_amount']) ? (float) $line['net_amount'] : (float) ($line['total_amount'] ?? 0),
                    'category' => $line['category'] ?? '',
                ];
            }

            return [
                'item_name' => $line->item_name ?? ($line->description ?? 'Item'),
                'quantity' => isset($line->quantity) ? (float) $line->quantity : 1,
                'unit_price' => isset($line->unit_price) ? (float) $line->unit_price : 0,
                'net_amount' => isset($line->net_amount) ? (float) $line->net_amount : (float) ($line->total_amount ?? 0),
                'category' => $line->category ?? '',
            ];
        };

        $billingItems = collect($billingLineItems ?? [])->map($normalizeLine)->values();
        $runningItems = collect($runningLineItems ?? [])->map($normalizeLine)->values();

        if ($billingItems->isEmpty()) {
            return $runningItems->all();
        }

        $buildSignature = function (array $line): string {
            $name = strtolower(trim((string) ($line['item_name'] ?? '')));
            $category = strtolower(trim((string) ($line['category'] ?? '')));
            $unitPrice = number_format((float) ($line['unit_price'] ?? 0), 2, '.', '');
            $quantity = number_format((float) ($line['quantity'] ?? 1), 2, '.', '');
            $netAmount = number_format((float) ($line['net_amount'] ?? 0), 2, '.', '');

            return implode('|', [$category, $name, $unitPrice, $quantity, $netAmount]);
        };

        $merged = [];
        $seen = [];

        foreach ($billingItems as $line) {
            $signature = $buildSignature($line);
            if ($signature === '' || isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $merged[] = $line;
        }

        foreach ($runningItems as $line) {
            $signature = $buildSignature($line);
            if ($signature === '' || isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $merged[] = $line;
        }

        return $merged;
    }

    protected function resolveIpdInvoiceDueCollections(IpdPatient $ipdpatient)
    {
        $query = DueCollection::query();

        if (!empty($ipdpatient->billing_id)) {
            $query->where(function ($q) use ($ipdpatient) {
                $q->where('billing_id', $ipdpatient->billing_id);
                $q->orWhere(function ($sub) use ($ipdpatient) {
                    $sub->whereNotNull('note')
                        ->where(function ($noteQuery) use ($ipdpatient) {
                            $noteQuery->where('note', 'like', '%ipd_patient_id:' . $ipdpatient->id . '%')
                                ->orWhere('note', 'like', '%ipd_patient_id: ' . $ipdpatient->id . '%')
                                ->orWhere('note', 'like', '%Collected via IPD payment%');
                        });
                });
            });
        } else {
            $query->where(function ($q) use ($ipdpatient) {
                $q->whereNotNull('note')
                    ->where(function ($noteQuery) use ($ipdpatient) {
                        $noteQuery->where('note', 'like', '%ipd_patient_id:' . $ipdpatient->id . '%')
                            ->orWhere('note', 'like', '%ipd_patient_id: ' . $ipdpatient->id . '%')
                            ->orWhere('note', 'like', '%Collected via IPD payment%');
                    });
            });
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('due_collections', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->orderBy('collected_at')->orderBy('created_at')->get();
    }

    protected function normalizeIpdInvoicePaymentsAndDueCollections($payments, $dueCollections, $invoiceTime): array
    {
        $normalizedPayments = collect();
        $invoiceTimePaidAmount = 0.0;

        foreach (collect($payments ?? []) as $p) {
            $paymentAmount = (float) ($p->amount ?? 0);
            $paymentCreatedAt = $p->created_at ?? now();
            if ($paymentCreatedAt <= $invoiceTime) {
                $invoiceTimePaidAmount += $paymentAmount;
            }

            $normalizedPayments->push((object) [
                'type' => 'payment',
                'created_at' => $paymentCreatedAt,
                'payment_method' => $p->payment_method ?? 'Cash',
                'transaction_id' => $p->transaction_id ?? '',
                'notes' => $p->notes ?? '',
                'amount' => $paymentAmount,
            ]);
        }

        foreach (collect($dueCollections ?? []) as $d) {
            $dueCollectionAmount = (float) ($d->collected_amount ?? $d->amount ?? 0);
            $dueCollectionCreatedAt = $d->collected_at ?? $d->created_at ?? now();
            if ($dueCollectionCreatedAt <= $invoiceTime) {
                $invoiceTimePaidAmount += $dueCollectionAmount;
            }

            $normalizedPayments->push((object) [
                'type' => 'due_collection',
                'created_at' => $dueCollectionCreatedAt,
                'payment_method' => $d->payment_method ?? 'Due Collection',
                'transaction_id' => '',
                'notes' => $d->note ?? '',
                'amount' => $dueCollectionAmount,
            ]);
        }

        $normalizedPayments = $normalizedPayments->sortBy(function ($row) {
            return $row->created_at ?? now();
        })->values();

        $paymentsHistory = $normalizedPayments->where('type', 'payment')->values();
        $dueCollectionsList = $normalizedPayments->where('type', 'due_collection')->values();
        $paymentsOnlyTotal = (float) $paymentsHistory->sum('amount');
        $dueCollectionsTotal = (float) $dueCollectionsList->sum('amount');

        return [
            'normalized_payments' => $normalizedPayments,
            'payments' => $paymentsHistory,
            'due_collections' => $dueCollectionsList,
            'invoice_time_paid_amount' => round($invoiceTimePaidAmount, 2),
            'total_paid' => round($paymentsOnlyTotal + $dueCollectionsTotal, 2),
        ];
    }

    private function resolveInvoiceDesign(string $module): ?InvoiceDesign
    {
        $normalizedModule = strtolower(trim($module));

        $design = InvoiceDesign::query()
            ->where('status', 'Active')
            ->whereRaw('LOWER(TRIM(module)) = ?', [$normalizedModule])
            ->first();

        if ($design) {
            return $design;
        }

        $design = InvoiceDesign::query()
            ->where('status', 'Active')
            ->whereNull('module')
            ->first();

        if ($design) {
            return $design;
        }

        return InvoiceDesign::query()
            ->where('status', 'Active')
            ->orderByRaw("CASE WHEN header_photo_path IS NOT NULL OR footer_photo_path IS NOT NULL THEN 0 ELSE 1 END")
            ->first();
    }

    private function getInvoiceDesignAssets(?InvoiceDesign $invoiceDesign, bool $fastOpen = false): array
    {
        $headerImage = $fastOpen
            ? $this->storageInvoiceImageToBrowserUrl($invoiceDesign?->header_photo_path)
            : $this->storageInvoiceImageToDataUri($invoiceDesign?->header_photo_path);
        $footerImage = $fastOpen
            ? $this->storageInvoiceImageToBrowserUrl($invoiceDesign?->footer_photo_path)
            : $this->storageInvoiceImageToDataUri($invoiceDesign?->footer_photo_path);

        return [
            'header_image' => $headerImage,
            'footer_image' => $footerImage,
            'footer_content' => $this->sanitizeHtmlForPdf((string) ($invoiceDesign?->footer_content ?? '')),
            // per-design option: whether footer content appears "above" or "below" the footer image
            'footer_content_position' => in_array(strtolower((string) ($invoiceDesign?->footer_content_position ?? '')), ['above', 'below'])
                ? strtolower((string) $invoiceDesign->footer_content_position)
                : 'above',
            'footer_font_size' => max(6, min(72, (int) ($invoiceDesign?->footer_font_size ?? 14))),
            'header_height' => (int) ($invoiceDesign?->header_height ?? 115),
            'footer_height' => (int) ($invoiceDesign?->footer_height ?? 70),
        ];
    }

    private function sanitizeHtmlForPdf(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // DomPDF can crash (fopen('')) when HTML includes custom font loaders.
        // Strip external styles and font declarations from admin-configured HTML.
        $html = preg_replace('/<\s*link\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<\s*style\b[^>]*>.*?<\s*\/\s*style\s*>/is', '', $html) ?? $html;

        // Remove @font-face and @import fragments in case they appear outside <style>.
        $html = preg_replace('/@font-face\s*\{.*?\}/is', '', $html) ?? $html;
        $html = preg_replace('/@import\s+url\([^)]*\)\s*;?/i', '', $html) ?? $html;

        // Remove font-family declarations from inline styles.
        $html = preg_replace_callback(
            '/\sstyle\s*=\s*(["\'])(.*?)\1/is',
            static function (array $matches): string {
                $quote = $matches[1];
                $style = $matches[2];

                $style = preg_replace('/font-family\s*:\s*[^;]+;?/i', '', $style) ?? $style;
                $style = preg_replace('/src\s*:\s*url\([^)]*\)\s*;?/i', '', $style) ?? $style;
                $style = trim(preg_replace('/\s{2,}/', ' ', $style) ?? $style);

                return ' style=' . $quote . $style . $quote;
            },
            $html
        ) ?? $html;

        return trim($html);
    }

    private function sanitizeRenderedHtmlForPdf(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Keep template CSS/layout, but remove problematic custom font declarations.
        $html = str_ireplace(['"DejaVu Sans"', "'DejaVu Sans'", 'DejaVu Sans'], 'Helvetica', $html);
        $html = preg_replace('/@font-face\s*\{.*?\}/is', '', $html) ?? $html;
        $html = preg_replace('/@import\s+url\([^)]*\)\s*;?/i', '', $html) ?? $html;
        $html = preg_replace('/font-family\s*:\s*[^;}{]+;?/i', '', $html) ?? $html;
        $html = preg_replace('/font\s*:\s*[^;}{]*?\b(?:serif|sans-serif|monospace|arial|helvetica|dejavu|times)\b[^;}{]*;?/i', '', $html) ?? $html;
        $html = preg_replace('/src\s*:\s*url\((?:\s*["\"])??\s*(?:["\"])??\s*\)\s*;?/i', '', $html) ?? $html;

        return trim($html);
    }

    private function buildInvoicePdfWithMpdfFromHtml(string $html): string
    {
        $tempDir = storage_path('app/mpdf-temp');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        // Load invoice-specific settings from web settings; do NOT apply reporting toggles to invoices
        $websetting = WebSetting::where('status', 'Active')->orderBy('id', 'desc')->first();
        $attendanceOptions = $websetting?->attendance_device_options ?? [];
        if (!is_array($attendanceOptions)) {
            try {
                $attendanceOptions = is_string($attendanceOptions) && trim($attendanceOptions) !== '' ? json_decode($attendanceOptions, true) : [];
            } catch (\Throwable $e) {
                $attendanceOptions = [];
            }
        }
        $attendanceOptions = is_array($attendanceOptions) ? $attendanceOptions : [];

        $invoiceOptions = data_get($attendanceOptions, 'invoice', []);
        // Respect separate show_header/show_footer when present in invoice options
        $settingShowHeader = array_key_exists('show_header', $invoiceOptions) ? (bool) $invoiceOptions['show_header'] : null;
        $settingShowFooter = array_key_exists('show_footer', $invoiceOptions) ? (bool) $invoiceOptions['show_footer'] : null;
        if ($settingShowHeader !== null || $settingShowFooter !== null) {
            $showHeader = $settingShowHeader !== null ? $settingShowHeader : true;
            $showFooter = $settingShowFooter !== null ? $settingShowFooter : true;
            $showHeaderFooter = $showHeader && $showFooter;
        } else {
            $showHeaderFooter = true; // default: invoices show header/footer
        }
        $layout = data_get($invoiceOptions ?? [], 'layout', []);
        $reportHeaderHeightPx = max(0, (int) ($layout['header_height'] ?? 115));
        $reportFooterHeightPx = max(0, (int) ($layout['footer_height'] ?? 70));
        $pageMarginTop = isset($layout['page_margin_top']) ? (int) $layout['page_margin_top'] : 12;
        $pageMarginBottom = isset($layout['page_margin_bottom']) ? (int) $layout['page_margin_bottom'] : 24;

        $pxToMm = function ($px) {
            return round(((float) $px) * 25.4 / 96, 2);
        };

        $marginHeaderMm = $showHeaderFooter ? $pxToMm($reportHeaderHeightPx) : 0;
        $marginFooterMm = $showHeaderFooter ? $pxToMm($reportFooterHeightPx) : 0;

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => max(0, (int) $pageMarginTop),
            'margin_bottom' => max(0, (int) $pageMarginBottom),
            'margin_header' => $marginHeaderMm,
            'margin_footer' => $marginFooterMm,
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function buildEmergencyInvoiceHtml(array $data): string
    {
        $billNo = e((string) ($data['bill_number'] ?? 'N/A'));
        $invoiceDateTime = e((string) ($data['invoiceDateTime'] ?? 'N/A'));
        $patientName = e((string) ($data['patient_name'] ?? 'N/A'));
        $age = e((string) ($data['age'] ?? 'N/A'));
        $contactNo = e((string) ($data['contact_no'] ?? 'N/A'));
        $gender = e((string) ($data['gender'] ?? 'N/A'));
        $refdBy = e((string) ($data['refd_by'] ?? 'N/A'));
        $deliveryDate = e((string) ($data['delivery_date'] ?? 'N/A'));
        $preparedBy = e((string) ($data['prepared_by'] ?? 'N/A'));
        $amountWords = e((string) ($data['amount_in_words'] ?? 'N/A'));
        $printedAt = e((string) ($data['printed_at'] ?? $data['invoiceDateTime'] ?? 'N/A'));
        $fallbackFooterLine = e((string) config('app.invoice_footer_fallback_line', 'Powered By: www.toamedit.com Support: 01919-592638'));
        $headerImage = trim((string) ($data['header_image'] ?? ''));
        $footerImage = trim((string) ($data['footer_image'] ?? ''));
        $footerContent = (string) ($data['footer_content'] ?? '');
        $barcodeImage = trim((string) ($data['barcode'] ?? ''));

        $headerBlock = $headerImage !== ''
            ? "<div class='header-wrap'><img class='header-image' src='" . e($headerImage) . "' alt='Header'></div>"
            : '';

        $footerImageBlock = $footerImage !== ''
            ? "<div class='footer-image-wrap'><img class='footer-image' src='" . e($footerImage) . "' alt='Footer'></div>"
            : '';

        $footerContentBlock = $footerContent !== ''
            ? "<div class='footer-content'>{$footerContent}</div>"
            : '';

        // Determine footer content positioning: 'above' places content above the image.
        $footerPosition = in_array(strtolower((string) ($data['footer_content_position'] ?? 'above')), ['above', 'below'])
            ? strtolower((string) ($data['footer_content_position'] ?? 'above'))
            : 'above';

        $footerTop = $footerPosition === 'above' ? $footerContentBlock : $footerImageBlock;
        $footerBottom = $footerPosition === 'above' ? $footerImageBlock : $footerContentBlock;

        $barcodeLeft = $barcodeImage !== ''
            ? "<img class='barcode-image' src='" . e($barcodeImage) . "' alt='Barcode'>"
            : 'Barcode';

        $barcodeRight = $barcodeImage !== ''
            ? "<img class='barcode-image barcode-image-right' src='" . e($barcodeImage) . "' alt='Barcode'>"
            : 'Barcode';

        $total = number_format((float) ($data['total_amount'] ?? 0), 2);
        $vat = number_format((float) ($data['vat'] ?? 0), 2);
        $discount = number_format((float) ($data['discount'] ?? 0), 2);
        $netPayable = number_format((float) ($data['net_payable'] ?? 0), 2);
        // Paid at invoice time vs total paid (including later due collections)
        $paidAtInvoice = number_format((float) ($data['paid_at_invoice'] ?? $data['paid'] ?? 0), 2);
        $totalPaid = number_format((float) ($data['paid'] ?? 0), 2);
        $due = number_format((float) ($data['due'] ?? 0), 2);

        $discountType = strtolower((string) ($data['discount_type'] ?? ''));
        $discountLabel = $discountType === 'percent'
            ? 'Discount (' . number_format((float) ($data['extra_flat_discount'] ?? 0), 2) . '%)'
            : 'Discount';

        $rows = '';
        foreach (($data['bill_items'] ?? []) as $index => $item) {
            $name = e((string) ($item->item_name ?? $item['item_name'] ?? 'Item'));
            $qty = e((string) ($item->quantity ?? $item['quantity'] ?? '1'));
            $priceRaw = (float) ($item->total_amount ?? $item['total_amount'] ?? 0);
            $price = number_format($priceRaw, 2);
            $sl = $index + 1;

            $rows .= "<tr><td class='center'>{$sl}</td><td>{$name}</td><td class='center'>{$qty}</td><td class='right'>{$price}</td></tr>";
        }

        if ($rows === '') {
            $rows = "<tr><td class='center'>-</td><td>No items found</td><td class='center'>-</td><td class='right'>0.00</td></tr>";
        }

        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Invoice</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 14px;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #111;
            line-height: 1.35;
        }
        .sheet {
            border: 1px solid #dadada;
            padding: 10px;
        }
        .header-wrap {
            width: 100%;
            text-align: center;
            margin-bottom: 8px;
        }
        .header-image {
            width: 100%;
            height: 70px;
            max-height: 70px;
            object-fit: fill;
            display: block;
        }
        .title-wrap { width: 100%; margin-bottom: 10px; }
        .title-wrap td { vertical-align: middle; }
        .barcode-text { width: 20%; font-size: 11px; font-weight: 700; }
        .barcode-right { text-align: right; }
        .barcode-image { height: 28px; width: 150px; object-fit: contain; }
        .barcode-image-right { float: right; }
        .receipt-title {
            width: 60%;
            text-align: center;
            font-size: 19px;
            font-weight: 700;
            letter-spacing: 2px;
        }
        .meta { width: 100%; margin-bottom: 2px; }
        .meta td { width: 50%; padding: 2px 0; vertical-align: top; }
        .meta strong { display: inline-block; min-width: 72px; }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 12px;
        }
        .items-table th {
            border: 1px solid #8f8f8f;
            padding: 5px 6px;
            text-align: left;
            background: #f2f2f2;
        }
        .items-table td {
            border: 1px solid #bdbdbd;
            padding: 5px 6px;
            vertical-align: top;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .delivery {
            margin-top: 7px;
            font-weight: 700;
        }
        .summary-wrap {
            width: 100%;
            margin-top: 8px;
        }
        .summary-left,
        .summary-right {
            width: 50%;
            vertical-align: top;
        }
        .due-badge {
            display: inline-block;
            background: #d93025;
            color: #fff;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 2px;
            margin-bottom: 5px;
        }
        .thanks {
            font-weight: 700;
            margin: 4px 0 8px;
        }
        .prepared-by { margin-top: 6px; }
        .totals {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .totals td {
            border-bottom: 1px solid #dcdcdc;
            padding: 3px 0;
        }
        .totals td:first-child { width: 65%; }
        .totals td:last-child { text-align: right; width: 35%; }
        .amount-words {
            margin-top: 7px;
            text-align: right;
            font-weight: 700;
        }
        .footer {
            margin-top: 10px;
            font-size: 11px;
            border-top: 1px dashed #aaa;
            padding-top: 6px;
            display: block;
            width: 100%;
        }
        .footer-image-wrap {
            width: 100%;
            text-align: center;
            margin-bottom: 4px;
        }
        .footer-image {
            width: 100%;
            max-height: 70px;
            object-fit: contain;
        }
        .footer-content {
            width: 100%;
            margin-bottom: 4px;
            line-height: 1.25;
        }
        .footer-left,
        .footer-right {
            display: table-cell;
            width: 50%;
            box-sizing: border-box;
        }
        .footer-left { padding-left: 10px; }
        .footer-right { text-align: right; padding-right: 10px; white-space: nowrap; }
    </style>
</head>
<body>
    <div class='sheet'>
    {$headerBlock}
    <table class='title-wrap'>
        <tr>
            <td class='barcode-text'>{$barcodeLeft}</td>
            <td class='receipt-title'>MONEY RECEIPT</td>
            <td class='barcode-text barcode-right'>{$barcodeRight}</td>
        </tr>
    </table>

    <table class='meta'>
        <tr>
            <td><strong>Bill No</strong>: {$billNo}</td>
            <td><strong>Date &amp; Time</strong>: {$invoiceDateTime}</td>
        </tr>
        <tr>
            <td><strong>Name</strong>: {$patientName}</td>
            <td><strong>Age</strong>: {$age}</td>
        </tr>
        <tr>
            <td><strong>Contact No</strong>: {$contactNo}</td>
            <td><strong>Gender</strong>: {$gender}</td>
        </tr>
        <tr>
            <td colspan='2'><strong>Refd. By</strong>: {$refdBy}</td>
        </tr>
    </table>

    <table class='items-table'>
        <thead>
            <tr>
                <th style='width:8%;' class='center'>SL</th>
                <th style='width:62%;'>Item Name</th>
                <th style='width:10%;' class='center'>Qty</th>
                <th style='width:20%;' class='right'>Price (Tk.)</th>
            </tr>
        </thead>
        <tbody>{$rows}</tbody>
    </table>

    <div class='delivery'>Delivery Date &amp; Time: {$deliveryDate}</div>

    <table class='summary-wrap'>
        <tr>
            <td class='summary-left'>
                <div class='due-badge'>DUE</div>
                <div class='thanks'>Thank You</div>
                <div class='prepared-by'><strong>Prepared By:</strong> {$preparedBy}</div>
            </td>
            <td class='summary-right'>
                <table class='totals'>
                    <tr><td>Total Amount Tk.</td><td>{$total}</td></tr>
                    <tr><td>Vat Tk.</td><td>{$vat}</td></tr>
                    <tr><td>{$discountLabel}</td><td>{$discount}</td></tr>
                    <tr><td><strong>Net Payable Tk.</strong></td><td><strong>{$netPayable}</strong></td></tr>
                    <tr><td>Paid (Invoice Time)</td><td>{$paidAtInvoice}</td></tr>
                    <tr><td>Total Paid Tk.</td><td>{$totalPaid}</td></tr>
                    <tr><td><strong>Due Tk.</strong></td><td><strong>{$due}</strong></td></tr>
                </table>
                <div class='amount-words'>{$amountWords}</div>
            </td>
        </tr>
    </table>

    <div class='footer'>
        {$footerTop}
        {$footerBottom}
        <div class='footer-left'>{$fallbackFooterLine}</div>
        <div class='footer-right'>Printing Date: {$printedAt}</div>
    </div>
    </div>
</body>
</html>";
    }

    private function storageInvoiceImageToDataUri(?string $publicStorageUrl): string
    {
        if (!$publicStorageUrl) {
            return '';
        }

        $rawPath = trim((string) $publicStorageUrl);
        if ($rawPath === '') {
            return '';
        }

        // Already converted (data URI), use as-is.
        if (str_starts_with($rawPath, 'data:image/')) {
            return $rawPath;
        }

        $pathFromUrl = $rawPath;
        if (preg_match('/^https?:\/\//i', $rawPath) === 1) {
            $parsed = parse_url($rawPath);
            $pathFromUrl = (string) ($parsed['path'] ?? '');
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $pathFromUrl), '/');
        $relativeStoragePath = '';

        if (str_contains($normalizedPath, 'storage/')) {
            $relativeStoragePath = ltrim(Str::after($normalizedPath, 'storage/'), '/');
        }

        $candidatePaths = [];

        if (is_file($rawPath)) {
            $candidatePaths[] = $rawPath;
        }

        if ($relativeStoragePath !== '') {
            $candidatePaths[] = storage_path('app/public/' . $relativeStoragePath);
            $candidatePaths[] = public_path('storage/' . $relativeStoragePath);
        }

        if ($normalizedPath !== '') {
            $candidatePaths[] = storage_path('app/public/' . $normalizedPath);
            $candidatePaths[] = public_path($normalizedPath);
        }

        $resolvedFilePath = '';
        foreach ($candidatePaths as $candidatePath) {
            if (is_string($candidatePath) && $candidatePath !== '' && is_file($candidatePath)) {
                $resolvedFilePath = $candidatePath;
                break;
            }
        }

        if ($resolvedFilePath === '') {
            return '';
        }

        $mime = @mime_content_type($resolvedFilePath) ?: 'image/png';
        $content = @file_get_contents($resolvedFilePath);
        if ($content === false || $content === '') {
            return '';
        }

        // DomPDF commonly fails to render WEBP in older environments.
        // Convert WEBP to PNG data URI when possible.
        if (strtolower($mime) === 'image/webp' && function_exists('imagecreatefromwebp') && function_exists('imagepng')) {
            $imageResource = @imagecreatefromwebp($resolvedFilePath);
            if ($imageResource !== false) {
                ob_start();
                imagepng($imageResource);
                $pngContent = ob_get_clean();
                imagedestroy($imageResource);

                if (is_string($pngContent) && $pngContent !== '') {
                    return 'data:image/png;base64,' . base64_encode($pngContent);
                }
            }
        }

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    private function storageInvoiceImageToBrowserUrl(?string $publicStorageUrl): string
    {
        if (!$publicStorageUrl) {
            return '';
        }

        $rawPath = trim((string) $publicStorageUrl);
        if ($rawPath === '') {
            return '';
        }

        if (str_starts_with($rawPath, 'data:image/')) {
            return $rawPath;
        }

        if (preg_match('/^https?:\/\//i', $rawPath) === 1) {
            return $rawPath;
        }

        $resolvedUrl = publicStorageUrl($rawPath);
        if ($resolvedUrl) {
            return $resolvedUrl;
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $rawPath), '/');
        return asset($normalizedPath);
    }

    private function numberToWords($number)
    {
        $ones = [
            "",
            "One",
            "Two",
            "Three",
            "Four",
            "Five",
            "Six",
            "Seven",
            "Eight",
            "Nine",
            "Ten",
            "Eleven",
            "Twelve",
            "Thirteen",
            "Fourteen",
            "Fifteen",
            "Sixteen",
            "Seventeen",
            "Eighteen",
            "Nineteen"
        ];

        $tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];

        $num = (int)$number;
        $words = "In Words: ";

        if ($num >= 100000) {
            $lakhs = (int)($num / 100000);
            $words .= $this->convertHundreds($lakhs, $ones, $tens) . " Lakh ";
            $num %= 100000;
        }

        if ($num >= 1000) {
            $thousands = (int)($num / 1000);
            $words .= $this->convertHundreds($thousands, $ones, $tens) . " Thousand ";
            $num %= 1000;
        }

        if ($num > 0) {
            $words .= $this->convertHundreds($num, $ones, $tens);
        }

        return trim($words) . " Only";
    }

    private function convertHundreds($num, $ones, $tens)
    {
        $words = "";

        if ($num >= 100) {
            $hundreds = (int)($num / 100);
            $words .= $ones[$hundreds] . " Hundred ";
            $num %= 100;
        }

        if ($num >= 20) {
            $ten = (int)($num / 10);
            $words .= $tens[$ten];
            $num %= 10;
            if ($num > 0) {
                $words .= " " . $ones[$num];
            }
        } elseif ($num > 0) {
            $words .= $ones[$num];
        }

        return $words;
    }

    public function downloadOpdInvoice(Request $request)
    {
        $requestData = $request->all();
        $opdPatient = $this->opdService->find($requestData['id']);

        $patient = $this->patientService->find($opdPatient->patient_id ?? '');
        $consultantDoctor = $this->adminService->find($opdPatient->consultant_doctor_id ?? '');

        // dd($patient, $opdPatient, $consultantDoctor, $consultantDoctor?->details?->qualification );

        $module = 'opd';

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', $module)->first();

        // Fallback to a default invoice design if module-specific one is not available
        if (! $invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }

        if (! $invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

        $headerImageBase64 = '';
        $footerImageBase64 = '';

        if ($invoiceDesign && $invoiceDesign->header_photo_path) {
            $relativePath = Str::after($invoiceDesign->header_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $headerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        if ($invoiceDesign && $invoiceDesign->footer_photo_path) {
            $relativePath = Str::after($invoiceDesign->footer_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $footerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        $opdId = prefixed_serial('opd_no_prefix', 'OPDN', $opdPatient->id, 3);
        $barcode = '';

        if ($opdPatient) {
            $barcode = $this->generateBarcode($opdId);
        }


        $age = 'N/A';
        if ($patient->dob) {
            $dob = new \DateTime($patient->dob);
            $now = new \DateTime();
            $ageYears = $now->diff($dob)->y;
            $age = $ageYears . ' Year (As Of Date ' . $now->format('d.m.Y') . ')';
        } elseif ($patient->age) {
            $age = $patient->age . ' Y';
        }

        $base_amount = $opdPatient->standard_charge;
        $discount = $opdPatient->discount ?? 0;
        $tax_percent = $opdPatient->tax ?? 0;
        $paid_amount = $opdPatient->paid_amount;

        $tax_amount = ($base_amount * $tax_percent) / 100;
        $discount_amount = ($base_amount * $discount) / 100;
        $net_amount = $base_amount + $tax_amount - $discount_amount;

        $opdDueCollections = DueCollection::query()
            ->where('payment_method', 'opd')
            ->where(function ($query) use ($opdPatient) {
                $query->where('note', 'like', '%opd_patient_id:' . $opdPatient->id . '%')
                    ->orWhere('note', 'like', '%opd_patient_id: ' . $opdPatient->id . '%');
            })
            ->whereNotNull('collected_at')
            ->orderBy('collected_at')
            ->get(['collected_amount', 'collected_at']);

        $opdDueCollectedTotal = (float) $opdDueCollections->sum('collected_amount');
        $invoiceTimePaidAmount = max(0, (float) $paid_amount - $opdDueCollectedTotal);

        $data = [
            'opd_id' => $opdId,
            'opd_checkin_id' => prefixed_serial('opd_checkup_id_prefix', 'OCID', $opdPatient->id, 2),
            'appointment_date' => \Carbon\Carbon::parse($opdPatient->appointment_date)->format('d-m-Y h:i A'),
            'patient_id' => (int) ($patient->id ?? 0),
            'patient_phone' => (string) ($patient->phone ?? ''),
            'patient_name' => $patient->name ?? 'N/A',
            'age' => $age,
            'gender' => $patient->gender ?? 'N/A',
            'blood_group' => $patient->blood_group ?? '',
            'known_allergies' => $opdPatient->allergies ?? '',
            'address' => $patient->address ?? '',
            'consultant_doctor' => $consultantDoctor->name ?? 'N/A',
            'consultant_qualification' => $consultantDoctor?->details?->qualification ?? '',
            'department' => $opdPatient->consultation_type ?? '',

            // Payment details
            'description' => $opdPatient?->chargeType?->name ?? '',
            'tax_percent' => $opdPatient->tax ?? 0,
            'amount' => $opdPatient->standard_charge ?? 0,
            'net_amount' => $net_amount ?? 0,
            'discount' => $opdPatient->discount ?? 0,
            'discount_amount' => $discount_amount ?? 0,
            'tax_amount' => $tax_amount ?? 0,
            'total_amount' => $opdPatient->amount ?? 0,
            'paid_amount' => $paid_amount ?? 0,
            'invoice_time_paid_amount' => $invoiceTimePaidAmount,
            'opd_due_collections' => $opdDueCollections,
            'opd_due_collected_total' => $opdDueCollectedTotal,
            'balance_amount' => $opdPatient->balance_amount ?? 0,

            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
                'footer_content_position' => in_array(strtolower((string) ($invoiceDesign?->footer_content_position ?? '')), ['above', 'below']) ? strtolower((string) $invoiceDesign?->footer_content_position) : 'above',
                'footer_font_size' => max(6, min(72, (int) ($invoiceDesign?->footer_font_size ?? 14))),
                'header_height' => (int) ($invoiceDesign?->header_height ?? 115),
                'footer_height' => (int) ($invoiceDesign?->footer_height ?? 70),
            'printed_at' => now()->timezone('Asia/Dhaka')->format('d F, Y h:i:s a'),
            'barcode' => $barcode,
            'clinic_address' => 'Daulatur Master Para, Daulatur Kushita Mobile: 01796-302512',
        ];

        // Prefer web-served Bengali font URL for OPD invoice view/pdf rendering.
        $banglaFontUrl = '';
        $banglaFontFile = public_path('fonts/NotoSansBengali-Regular.ttf');
        if (is_file($banglaFontFile)) {
            $banglaFontUrl = asset('fonts/NotoSansBengali-Regular.ttf');
        }

        $data['banglaFontUrl'] = $banglaFontUrl;

        $pdf = Pdf::loadView('frontend.invoice.opd-pdf', $data);

        // Ensure DomPDF parses HTML5 and remote resources so header/footer images and
        // fixed positioning render correctly in generated PDFs.
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'dejavu sans',
            'dpi' => 96,
            'isPhpEnabled' => true,
            'isJavascriptEnabled' => true,
        ]);

        $filename = 'opd_invoice_' . $opdId . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

        public function downloadAppointmentInvoice(Request $request)
    {
        $requestData = $request->all();

        $appointment = $this->appoinmentService->find($requestData['id']);
        $patient = $this->patientService->find($appointment->patient_id ?? '');
        $doctor = $this->adminService->find($appointment->doctor_id ?? '');

        // dd($patient, $doctor);

        $module = 'appointment';

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', $module)->first();

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

        $headerImageBase64 = '';
        $footerImageBase64 = '';

        // Process header image
        if ($invoiceDesign && $invoiceDesign->header_photo_path) {
            $relativePath = Str::after($invoiceDesign->header_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $headerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        // Process footer image
        if ($invoiceDesign && $invoiceDesign->footer_photo_path) {
            $relativePath = Str::after($invoiceDesign->footer_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $footerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        $appointmentId = prefixed_serial('appointment_prefix', 'APPN', $appointment->id, 3);

        $data = [
            'appointment' => $appointment,
            'patient' => $patient,
            'doctor' => $doctor,
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
                'footer_content_position' => in_array(strtolower((string) ($invoiceDesign?->footer_content_position ?? '')), ['above', 'below']) ? strtolower((string) $invoiceDesign?->footer_content_position) : 'above',
                'footer_font_size' => max(6, min(72, (int) ($invoiceDesign?->footer_font_size ?? 14))),
                'header_height' => (int) ($invoiceDesign?->header_height ?? 115),
                'footer_height' => (int) ($invoiceDesign?->footer_height ?? 70),
            'printed_at' => now()->timezone('Asia/Dhaka')->format('d F, Y h:i:s a'),
        ];

        $pdf = Pdf::loadView('frontend.invoice.appointment-pdf', $data);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'dejavu sans',
            'dpi' => 96,
            'isPhpEnabled' => true,
            'isJavascriptEnabled' => true,
        ]);

        $filename = 'appointment_invoice_' . $appointmentId . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

        protected function resolveIpdFinalBillViewOptions(Request $request): array
    {
        $fastOpen = $request->boolean('fast_open');
        $autoPrint = $request->boolean('auto_print') || $fastOpen;

        return [
            'auto_print' => $autoPrint,
            'is_fast_open' => $fastOpen,
        ];
    }

    public function downloadIpdFinalBill(Request $request, IpdDischargeBillingService $ipdDischargeBillingService)
    {
        $ipdPatientId = $request->get('id');
        $viewOptions = $this->resolveIpdFinalBillViewOptions($request);

        if (!$ipdPatientId) {
            abort(422, 'IPD patient id is required.');
        }

        $ipdpatient = IpdPatient::query()
            ->with([
                'patient',
                'doctor.details.designation',
                'bed',
                'billing.billItems',
                'billing.dueCollections',
                'billing.admin',
            ])
            ->findOrFail($ipdPatientId);

        if (empty($ipdpatient->billing_id) || !$ipdpatient->billing) {
            if ($ipdpatient->status !== 'Inactive') {
                abort(422, 'Patient is not discharged yet, so final billing is not available.');
            }

            $billing = $ipdDischargeBillingService->createOrGetForDischarge($ipdpatient, auth('admin')->id());
            $ipdpatient->billing_id = $billing->id;
            $ipdpatient->save();

            $ipdpatient->loadMissing(['billing.billItems', 'billing.dueCollections', 'billing.admin']);
        }

        $billing = $ipdpatient->billing;
        if (!$billing) {
            abort(404, 'Final billing not found for this IPD patient.');
        }

        $billing = $ipdDischargeBillingService->refreshBillingTotals($ipdpatient, auth('admin')->id());
        $ipdpatient->loadMissing(['billing.billItems', 'billing.dueCollections', 'billing.admin']);

        // This view expects the same variables as the normal billing money receipt.
        $module = 'ipd';
        $invoiceDateTime = $billing->created_at
            ? $billing->created_at->format('d-M-Y h:i:s A')
            : now()->format('d-M-Y h:i:s A');

        // Use running-bill lines (same as running-bill print) so that
        // UI-created hospital charges and generated running items appear
        // in the final IPD bill. Normalize lines into objects expected
        // by the invoice view (`item_name`, `quantity`, `total_amount`).
        $running = $ipdDischargeBillingService->getRunningDetails($ipdpatient);
        $runningLines = $running['lines'] ?? [];
        $billItems = collect($runningLines)->map(function ($ln) {
            return (object) [
                'item_name' => $ln['item_name'] ?? ($ln['description'] ?? 'Item'),
                'quantity' => isset($ln['quantity']) ? (int) $ln['quantity'] : 1,
                'total_amount' => isset($ln['net_amount']) ? (float) $ln['net_amount'] : (float) ($ln['total_amount'] ?? 0),
                'category' => isset($ln['category']) ? (string) $ln['category'] : '',
            ];
        });

        $patient = $this->patientService->find($billing->patient_id ?? '');

        $invoiceDesign = InvoiceDesign::where('status', 'Active')
            ->whereIn('module', ['ipd_final', 'billing'])
            ->orderByRaw("CASE WHEN module = 'ipd_final' THEN 0 WHEN module = 'billing' THEN 1 ELSE 2 END")
            ->first();

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

        $headerImageBase64 = '';
        $footerImageBase64 = '';

        if ($invoiceDesign && $invoiceDesign->header_photo_path) {
            $relativePath = Str::after($invoiceDesign->header_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $headerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        if ($invoiceDesign && $invoiceDesign->footer_photo_path) {
            $relativePath = Str::after($invoiceDesign->footer_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $footerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        $barcode = $this->generateBarcode($billing->bill_number ?? ('IPD' . $ipdpatient->id));

        $totals = $this->calculateFilteredTotals($billItems, $billing, $module);
        $totalAmount = (float) ($totals['total_amount'] ?? 0);
        $invoicePaidAmount = (float) ($totals['paid_at_invoice'] ?? $totals['paid'] ?? 0);
        $totalPaidAmount = (float) ($totals['paid'] ?? $invoicePaidAmount);
        $dueAmount = (float) ($totals['due'] ?? max($totalAmount - $totalPaidAmount, 0));

        // Compute return amount for view (prefer billing field, fall back to computed)
        $returnAmount = (float) ($billing->return_amt ?? $billing->return_amount ?? 0);
        if ($returnAmount <= 0) {
            $receiving = (float) ($billing->receiving_amt ?? $billing->paid_amt ?? 0);
            $payable = (float) ($billing->payable_amount ?? $billing->total ?? 0);
            $returnAmount = max(0, $receiving - $payable);
        }

        $data = [
            'billing' => $billing,
            'bill_number' => $billing->bill_number ?? '',
            'invoiceDateTime' => $invoiceDateTime,
            'patient_name' => $patient->name ?? 'N/A',
            'age' => $patient->age ?? 'N/A',
            'contact_no' => $billing->patient_mobile,
            'gender' => $billing->gender,
            'refd_by' => $billing->doctor_name ?? 'N/A',
            'bill_items' => $billItems,
            'total_amount' => round($totalAmount, 2),
            'vat' => 0,
            'net_payable' => round((float) ($totals['net_payable'] ?? $totalAmount), 2),
            'discount' => round((float) ($totals['discount'] ?? 0), 2),
            'discount_type' => $billing['discount_type'] ?? 'flat',
            'extra_flat_discount' => 0,
            'paid' => round($totalPaidAmount, 2),
            'paid_at_invoice' => round($invoicePaidAmount, 2),
            'due' => round($dueAmount, 2),
            'return_amount' => round($returnAmount, 2),
            'show_return_amount' => $returnAmount > 0,
            'delivery_date' => $billing->delivery_date,
            'remarks' => $this->normalizeIpdFinalBillRemark($billing->remarks ?? ''),
            'prepared_by' => 'Toamed Admin',
            'amount_in_words' => $this->numberToWords($totalAmount),
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
            'footer_content_position' => in_array(strtolower((string) ($invoiceDesign?->footer_content_position ?? '')), ['above', 'below']) ? strtolower((string) $invoiceDesign?->footer_content_position) : 'above',
            'showHeaderFooter' => true,
            'show_header_footer' => true,
            'header_title' => 'TOAMED HOSPITAL',
            'header_subtitle' => 'IPD Final Bill',
            'barcode' => $barcode,
            'module' => $module,
            'auto_print' => $viewOptions['auto_print'],
            'is_fast_open' => $viewOptions['is_fast_open'],
            // IPD specific display fields
            'ipd_id' => function_exists('prefixed_serial') ? prefixed_serial('ipd_no_prefix', 'IPDN', $ipdpatient->id, 4) : ('IPD' . str_pad($ipdpatient->id, 4, '0', STR_PAD_LEFT)),
            'printed_at' => $invoiceDateTime,
            'consultant' => $ipdpatient->doctor?->name ?? $billing->doctor_name ?? '',
            'bed' => $ipdpatient->bed?->name ?? '',
            'admission' => !empty($ipdpatient->admission_date) ? \Carbon\Carbon::parse($ipdpatient->admission_date)->format('d-m-Y h:i A') : '',
            'discharge' => !empty($ipdpatient->discharged_at) ? \Carbon\Carbon::parse($ipdpatient->discharged_at)->format('d-m-Y h:i A') : '',
            'case' => $ipdpatient->case ?? '',
        ];

        if ($viewOptions['is_fast_open']) {
            return response(view('frontend.invoice.pdf', $data)->render(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        $pdf = Pdf::loadView('frontend.invoice.pdf', $data);

        $pdf->setPaper('A4', 'portrait');

        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'dejavu sans',
            'dpi' => 96,
            'isPhpEnabled' => true,
            'isJavascriptEnabled' => true,
        ]);

        $filename = 'ipd_final_bill_' . $ipdpatient->id . '_' . ($billing->bill_number ?? 'bill') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

    private function normalizeIpdFinalBillRemark(string $remarks): string
    {
        $remarks = trim($remarks);
        if ($remarks === '') {
            return '';
        }

        $autoPrefixes = [
            'IPD Selected Charges',
            'IPD Discharge Billing (Auto)',
            'IPD Discharge Billing (Auto/Regen)',
            'IPD Admission Items',
            'IPD Items',
            'IPD Manual Charge',
        ];

        foreach ($autoPrefixes as $prefix) {
            if (stripos($remarks, $prefix) === 0) {
                return '';
            }
        }

        return $remarks;
    }

    public function printIpdFinalBill(Request $request, IpdDischargeBillingService $ipdDischargeBillingService)
    {
        $ipdPatientId = $request->get('id');
        $viewOptions = $this->resolveIpdFinalBillViewOptions($request);

        if (!$ipdPatientId) {
            abort(422, 'IPD patient id is required.');
        }

        $ipdpatient = IpdPatient::query()
            ->with([
                'patient',
                'doctor.details.designation',
                'bed',
                'billing.billItems',
                'billing.dueCollections',
                'billing.admin',
            ])
            ->findOrFail($ipdPatientId);

        if (empty($ipdpatient->billing_id) || !$ipdpatient->billing) {
            if ($ipdpatient->status !== 'Inactive') {
                abort(422, 'Patient is not discharged yet, so final billing is not available.');
            }

            $billing = $ipdDischargeBillingService->createOrGetForDischarge($ipdpatient, auth('admin')->id());
            $ipdpatient->billing_id = $billing->id;
            $ipdpatient->save();

            $ipdpatient->loadMissing(['billing.billItems', 'billing.dueCollections', 'billing.admin']);
        }

        $billing = $ipdpatient->billing;
        if (!$billing) {
            abort(404, 'Final billing not found for this IPD patient.');
        }

        $billing = $ipdDischargeBillingService->refreshBillingTotals($ipdpatient, auth('admin')->id());
        $ipdpatient->loadMissing(['billing.billItems', 'billing.dueCollections', 'billing.admin']);

        $module = 'ipd';
        $invoiceDateTime = $billing->created_at
            ? $billing->created_at->format('d-M-Y h:i:s A')
            : now()->format('d-M-Y h:i:s A');

        $running = $ipdDischargeBillingService->getRunningDetails($ipdpatient);
        $runningLines = $running['lines'] ?? [];
        $billItems = collect($runningLines)->map(function ($ln) {
            return (object) [
                'item_name' => $ln['item_name'] ?? ($ln['description'] ?? 'Item'),
                'quantity' => isset($ln['quantity']) ? (int) $ln['quantity'] : 1,
                'total_amount' => isset($ln['net_amount']) ? (float) $ln['net_amount'] : (float) ($ln['total_amount'] ?? 0),
                'category' => isset($ln['category']) ? (string) $ln['category'] : '',
            ];
        });
        $patient = $this->patientService->find($billing->patient_id ?? '');

        $invoiceDesign = InvoiceDesign::where('status', 'Active')
            ->whereIn('module', ['ipd_final', 'billing'])
            ->orderByRaw("CASE WHEN module = 'ipd_final' THEN 0 WHEN module = 'billing' THEN 1 ELSE 2 END")
            ->first();

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

        $headerImageBase64 = '';
        $footerImageBase64 = '';

        if ($invoiceDesign && $invoiceDesign->header_photo_path) {
            $relativePath = Str::after($invoiceDesign->header_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $headerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        if ($invoiceDesign && $invoiceDesign->footer_photo_path) {
            $relativePath = Str::after($invoiceDesign->footer_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $footerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        $barcode = $this->generateBarcode($billing->bill_number ?? ('IPD' . $ipdpatient->id));

        $totals = $this->calculateFilteredTotals($billItems, $billing, $module);
        $totalAmount = (float) ($totals['total_amount'] ?? 0);
        $invoicePaidAmount = (float) ($totals['paid_at_invoice'] ?? $totals['paid'] ?? 0);
        $totalPaidAmount = (float) ($totals['paid'] ?? $invoicePaidAmount);
        $dueAmount = (float) ($totals['due'] ?? max($totalAmount - $totalPaidAmount, 0));

        // Compute return amount for view (prefer billing field, fall back to computed)
        $returnAmount = (float) ($billing->return_amt ?? $billing->return_amount ?? 0);
        if ($returnAmount <= 0) {
            $receiving = (float) ($billing->receiving_amt ?? $billing->paid_amt ?? 0);
            $payable = (float) ($billing->payable_amount ?? $billing->total ?? 0);
            $returnAmount = max(0, $receiving - $payable);
        }

        $data = [
            'billing' => $billing,
            'bill_number' => $billing->bill_number ?? '',
            'invoiceDateTime' => $invoiceDateTime,
            'patient_name' => $patient->name ?? 'N/A',
            'age' => $patient->age ?? 'N/A',
            'contact_no' => $billing->patient_mobile,
            'gender' => $billing->gender,
            'refd_by' => $billing->doctor_name ?? 'N/A',
            'bill_items' => $billItems,
            'total_amount' => round($totalAmount, 2),
            'vat' => 0,
            'net_payable' => round((float) ($totals['net_payable'] ?? $totalAmount), 2),
            'discount' => round((float) ($totals['discount'] ?? 0), 2),
            'discount_type' => $billing->discount_type ?? 'flat',
            'extra_flat_discount' => (float) ($billing->extra_flat_discount ?? 0),
            'paid' => round($totalPaidAmount, 2),
            'paid_at_invoice' => round($invoicePaidAmount, 2),
            'due' => round($dueAmount, 2),
            'return_amount' => round($returnAmount, 2),
            'show_return_amount' => $returnAmount > 0,
            'delivery_date' => $billing->delivery_date,
            'remarks' => $this->normalizeIpdFinalBillRemark($billing->remarks ?? ''),
            'prepared_by' => $billing?->admin?->name ?? '',
            'amount_in_words' => $this->numberToWords((float) ($totals['net_payable'] ?? $totalAmount)),
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
            'footer_content_position' => in_array(strtolower((string) ($invoiceDesign?->footer_content_position ?? '')), ['above', 'below']) ? strtolower((string) $invoiceDesign?->footer_content_position) : 'above',
            'showHeaderFooter' => true,
            'show_header_footer' => true,
            'header_title' => 'TOAMED HOSPITAL',
            'header_subtitle' => 'IPD Final Bill',
            'barcode' => $barcode,
            'module' => $module,
            'auto_print' => $viewOptions['auto_print'],
            'is_fast_open' => $viewOptions['is_fast_open'],
            // IPD specific display fields
            'ipd_id' => function_exists('prefixed_serial') ? prefixed_serial('ipd_no_prefix', 'IPDN', $ipdpatient->id, 4) : ('IPD' . str_pad($ipdpatient->id, 4, '0', STR_PAD_LEFT)),
            'printed_at' => $invoiceDateTime,
            'consultant' => $ipdpatient->doctor?->name ?? $billing->doctor_name ?? '',
            'bed' => $ipdpatient->bed?->name ?? '',
            'admission' => !empty($ipdpatient->admission_date) ? \Carbon\Carbon::parse($ipdpatient->admission_date)->format('d-m-Y h:i A') : '',
            'discharge' => !empty($ipdpatient->discharged_at) ? \Carbon\Carbon::parse($ipdpatient->discharged_at)->format('d-m-Y h:i A') : '',
            'case' => $ipdpatient->case ?? '',
        ];

        return view('frontend.invoice.pdf', $data);
    }

    public function downloadIpdInvoice(Request $request, IpdDischargeBillingService $ipdDischargeBillingService)
    {
        $requestData = $request->all();
        $ipdPatientId = $requestData['id'] ?? null;
        $fastOpen = $request->boolean('fast_open');
        $autoPrint = $request->boolean('auto_print');

        if (!$ipdPatientId) {
            abort(422, 'IPD patient id is required.');
        }

        $ipdpatient = IpdPatient::query()
            ->with(['patient', 'doctor.details.designation', 'bed'])
            ->findOrFail($ipdPatientId);

        // If an itemized Billing exists for this IPD admission, attempt to
        // regenerate billing items, but log running lines and final bill items
        // to help diagnose missing charges (e.g., Pathology/Pharmacy not found).
        if (!empty($ipdpatient->billing_id)) {
            try {
                $runningBefore = $ipdDischargeBillingService->getRunningDetails($ipdpatient);
                $runningLines = $runningBefore['lines'] ?? [];
                $counts = collect($runningLines)->groupBy(function ($ln) {
                    return strtolower(trim($ln['category'] ?? '')) ?: 'ipd';
                })->map->count()->toArray();

                Log::info('InvoiceController::downloadIpdInvoice - running lines before regenerate', ['ipd_id' => $ipdpatient->id, 'counts' => $counts]);
            } catch (\Throwable $e) {
                Log::warning('InvoiceController::downloadIpdInvoice - failed to compute running lines', ['err' => $e->getMessage(), 'ipd_id' => $ipdpatient->id]);
            }

            // Ensure billing items are up-to-date (rebuild from IPD charges)
            try {
                $ipdDischargeBillingService->regenerateForDischarge($ipdpatient, auth('admin')->id());
            } catch (\Throwable $e) {
                Log::warning('InvoiceController::downloadIpdInvoice - failed to regenerate billing for IPD', ['err' => $e->getMessage(), 'ipd_id' => $ipdpatient->id]);
            }

            try {
                $billing = \App\Models\Billing::query()->with(['billItems' => function ($q) { $q->withTrashed(); }])->find($ipdpatient->billing_id);
                $billItems = $billing?->billItems ?? [];

                $billCategories = collect($billItems)->map(function ($it) {
                    return strtolower(trim($it->category ?? '')) ?: 'ipd';
                })->unique()->values()->all();

                $billCounts = collect($billItems)->groupBy(function ($it) {
                    return strtolower(trim($it->category ?? '')) ?: 'ipd';
                })->map->count()->toArray();

                Log::info('InvoiceController::downloadIpdInvoice - billing items after regenerate', ['billing_id' => $ipdpatient->billing_id, 'counts' => $billCounts, 'categories' => $billCategories]);

                // Keep the IPD-specific invoice rendering path so the payment history,
                // due collections, and invoice-time totals render consistently for IPD.
            } catch (\Throwable $e) {
                Log::warning('InvoiceController::downloadIpdInvoice - failed to load billing items after regenerate', ['err' => $e->getMessage(), 'billing_id' => $ipdpatient->billing_id]);
            }

            // Fallthrough: billing exists but only has bed charges (or none) — continue
            // to build the running-lines IPD PDF so other items are visible.
        }

        $payments = Payment::query()
            ->where('ipd_patient_id', $ipdpatient->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get();

        $invoiceTime = null;
        $billingRecord = null;

        // Allow caller to pass an explicit billing_id (e.g. from Billing list)
        $requestedBillingId = $request->input('billing_id');
        if (!empty($requestedBillingId)) {
            $billingRecord = \App\Models\Billing::query()->find($requestedBillingId);
        }

        // Fallback to ipdpatient linked billing if no explicit billing provided
        if (empty($billingRecord) && !empty($ipdpatient->billing_id)) {
            $billingRecord = \App\Models\Billing::query()->find($ipdpatient->billing_id);
        }

        if ($billingRecord?->created_at) {
            $invoiceTime = $billingRecord->created_at;
        } elseif ($ipdpatient->created_at) {
            $invoiceTime = $ipdpatient->created_at;
        } else {
            $invoiceTime = now();
        }

        // Include any due collections associated with the billing (if present)
        $dueCollections = $this->resolveIpdInvoiceDueCollections($ipdpatient);

        $summary = $this->normalizeIpdInvoicePaymentsAndDueCollections($payments, $dueCollections, $invoiceTime);
        $invoiceTimePaidAmount = (float) ($summary['invoice_time_paid_amount'] ?? 0);
        $totalPaid = (float) ($summary['total_paid'] ?? 0);
        $payments = $summary['payments'] ?? collect();
        $due_collections = $summary['due_collections'] ?? collect();
        $module = 'ipd';

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', $module)->first();

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

        $headerImageBase64 = '';
        $footerImageBase64 = '';

        if ($invoiceDesign && $invoiceDesign->header_photo_path) {
            $relativePath = Str::after($invoiceDesign->header_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $headerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        if ($invoiceDesign && $invoiceDesign->footer_photo_path) {
            $relativePath = Str::after($invoiceDesign->footer_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $footerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        $ipdId = prefixed_serial('ipd_no_prefix', 'IPDN', $ipdpatient->id, 4);
        $barcode = $this->generateBarcode($ipdId);

        $running = $ipdDischargeBillingService->getRunningDetails($ipdpatient);
        $runningLines = $running['lines'] ?? [];
        $runningSummary = $running['summary'] ?? [];
        $runningCollection = collect($runningLines);
        $runningLineItems = $runningCollection->map(function ($ln) {
            return [
                'item_name' => $ln['item_name'] ?? ($ln['description'] ?? 'Item'),
                'quantity' => isset($ln['quantity']) ? (float) $ln['quantity'] : 1,
                'unit_price' => isset($ln['unit_price']) ? (float) $ln['unit_price'] : 0,
                'net_amount' => isset($ln['net_amount']) ? (float) $ln['net_amount'] : (float) ($ln['total_amount'] ?? 0),
                'category' => $ln['category'] ?? '',
            ];
        })->values()->all();
        $medicineTotal = $runningCollection->filter(function ($ln) {
            return strtolower(trim($ln['category'] ?? '')) === 'medicine';
        })->sum(function ($ln) {
            return (float) ($ln['net_amount'] ?? $ln['total_amount'] ?? 0);
        });
        $labTotal = $runningCollection->filter(function ($ln) {
            $c = strtolower(trim($ln['category'] ?? ''));
            return in_array($c, ['pathology', 'radiology']);
        })->sum(function ($ln) {
            return (float) ($ln['net_amount'] ?? $ln['total_amount'] ?? 0);
        });

        $billingLineItems = collect();
        if (!empty($ipdpatient->billing_id)) {
            $billingRecordForItems = \App\Models\Billing::query()
                ->with(['billItems' => function ($q) {
                    $q->whereNull('deleted_at');
                }])
                ->find($ipdpatient->billing_id);

            $billingLineItems = collect($billingRecordForItems?->billItems ?? [])
                ->filter(function ($item) {
                    return !empty($item->item_name) || !empty($item->description) || (float) ($item->net_amount ?? $item->total_amount ?? 0) > 0;
                })
                ->map(function ($item) {
                    return [
                        'item_name' => $item->item_name ?? ($item->description ?? 'Item'),
                        'quantity' => isset($item->quantity) ? (float) $item->quantity : 1,
                        'unit_price' => isset($item->unit_price) ? (float) $item->unit_price : 0,
                        'net_amount' => isset($item->net_amount) ? (float) $item->net_amount : (float) ($item->total_amount ?? 0),
                        'category' => $item->category ?? '',
                    ];
                });
        }

        $displayLineItems = $this->resolveIpdInvoiceLineItems($billingLineItems, $runningLineItems);

        $runningTotal = collect($displayLineItems)->sum(function ($ln) {
            return (float) ($ln['net_amount'] ?? $ln['total_amount'] ?? 0);
        });

        $paymentsOnlyTotal = (float) $payments->sum('amount');
        $dueCollectionsTotal = (float) collect($due_collections ?? [])->sum(function ($d) {
            return (float) ($d->collected_amount ?? $d->amount ?? 0);
        });

        $netPayable = $runningTotal;
        $totalPaid = round(max(0, $paymentsOnlyTotal + $dueCollectionsTotal), 2);
        $dueAmount = max(0, round($netPayable - $totalPaid, 2));
        $changeAmount = max(0, round($paymentsOnlyTotal - $runningTotal, 2));

        $discountAmount = 0.0;
        $discountType = 'flat';
        $extraFlatDiscount = 0.0;
        $vatAmount = 0.0;

        if ($billingRecord) {
            $billingTotals = $this->calculateFilteredTotals($runningLineItems, $billingRecord, 'ipd');
            $runningTotal = (float) ($billingTotals['total_amount'] ?? $runningTotal);
            $netPayable = (float) ($billingTotals['net_payable'] ?? $runningTotal);
            $totalPaid = round((float) ($billingTotals['paid'] ?? $totalPaid), 2);
            $dueAmount = max(0, round($netPayable - $totalPaid, 2));
            $changeAmount = max(0, (float) ($billingRecord->change_amt ?? $billingRecord->return_amount ?? $billingRecord->return_amt ?? 0));
            $discountAmount = (float) ($billingTotals['discount'] ?? 0);
            $discountType = $billingRecord->discount_type ?? 'flat';
            $extraFlatDiscount = (float) ($billingRecord->extra_flat_discount ?? 0);
        }

        $preparedBy = auth('admin')->user()?->name ?? 'Toamed Admin';
        $amountInWords = $this->numberToWords((float) $netPayable);

        // Prepare billing display info (prefer explicit billing_id if provided)
        $billingInfo = null;
        if (!empty($billingRecord)) {
            $billingPatient = null;
            if (!empty($billingRecord->patient_id)) {
                $billingPatient = \App\Models\Patient::query()->find($billingRecord->patient_id);
            }

            $ageDisplay = 'N/A';
            try {
                if (!empty($billingPatient?->age)) {
                    $ageDisplay = (string) $billingPatient->age . ' years';
                } elseif (!empty($billingPatient?->dob)) {
                    $ageDisplay = \Carbon\Carbon::parse($billingPatient->dob)->age . ' years';
                }
            } catch (\Throwable $_e) {
                $ageDisplay = 'N/A';
            }

            $billingInfo = [
                'bill_no' => $billingRecord->bill_number ?? '',
                'bill_date_time' => $billingRecord->created_at ? $billingRecord->created_at->format('d-M-Y h:i:s A') : ($invoiceTime?->format('d-M-Y h:i:s A') ?? now()->format('d-M-Y h:i:s A')),
                'patient_name' => $billingPatient?->name ?? $billingRecord->patient_name ?? ($ipdpatient->patient?->name ?? ''),
                'age_display' => $ageDisplay,
                'gender' => $billingPatient?->gender ?? $billingRecord->gender ?? ($ipdpatient->patient?->gender ?? 'N/A'),
                'phone' => $billingPatient?->phone ?? $billingRecord->patient_mobile ?? ($ipdpatient->patient?->phone ?? ''),
                'refd_by' => $billingRecord->doctor_name ?? '',
            ];
        }

        $data = [
            'ipd_id' => $ipdId,
            'ipdpatient' => $ipdpatient,
            'patient' => $ipdpatient->patient,
            // Patient summary fields (mirror billing invoice fields)
            'patient_name' => $ipdpatient->patient?->name ?? 'N/A',
            'name' => $ipdpatient->patient?->name ?? 'N/A',
            'age' => $ipdpatient->patient?->age ?? 'N/A',
            'age_display' => (function () use ($ipdpatient) {
                $raw = $ipdpatient->patient?->age ?? '';
                if ($raw === null || $raw === '') return 'N/A';
                $rawStr = (string) $raw;
                // If already contains 'year' or 'yrs', return as-is
                if (preg_match('/\b(years?|yrs?)\b/i', $rawStr)) {
                    return $rawStr;
                }
                // If numeric, append ' years'
                if (is_numeric($rawStr)) {
                    return trim($rawStr) . ' years';
                }
                return $rawStr;
            })(),
            'contact_no' => $ipdpatient->patient?->mobile ?? $ipdpatient->patient?->phone ?? ($ipdpatient->patient_mobile ?? '00'),
            'gender' => $ipdpatient->patient?->gender ?? ($ipdpatient->gender ?? ''),
            'refd_by' => $ipdpatient->doctor?->name ?? '',
            'doctor' => $ipdpatient->doctor,
            'bed' => $ipdpatient->bed,
            'payments' => $payments,
            'due_collections' => $due_collections ?? collect(),
            'total_paid' => round($totalPaid, 2),
            'invoice_time_paid_amount' => round($invoiceTimePaidAmount, 2),
            'due_amount' => round($dueAmount, 2),
            'return_amount' => round($changeAmount, 2),
            'total_amount' => round($runningTotal, 2),
            'net_payable' => round($netPayable, 2),
            'amount_in_words' => $amountInWords,
            'prepared_by' => $preparedBy,
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
            'footer_content_position' => in_array(strtolower((string) ($invoiceDesign?->footer_content_position ?? '')), ['above', 'below']) ? strtolower((string) $invoiceDesign?->footer_content_position) : 'above',
            'barcode' => $barcode,
            // Billing display (preserve billing list values when provided)
            'bill_no' => $billingInfo['bill_no'] ?? ($billingRecord?->bill_number ?? ''),
            'bill_date_time' => $billingInfo['bill_date_time'] ?? ($billingRecord?->created_at ? $billingRecord->created_at->format('d-M-Y h:i:s A') : ($invoiceTime?->format('d-M-Y h:i:s A') ?? now()->format('d-M-Y h:i:s A'))),
            'billing_info' => $billingInfo,
            'printed_at' => now()->timezone('Asia/Dhaka')->format('d-M-Y h:i:s A'),
            'medicineTotal' => (float) $medicineTotal,
            'labTotal' => (float) $labTotal,
            'runningLines' => $displayLineItems,
            'bill_items' => $displayLineItems,
            'header_height' => (int) ($invoiceDesign->header_height ?? 115),
            'footer_height' => (int) ($invoiceDesign->footer_height ?? 70),
            'showHeaderFooter' => true,
            'auto_print' => $autoPrint,
            // Billing identifiers (if billing exists for this IPD admission)
            'bill_no' => $billingRecord?->bill_number ?? ($billing?->bill_number ?? ''),
            'bill_date_time' => $billingRecord?->created_at
                ? $billingRecord->created_at->format('d-M-Y h:i:s A')
                : ($invoiceTime?->format('d-M-Y h:i:s A') ?? now()->timezone('Asia/Dhaka')->format('d-M-Y h:i:s A')),
            // Additional IPD display fields
            'admission' => !empty($ipdpatient->admission_date) ? \Carbon\Carbon::parse($ipdpatient->admission_date)->format('d-m-Y h:i A') : 'N/A',
            'discharge' => !empty($ipdpatient->discharged_at) ? \Carbon\Carbon::parse($ipdpatient->discharged_at)->format('d-m-Y h:i A') : 'N/A',
            'consultant' => $ipdpatient->doctor?->name ?? '',
            'case' => $ipdpatient->case ?? '',
        ];

        // Attach billing identifiers to the data so invoice shows Bill No and Date & Time
        $data['bill_no'] = $billingRecord?->bill_number ?? '';
        $data['bill_date_time'] = $billingRecord?->created_at
            ? $billingRecord->created_at->format('d-M-Y h:i:s A')
            : ($invoiceTime?->format('d-M-Y h:i:s A') ?? now()->timezone('Asia/Dhaka')->format('d-M-Y h:i:s A'));

        if ($fastOpen) {
            return response(view('frontend.invoice.ipd-pdf', $data)->render(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        $pdf = Pdf::loadView('frontend.invoice.ipd-pdf', $data)
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'dejavu sans',
                'dpi' => 96,
            ]);

        $filename = 'ipd_invoice_' . $ipdId . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

    public function printIpdInvoice(Request $request, IpdDischargeBillingService $ipdDischargeBillingService)
    {
        $requestData = $request->all();
        $ipdPatientId = $requestData['id'] ?? null;
        $autoPrint = $request->boolean('auto_print');

        if (!$ipdPatientId) {
            abort(422, 'IPD patient id is required.');
        }

        $ipdpatient = IpdPatient::query()
            ->with(['patient', 'doctor.details.designation', 'bed'])
            ->findOrFail($ipdPatientId);

        $payments = Payment::query()
            ->where('ipd_patient_id', $ipdpatient->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get();

        $invoiceTime = null;
        $billingRecord = null;
        if (!empty($ipdpatient->billing_id)) {
            $billingRecord = \App\Models\Billing::query()->find($ipdpatient->billing_id);
        }
        if ($billingRecord?->created_at) {
            $invoiceTime = $billingRecord->created_at;
        } elseif ($ipdpatient->created_at) {
            $invoiceTime = $ipdpatient->created_at;
        } else {
            $invoiceTime = now();
        }

        $dueCollections = $this->resolveIpdInvoiceDueCollections($ipdpatient);

        $summary = $this->normalizeIpdInvoicePaymentsAndDueCollections($payments, $dueCollections, $invoiceTime);
        $invoiceTimePaidAmount = (float) ($summary['invoice_time_paid_amount'] ?? 0);
        $totalPaid = (float) ($summary['total_paid'] ?? 0);
        $payments = $summary['payments'] ?? collect();
        $due_collections = $summary['due_collections'] ?? collect();

        $module = 'ipd';

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', $module)->first();

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

        $headerImageBase64 = '';
        $footerImageBase64 = '';

        if ($invoiceDesign && $invoiceDesign->header_photo_path) {
            $relativePath = Str::after($invoiceDesign->header_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $headerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        if ($invoiceDesign && $invoiceDesign->footer_photo_path) {
            $relativePath = Str::after($invoiceDesign->footer_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $footerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        $ipdId = prefixed_serial('ipd_no_prefix', 'IPDN', $ipdpatient->id, 4);
        $barcode = $this->generateBarcode($ipdId);

        $running = $ipdDischargeBillingService->getRunningDetails($ipdpatient);
        $runningLines = $running['lines'] ?? [];
        $runningSummary = $running['summary'] ?? [];
        $runningCollection = collect($runningLines);
        $runningLineItems = $runningCollection->map(function ($ln) {
            return [
                'item_name' => $ln['item_name'] ?? ($ln['description'] ?? 'Item'),
                'quantity' => isset($ln['quantity']) ? (float) $ln['quantity'] : 1,
                'unit_price' => isset($ln['unit_price']) ? (float) $ln['unit_price'] : 0,
                'net_amount' => isset($ln['net_amount']) ? (float) $ln['net_amount'] : (float) ($ln['total_amount'] ?? 0),
                'category' => $ln['category'] ?? '',
            ];
        })->values()->all();
        $medicineTotal = $runningCollection->filter(function ($ln) {
            return strtolower(trim($ln['category'] ?? '')) === 'medicine';
        })->sum(function ($ln) {
            return (float) ($ln['net_amount'] ?? $ln['total_amount'] ?? 0);
        });
        $labTotal = $runningCollection->filter(function ($ln) {
            $c = strtolower(trim($ln['category'] ?? ''));
            return in_array($c, ['pathology', 'radiology']);
        })->sum(function ($ln) {
            return (float) ($ln['net_amount'] ?? $ln['total_amount'] ?? 0);
        });

        $billingLineItems = collect();
        if (!empty($ipdpatient->billing_id)) {
            $billingRecordForItems = \App\Models\Billing::query()
                ->with(['billItems' => function ($q) {
                    $q->whereNull('deleted_at');
                }])
                ->find($ipdpatient->billing_id);

            $billingLineItems = collect($billingRecordForItems?->billItems ?? [])
                ->filter(function ($item) {
                    return !empty($item->item_name) || !empty($item->description) || (float) ($item->net_amount ?? $item->total_amount ?? 0) > 0;
                })
                ->map(function ($item) {
                    return [
                        'item_name' => $item->item_name ?? ($item->description ?? 'Item'),
                        'quantity' => isset($item->quantity) ? (float) $item->quantity : 1,
                        'unit_price' => isset($item->unit_price) ? (float) $item->unit_price : 0,
                        'net_amount' => isset($item->net_amount) ? (float) $item->net_amount : (float) ($item->total_amount ?? 0),
                        'category' => $item->category ?? '',
                    ];
                });
        }

        $displayLineItems = !empty($runningLineItems)
            ? $runningLineItems
            : ($billingLineItems->isNotEmpty() ? $billingLineItems->values()->all() : []);

        $runningTotal = collect($displayLineItems)->sum(function ($ln) {
            return (float) ($ln['net_amount'] ?? $ln['total_amount'] ?? 0);
        });

        $paymentsOnlyTotal = (float) $payments->sum('amount');
        $dueCollectionsTotal = (float) collect($due_collections ?? [])->sum(function ($d) {
            return (float) ($d->collected_amount ?? $d->amount ?? 0);
        });

        $billingPaid = $billingRecord ? (float) ($billingRecord->paid_amt ?? 0) : 0;
        if ($billingPaid <= 0) {
            $billingPaid = max(0, $paymentsOnlyTotal + $dueCollectionsTotal);
        }

        $totalPaid = round(max(0, $billingPaid), 2);
        $discountAmount = 0.0;
        $discountType = 'flat';
        $extraFlatDiscount = 0.0;
        $vatAmount = 0.0;

        if ($billingRecord) {
            $billingTotals = $this->calculateFilteredTotals($runningLineItems, $billingRecord, 'ipd');
            $runningTotal = (float) ($billingTotals['total_amount'] ?? $runningTotal);
            $netPayable = (float) ($billingTotals['net_payable'] ?? $runningTotal);
            $totalPaid = round((float) ($billingTotals['paid'] ?? $totalPaid), 2);
            $dueAmount = round((float) ($billingTotals['due'] ?? max(0, $netPayable - $totalPaid)), 2);
            $changeAmount = max(0, (float) ($billingRecord->change_amt ?? $billingRecord->return_amount ?? $billingRecord->return_amt ?? 0));
            $discountAmount = (float) ($billingTotals['discount'] ?? 0);
            $discountType = $billingRecord->discount_type ?? 'flat';
            $extraFlatDiscount = (float) ($billingRecord->extra_flat_discount ?? 0);
        } else {
            $netPayable = $runningTotal;
            $dueAmount = max(0, round($runningTotal - $totalPaid, 2));
            $changeAmount = max(0, round($paymentsOnlyTotal - $runningTotal, 2));
        }

        $preparedBy = auth('admin')->user()?->name ?? 'Toamed Admin';
        $amountInWords = $this->numberToWords((float) $netPayable);

        $data = [
            'ipd_id' => $ipdId,
            'ipdpatient' => $ipdpatient,
            'patient' => $ipdpatient->patient,
            'doctor' => $ipdpatient->doctor,
            'bed' => $ipdpatient->bed,
            'payments' => $payments,
            'due_collections' => $due_collections ?? collect(),
            'total_paid' => round($totalPaid, 2),
            'invoice_time_paid_amount' => round($invoiceTimePaidAmount, 2),
            'due_amount' => round($dueAmount, 2),
            'return_amount' => round($changeAmount, 2),
            'total_amount' => round($runningTotal, 2),
            'vat' => round($vatAmount, 2),
            'discount' => round($discountAmount, 2),
            'discount_type' => $discountType,
            'extra_flat_discount' => round($extraFlatDiscount, 2),
            'net_payable' => round($netPayable, 2),
            'amount_in_words' => $amountInWords,
            'prepared_by' => $preparedBy,
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
            'footer_content_position' => in_array(strtolower((string) ($invoiceDesign?->footer_content_position ?? '')), ['above', 'below']) ? strtolower((string) $invoiceDesign?->footer_content_position) : 'above',
            'barcode' => $barcode,
            'printed_at' => now()->timezone('Asia/Dhaka')->format('d-M-Y h:i:s A'),
            'medicineTotal' => (float) $medicineTotal,
            'labTotal' => (float) $labTotal,
            'runningLines' => $displayLineItems,
            'bill_items' => $displayLineItems,
            'header_height' => (int) ($invoiceDesign->header_height ?? 115),
            'footer_height' => (int) ($invoiceDesign->footer_height ?? 70),
            'showHeaderFooter' => true,
            'auto_print' => $autoPrint,
        ];

        return view('frontend.invoice.ipd-pdf', $data);
    }
}
