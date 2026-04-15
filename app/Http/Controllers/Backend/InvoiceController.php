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
                'billItems',
                'dueCollections',
                'payments',
                'admin',
            ])
            ->findOrFail($validated['id']);

        $invoiceDateTime = $billing->created_at
            ? $billing->created_at->format('d-M-Y h:i:s A')
            : now()->format('d-M-Y h:i:s A');

        $billItems = $this->filterBillItemsByModule($billing->billItems ?? collect(), $module);

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
        $productReturnAmount = (float) ProductReturn::query()
            ->where('billing_id', $billing->id)
            ->whereIn('status', ['approved', 'processed'])
            ->sum('total_amount');
        $cashReturnAmount = max(0, (float) ($billing->receiving_amt ?? 0) - (float) ($billing->invoice_amount ?? 0));
        $returnAmount = $productReturnAmount + $cashReturnAmount;
        $adjustedDue = max(0, (float) $totals['due'] - $returnAmount);

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
            'vat' => 0,
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
            'header_height' => $designAssets['header_height'],
            'footer_height' => $designAssets['footer_height'],
            'barcode' => $barcode,
            'module' => $module,
        ];

        $safeBillNo = Str::of((string) ($billing->bill_number ?? $billing->id))
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
            ->toString();
        $safeModule = Str::of((string) $module)
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
            ->toString();
        $filename = 'invoice_' . $safeBillNo . '_' . $safeModule . '.pdf';

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
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

    private function buildInvoicePdf(array $data, string $defaultFont)
    {
        $pdf = Pdf::loadView('frontend.invoice.pdf', $data);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
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
            'isRemoteEnabled' => false,
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
            $total = (float) ($billing->total ?? 0);

            if ($billing->discount_type === 'percentage') {
                $discountPercent = (float) ($billing->discount ?? 0);
                $discountAmount = max(0, ($total * $discountPercent) / 100);
            } else {
                $discountPercent = null;
                $discountAmount = max(0, (float) ($billing->discount ?? 0));
            }

            $extraDiscount = max(0, (float) ($billing->extra_flat_discount ?? 0));

            $netPayable = max(0, $total - $discountAmount - $extraDiscount);

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

            $paymentsSum = (float) \App\Models\Payment::where('billing_id', $billing->id)->sum('amount');
            $dueCollected = (float) \App\Models\DueCollection::where('billing_id', $billing->id)->sum('collected_amount');

            $totalPaid = max(0, $paymentsSum + $dueCollected);
            $computedDue = max(0, $netPayable - $totalPaid);

            return [
                'total_amount' => round($total, 2),
                // For templates: if percentage discount, return percentage value; otherwise return amount
                'discount' => $billing->discount_type === 'percentage' ? round($discountPercent, 2) : round($discountAmount, 2),
                'net_payable' => round($netPayable, 2),
                'paid_at_invoice' => round($paidAtInvoice, 2),
                'paid' => round($totalPaid, 2),
                'due' => round($computedDue, 2)
            ];
        }

        // Module-filtered totals (pathology/pharmacy/radiology)
        $itemTotal = $filteredItems->sum('total_amount');
        $itemDiscount = $filteredItems->sum('discount');

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

        $proportionalPaid = 0;
        if ($billing->payable_amount > 0 && $billing->paid_amt > 0) {
            $proportionalPaid = ($netPayable / $billing->payable_amount) * $billing->paid_amt;
        }

        $due = $netPayable - $proportionalPaid;

        return [
            'total_amount' => round($itemTotal, 2),
            'discount' => $billing->discount_type === 'percentage' ? round($discountPercent ?? 0, 2) : round($itemDiscount + $proportionalDiscountAmount, 2),
            'net_payable' => round($netPayable, 2),
            'paid' => round($proportionalPaid, 2),
            'due' => max(0, round($due, 2))
        ];
    }

    private function generateBarcode($billNumber)
    {
        $dns1d = new DNS1D();
        $barcode = $dns1d->getBarcodePNG($billNumber, 'C128', 3, 60);
        return 'data:image/png;base64,' . $barcode;
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

    private function getInvoiceDesignAssets(?InvoiceDesign $invoiceDesign): array
    {
        return [
            'header_image' => $this->storageInvoiceImageToDataUri($invoiceDesign?->header_photo_path),
            'footer_image' => $this->storageInvoiceImageToDataUri($invoiceDesign?->footer_photo_path),
            'footer_content' => $this->sanitizeHtmlForPdf((string) ($invoiceDesign?->footer_content ?? '')),
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

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 24,
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
        {$footerImageBlock}
        {$footerContentBlock}
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
            'header_height' => (int) ($invoiceDesign?->header_height ?? 115),
            'footer_height' => (int) ($invoiceDesign?->footer_height ?? 70),
            'printed_at' => now()->timezone('Asia/Dhaka')->format('d F, Y h:i:s a'),
            'barcode' => $barcode,
            'clinic_address' => 'Daulatur Master Para, Daulatur Kushita Mobile: 01796-302512',
        ];

        $pdf = Pdf::loadView('frontend.invoice.opd-pdf', $data);

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
            'header_height' => (int) ($invoiceDesign?->header_height ?? 115),
            'footer_height' => (int) ($invoiceDesign?->footer_height ?? 70),
            'printed_at' => now()->timezone('Asia/Dhaka')->format('d F, Y h:i:s a'),
        ];

        $pdf = Pdf::loadView('frontend.invoice.appointment-pdf', $data);

        $filename = 'appointment_invoice_' . $appointmentId . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

        public function downloadIpdFinalBill(Request $request, IpdDischargeBillingService $ipdDischargeBillingService)
    {
        $ipdPatientId = $request->get('id');

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
        $module = 'billing';
        $invoiceDateTime = $billing->created_at
            ? $billing->created_at->format('d-M-Y h:i:s A')
            : now()->format('d-M-Y h:i:s A');

        $billItems = $billing->billItems ?? collect();
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
            'total_amount' => $totals['total_amount'],
            'vat' => 0,
            'net_payable' => $totals['net_payable'],
            'discount' => $totals['discount'],
            'discount_type' => $billing['discount_type'],
            'extra_flat_discount' => $billing['extra_flat_discount'],
            'paid' => $totals['paid'],
            'due' => $totals['due'],
            'delivery_date' => $billing->delivery_date,
            'remarks' => trim((string) ($billing->remarks ?? '') . ' | IPD#' . $ipdpatient->id),
            'prepared_by' => $billing?->admin?->name ?? '',
            'amount_in_words' => $this->numberToWords($totals['net_payable']),
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
            'barcode' => $barcode,
            'module' => $module,
        ];

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

    public function printIpdFinalBill(Request $request, IpdDischargeBillingService $ipdDischargeBillingService)
    {
        $ipdPatientId = $request->get('id');

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

        $module = 'billing';
        $invoiceDateTime = $billing->created_at
            ? $billing->created_at->format('d-M-Y h:i:s A')
            : now()->format('d-M-Y h:i:s A');

        $billItems = $billing->billItems ?? collect();
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
            'total_amount' => $totals['total_amount'],
            'vat' => 0,
            'net_payable' => $totals['net_payable'],
            'discount' => $totals['discount'],
            'discount_type' => $billing['discount_type'],
            'extra_flat_discount' => $billing['extra_flat_discount'],
            'paid' => $totals['paid'],
            'due' => $totals['due'],
            'delivery_date' => $billing->delivery_date,
            'remarks' => trim((string) ($billing->remarks ?? '') . ' | IPD#' . $ipdpatient->id),
            'prepared_by' => $billing?->admin?->name ?? '',
            'amount_in_words' => $this->numberToWords($totals['net_payable']),
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
            'barcode' => $barcode,
            'module' => $module,
        ];

        return view('frontend.invoice.pdf', $data);
    }

    public function downloadIpdInvoice(Request $request)
    {
        $requestData = $request->all();
        $ipdPatientId = $requestData['id'] ?? null;

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

        $totalPaid = (float) $payments->sum('amount');

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

        $data = [
            'ipd_id' => $ipdId,
            'ipdpatient' => $ipdpatient,
            'patient' => $ipdpatient->patient,
            'doctor' => $ipdpatient->doctor,
            'bed' => $ipdpatient->bed,
            'payments' => $payments,
            'total_paid' => $totalPaid,
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
            'barcode' => $barcode,
            'printed_at' => now()->timezone('Asia/Dhaka')->format('d-M-Y h:i:s A'),
        ];

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

    public function printIpdInvoice(Request $request)
    {
        $requestData = $request->all();
        $ipdPatientId = $requestData['id'] ?? null;

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

        $totalPaid = (float) $payments->sum('amount');

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

        $data = [
            'ipd_id' => $ipdId,
            'ipdpatient' => $ipdpatient,
            'patient' => $ipdpatient->patient,
            'doctor' => $ipdpatient->doctor,
            'bed' => $ipdpatient->bed,
            'payments' => $payments,
            'total_paid' => $totalPaid,
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
            'barcode' => $barcode,
            'printed_at' => now()->timezone('Asia/Dhaka')->format('d-M-Y h:i:s A'),
        ];

        return view('frontend.invoice.ipd-pdf', $data);
    }
}

