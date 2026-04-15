<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BillItem;
use App\Models\Billing;
use App\Models\InvoiceDesign;
use App\Models\PathologyTestParameter;
use App\Models\RadiologyTest;
use App\Models\Test;
use App\Models\WebSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ReportingController extends Controller
{
    /**
     * Shared upload rules for report attachments.
     */
    private function reportFileValidationRules(): array
    {
        return [
            'nullable',
            'file',
            'max:5120',
            // Keep extension check strict; allow legacy DOC/DOCX and common image/report formats.
            'extensions:pdf,jpg,jpeg,png,webp,doc,docx',
            // Some legacy DOC files are detected as octet-stream by browsers/servers.
            'mimetypes:application/pdf,image/jpeg,image/png,image/webp,application/msword,application/vnd.ms-word,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/octet-stream',
        ];
    }

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:reporting');
    }

    public function index(Request $request)
    {
        $allowedCategories = $this->resolveDepartmentCategories();
        $billNumber = trim((string) $request->input('bill_number', ''));
        $includeReported = $request->boolean('include_reported');

        $datas = Billing::query()
            ->where('status', 'Active')
            ->when($billNumber !== '', function ($query) use ($billNumber) {
                $query->where('bill_number', 'like', '%' . $billNumber . '%');
            })
            ->whereHas('billItems', function ($query) use ($includeReported, $allowedCategories) {
                $query->whereIn('category', $allowedCategories)
                    ->whereNotNull('sample_collected_at');

                if (!$includeReported) {
                    $query->whereNull('reported_at');
                }
            })
            ->with([
                'patient',
                'billItems' => function ($query) use ($includeReported, $allowedCategories) {
                    $query->whereIn('category', $allowedCategories)
                        ->whereNotNull('sample_collected_at')
                        ->with('collectedBy');

                    if (!$includeReported) {
                        $query->whereNull('reported_at');
                    }
                },
            ])
            ->orderByDesc('id')
            ->paginate($request->input('numOfData', 10))
            ->withQueryString();

        return Inertia::render('Backend/Reporting/Index', [
            'pageTitle' => 'Reporting',
            'datas' => $datas,
            'filters' => [
                'bill_number' => $billNumber,
                'include_reported' => $includeReported,
            ],
        ]);
    }

    public function search(Request $request)
    {
        $allowedCategories = $this->resolveDepartmentCategories();
        $billNumber = trim((string) $request->input('bill_number', ''));

        if ($billNumber === '') {
            return back()->with('warning', 'Please enter a bill number.');
        }

        $query = Billing::query()
            ->where('status', 'Active')
            ->whereHas('billItems', function ($query) use ($allowedCategories) {
                $query->whereIn('category', $allowedCategories)
                    ->whereNotNull('sample_collected_at')
                    ->whereNull('reported_at');
            });

        $billing = (clone $query)->where('bill_number', $billNumber)->first();

        if (!$billing) {
            $billing = (clone $query)->where('bill_number', 'like', '%' . $billNumber . '%')->first();
        }

        if (!$billing) {
            return back()->with('warning', 'No pending report found for this bill number.');
        }

        return redirect()->route('backend.reporting.edit', $billing->id);
    }

    public function edit(Billing $billing)
    {
        $allowedCategories = $this->resolveDepartmentCategories();
        $includeReported = request()->boolean('include_reported');

        $billing->load([
            'patient',
            'billItems' => function ($query) use ($includeReported, $allowedCategories) {
                $query->whereIn('category', $allowedCategories)
                    ->whereNotNull('sample_collected_at');

                if (!$includeReported) {
                    $query->whereNull('reported_at');
                }
            },
        ]);

        $pathologyItemIds = $billing->billItems
            ->where('category', 'Pathology')
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values();

        $normalRangeMap = $this->buildNormalRangeMap($pathologyItemIds->all());

        $items = $billing->billItems->map(function ($item) use ($normalRangeMap) {
            $item->report_file_url = $item->report_file
                ? route('backend.reporting.item.file', $item->id)
                : null;

            $defaultRange = $item->category === 'Pathology'
                ? ($normalRangeMap[$item->item_id] ?? null)
                : null;

            // Fallback AI-style range suggestion by test name/category when a predefined range is absent.
            if (empty($defaultRange)) {
                $defaultRange = $this->suggestNormalRangeByTestName($item);
            }

            $item->default_report_range = $defaultRange;

            return $item;
        });

        if ($items->isEmpty()) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'No pending tests for reporting.');
        }

        return Inertia::render('Backend/Reporting/Form', [
            'pageTitle' => 'Report Entry',
            'billing' => $billing,
            'billItems' => $items,
        ]);
    }

    public function viewFile(BillItem $billItem)
    {
        $allowedCategories = $this->resolveDepartmentCategories();

        if (!in_array($billItem->category, $allowedCategories, true)) {
            abort(403);
        }

        $relativePath = trim((string) $billItem->report_file);
        if ($relativePath === '') {
            abort(404);
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!Storage::disk('public')->exists($normalizedPath)) {
            abort(404);
        }

        $fullPath = storage_path('app/public/' . $normalizedPath);
        return response()->file($fullPath);
    }

    public function importStoredFileText(BillItem $billItem)
    {
        $allowedCategories = $this->resolveDepartmentCategories();

        if (!in_array($billItem->category, $allowedCategories, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid report item.',
            ], 403);
        }

        $relativePath = trim((string) $billItem->report_file);
        if ($relativePath === '') {
            return response()->json([
                'ok' => false,
                'message' => 'No file attached for this item.',
            ], 404);
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!Storage::disk('public')->exists($normalizedPath)) {
            return response()->json([
                'ok' => false,
                'message' => 'Attached file not found.',
            ], 404);
        }

        $fullPath = storage_path('app/public/' . $normalizedPath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        [$text, $meta] = $this->extractReportFileText($fullPath, $extension);
        $clean = $this->normalizeImportedText($text);

        if ($clean === '') {
            return response()->json([
                'ok' => false,
                'message' => $meta['message'] ?? 'Text could not be extracted from this file.',
                'source' => $meta['source'] ?? $extension,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'text' => $clean,
            'source' => $meta['source'] ?? $extension,
            'warning' => $meta['warning'] ?? null,
        ]);
    }

    private function extractReportFileText(string $fullPath, string $extension): array
    {
        if (in_array($extension, ['txt', 'md', 'csv', 'log'], true)) {
            return [
                (string) @file_get_contents($fullPath),
                ['source' => $extension],
            ];
        }

        if (in_array($extension, ['html', 'htm'], true)) {
            $html = (string) @file_get_contents($fullPath);
            return [
                html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ['source' => 'html'],
            ];
        }

        if ($extension === 'docx') {
            return $this->extractDocxText($fullPath);
        }

        if ($extension === 'doc') {
            return $this->extractDocTextWithConverters($fullPath);
        }

        if ($extension === 'pdf') {
            return $this->extractPdfTextWithConverter($fullPath);
        }

        return [
            '',
            ['source' => $extension, 'message' => 'Unsupported file type for text import.'],
        ];
    }

    private function extractDocxText(string $fullPath): array
    {
        if (!class_exists('ZipArchive')) {
            return ['', ['source' => 'docx', 'message' => 'ZIP extension is not available on the server.']];
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($fullPath);
        if ($opened !== true) {
            return ['', ['source' => 'docx', 'message' => 'DOCX file could not be opened.']];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!is_string($xml) || trim($xml) === '') {
            return ['', ['source' => 'docx', 'message' => 'DOCX content is empty.']];
        }

        $text = preg_replace('/<w:p[^>]*>/', "\n", $xml);
        $text = preg_replace('/<[^>]+>/', '', (string) $text);
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return [(string) $text, ['source' => 'docx']];
    }

    private function extractDocTextWithConverters(string $fullPath): array
    {
        $attempts = [
            ['antiword', 'antiword :file'],
            ['catdoc', 'catdoc :file'],
        ];

        foreach ($attempts as [$tool, $template]) {
            if (!$this->isShellToolAvailable($tool)) {
                continue;
            }

            $command = str_replace(':file', escapeshellarg($fullPath), $template);
            $output = $this->runShellCommand($command);
            if ($output !== '') {
                return [$output, ['source' => $tool]];
            }
        }

        if ($this->isShellToolAvailable('soffice')) {
            $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'hms_doc_import_' . Str::random(10);
            @mkdir($tmpDir, 0777, true);

            $command = 'soffice --headless --convert-to txt:Text --outdir '
                . escapeshellarg($tmpDir) . ' ' . escapeshellarg($fullPath);

            $this->runShellCommand($command);

            $target = $tmpDir . DIRECTORY_SEPARATOR . pathinfo($fullPath, PATHINFO_FILENAME) . '.txt';
            if (file_exists($target)) {
                $text = (string) @file_get_contents($target);
                @unlink($target);
                @rmdir($tmpDir);

                if (trim($text) !== '') {
                    return [$text, ['source' => 'soffice']];
                }
            }

            @rmdir($tmpDir);
        }

        return [
            '',
            [
                'source' => 'doc',
                'message' => 'Server DOC converter was not found. Install antiword/catdoc/LibreOffice or use DOCX.',
            ],
        ];
    }

    private function extractPdfTextWithConverter(string $fullPath): array
    {
        if (!$this->isShellToolAvailable('pdftotext')) {
            return [
                '',
                ['source' => 'pdf', 'message' => 'PDF text converter is not installed on the server.'],
            ];
        }

        $command = 'pdftotext -layout ' . escapeshellarg($fullPath) . ' -';
        $output = $this->runShellCommand($command);

        if ($output === '') {
            return ['', ['source' => 'pdf', 'message' => 'No readable text found in PDF.']];
        }

        return [$output, ['source' => 'pdftotext']];
    }

    private function isShellToolAvailable(string $tool): bool
    {
        $command = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
            ? 'where ' . escapeshellarg($tool)
            : 'command -v ' . escapeshellarg($tool);

        $output = $this->runShellCommand($command, true);
        return $output !== '';
    }

    private function runShellCommand(string $command, bool $suppressErrors = false): string
    {
        $redirect = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
            ? ($suppressErrors ? ' 2>NUL' : ' 2>&1')
            : ($suppressErrors ? ' 2>/dev/null' : ' 2>&1');

        $output = @shell_exec($command . $redirect);
        return trim((string) $output);
    }

    private function normalizeImportedText(string $text): string
    {
        $normalized = preg_replace('/\x{FEFF}/u', '', (string) $text);
        $normalized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', (string) $normalized);
        $normalized = preg_replace('/[^\S\r\n]{2,}/u', ' ', (string) $normalized);
        $normalized = preg_replace('/\n{3,}/u', "\n\n", (string) $normalized);

        return trim((string) $normalized);
    }

    private function buildNormalRangeMap(array $testIds): array
    {
        if (empty($testIds)) {
            return [];
        }

        $parameters = PathologyTestParameter::query()
            ->whereIn('pathology_test_id', $testIds)
            ->with('pathologyUnit:id,name')
            ->orderBy('id')
            ->get();

        return $parameters
            ->groupBy('pathology_test_id')
            ->map(function ($rows) {
                $formatted = $rows->map(function ($row) {
                    $from = trim((string) ($row->reference_from ?? ''));
                    $to = trim((string) ($row->reference_to ?? ''));
                    $unit = trim((string) ($row->pathologyUnit->name ?? ''));

                    if ($from !== '' && $to !== '') {
                        $range = $from . ' - ' . $to;
                    } elseif ($from !== '') {
                        $range = $from;
                    } elseif ($to !== '') {
                        $range = $to;
                    } else {
                        $range = '';
                    }

                    $value = trim($range . ($unit !== '' ? ' ' . $unit : ''));
                    return $value !== '' ? $value : null;
                })->filter()->values();

                return $formatted->isNotEmpty() ? $formatted->implode(' | ') : null;
            })
            ->filter()
            ->toArray();
    }

    public function update(Request $request, Billing $billing)
    {
        $allowedCategories = $this->resolveDepartmentCategories();
        $validated = $request->validate([
            'report_notes' => ['array'],
            'report_notes.*' => ['nullable', 'string'],
            'report_files' => ['array'],
            'report_files.*' => $this->reportFileValidationRules(),
        ]);

        $items = BillItem::query()
            ->where('billing_id', $billing->id)
            ->whereIn('category', $allowedCategories)
            ->whereNotNull('sample_collected_at')
            ->whereNull('reported_at')
            ->get();

        foreach ($items as $item) {
            $note = $validated['report_notes'][$item->id] ?? null;
            $file = $request->file("report_files.{$item->id}");

            if ($file) {
                $path = $file->store('reports', 'public');
                $item->report_file = $path;
            }

            $item->report_note = $note;
            $item->reported_at = now();
            $item->reported_by = auth('admin')->id();
            $item->save();
        }

        return redirect()->route('backend.reporting.index')
            ->with('success', 'Report saved successfully.');
    }

    public function updateItem(Request $request, BillItem $billItem)
    {
        $allowedCategories = $this->resolveDepartmentCategories();
        $request->validate([
            'report_note' => ['nullable', 'string'],
            'report_range' => ['nullable', 'string', 'max:255'],
            'report_file' => $this->reportFileValidationRules(),
        ]);

        if (!in_array($billItem->category, $allowedCategories, true)) {
            return back()->with('warning', 'Invalid report item.');
        }

        if (empty($billItem->sample_collected_at)) {
            return back()->with('warning', 'Sample not collected yet.');
        }

        $file = $request->file('report_file');

        if ($file) {
            $path = $file->store('reports', 'public');
            $billItem->report_file = $path;
        }

        $billItem->report_note = $request->input('report_note');
        $billItem->report_range = $request->input('report_range');
        if (empty($billItem->reported_at)) {
            $billItem->reported_at = now();
            $billItem->reported_by = auth('admin')->id();
        }
        $billItem->save();

        return back()->with('success', 'Report saved successfully.');
    }

    public function print(BillItem $billItem)
    {
        $allowedCategories = $this->resolveDepartmentCategories();

        if (!in_array($billItem->category, $allowedCategories, true)) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'Invalid report item.');
        }

        if (empty($billItem->reported_at)) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'Report is not ready for print.');
        }

        $billItem->load('billing.patient', 'collectedBy', 'reportedBy.details.designation');

        $billing = $billItem->billing;
        $patient = $billing?->patient;
        if ($patient) {
            $patientName = trim((string) ($patient->name ?? trim((($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '')))));
            if ($patientName === '') {
                $patientName = 'N/A';
            }
        } else {
            $patientName = 'N/A';
        }

        $settings = WebSetting::query()
            ->where('status', 'Active')
            ->orderByDesc('id')
            ->first();
        if (!$settings) {
            $settings = WebSetting::query()->orderByDesc('id')->first();
        }

        $headerHtml = trim((string) ($settings?->report_header_html ?? ''));
        $footerHtml = trim((string) ($settings?->report_footer_html ?? ''));

        $attendanceOptions = [];
        $rawOptions = $settings?->attendance_device_options;

        if (is_array($rawOptions)) {
            $attendanceOptions = $rawOptions;
        } elseif (is_string($rawOptions) && trim($rawOptions) !== '') {
            $decodedOptions = json_decode($rawOptions, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decodedOptions)) {
                    $attendanceOptions = $decodedOptions;
                } elseif (is_string($decodedOptions) && trim($decodedOptions) !== '') {
                    $decodedTwice = json_decode($decodedOptions, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedTwice)) {
                        $attendanceOptions = $decodedTwice;
                    }
                }
            }
        }

        $signatureSettings = data_get($attendanceOptions, 'reporting.signature', []);
        if (!is_array($signatureSettings)) {
            $signatureSettings = [];
        }

        $identitySettings = data_get($attendanceOptions, 'reporting.identity', []);
        if (!is_array($identitySettings)) {
            $identitySettings = [];
        }

        $layoutSettings = data_get($attendanceOptions, 'reporting.layout', []);
        if (!is_array($layoutSettings)) {
            $layoutSettings = [];
        }

        $signatureMarginTop = max((int) ($signatureSettings['margin_top'] ?? 160), 0);
        $signatureMarginLeft = max((int) ($signatureSettings['margin_left'] ?? 96), 0);
        $pageMarginTop = max((int) ($layoutSettings['page_margin_top'] ?? 0), 0);
        $pageMarginBottom = max((int) ($layoutSettings['page_margin_bottom'] ?? 0), 0);

        // header/footer heights (pixels). Defaults chosen to keep previous look:
        // default header ~115px (≈1.2in), footer default 70px.
        $reportHeaderHeight = max((int) ($layoutSettings['header_height'] ?? 115), 0);
        $reportFooterHeight = max((int) ($layoutSettings['footer_height'] ?? 70), 0);
        $reportHeaderHeight = max((int) ($layoutSettings['header_height'] ?? 115), 0); // px

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', 'billing')->first();
        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }
        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

        // Final header/footer algorithm:
        // Header: invoice header image > report header html > empty
        // Footer: invoice footer image > invoice footer content > report footer html > empty
        $headerImageBase64 = $this->resolvePublicStorageImageDataUri((string) ($invoiceDesign?->header_photo_path ?? ''));
        $footerImageBase64 = $this->resolvePublicStorageImageDataUri((string) ($invoiceDesign?->footer_photo_path ?? ''));
        $footerContent = trim((string) ($invoiceDesign?->footer_content ?? ''));

        $hasHeader = $headerImageBase64 !== '' || $headerHtml !== '';
        $hasFooter = $footerImageBase64 !== '' || $footerContent !== '' || $footerHtml !== '';

        // respect admin option to show/hide header & footer for reporting
        $showHeaderFooter = data_get($attendanceOptions, 'reporting.show_header_footer', true);
        if (!$showHeaderFooter) {
            $headerImageBase64 = '';
            $footerImageBase64 = '';
            $headerHtml = '';
            $footerContent = '';
            $footerHtml = '';
            $hasHeader = false;
            $hasFooter = false;
        }

        // apply page bottom margin if provided (already set above)
        $reportFooterHeight = max((int) ($layoutSettings['footer_height'] ?? 70), 0); // px

        $resolveSignatureImage = function (?string $path): string {
            $rawPath = trim((string) $path);
            if ($rawPath === '') {
                return '';
            }

            $storagePath = storage_path('app/public/' . ltrim($rawPath, '/'));
            if (!file_exists($storagePath)) {
                return '';
            }

            $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => 'image/png',
            };

            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($storagePath));
        };

        $technologistSignature = $resolveSignatureImage((string) ($settings?->technologist_signature ?? ''));
        $sampleCollectedBySignature = $resolveSignatureImage((string) ($settings?->sample_collected_by_signature ?? ''));
        $pathologistSignature = $resolveSignatureImage((string) ($settings?->pathologist_signature ?? ''));
        $pathologistName = trim((string) ($settings?->pathologist_name ?? ''));
        $pathologistDesignation = trim((string) ($settings?->pathologist_designation ?? ''));

        $technologistNameSetting = trim((string) ($identitySettings['technologist_name'] ?? ''));
        $technologistDesignationSetting = trim((string) ($identitySettings['technologist_designation'] ?? ''));
        $sampleCollectedByNameSetting = trim((string) ($identitySettings['sample_collected_by_name'] ?? ''));
        $sampleCollectedByDesignationSetting = trim((string) ($identitySettings['sample_collected_by_designation'] ?? ''));

        $contactNo = $billing?->patient_mobile ?? $patient?->phone ?? $patient?->mobile ?? 'N/A';
        $gender = $billing?->gender ?? $patient?->gender ?? 'N/A';

        $age = 'N/A';
        if ($patient?->dob) {
            $dob = new \DateTime($patient->dob);
            $now = new \DateTime();
            $ageYears = $now->diff($dob)->y;
            $age = $ageYears . ' Year (As Of Date ' . $now->format('d.m.Y') . ')';
        } elseif (!empty($patient?->age)) {
            $age = $patient->age . ' Y';
        }

        $reportDateTime = $billItem->reported_at
            ? $billItem->reported_at->format('d-M-Y h:i:s A')
            : now()->format('d-M-Y h:i:s A');

        $refdBy = $billing?->doctor_name ?? 'N/A';

        $singleItemCategories = ['Radiology', 'Ultrasonogram', 'Ultrasonography', 'ECG'];
        $isSingleItemCategory = in_array((string) $billItem->category, $singleItemCategories, true);
        $isCbc = str_contains(strtolower((string) $billItem->item_name), 'cbc');

        if ($isSingleItemCategory || $isCbc) {
            $items = collect([$billItem]);
        } else {
            $allItems = BillItem::query()
                ->where('billing_id', $billItem->billing_id)
                ->where('category', 'Pathology')
                ->whereNotNull('reported_at')
                ->where(function ($query) {
                    $query->whereNull('item_name')
                        ->orWhereRaw("LOWER(item_name) NOT LIKE '%cbc%'");
                })
                ->orderBy('id')
                ->get();

            $chunks = $allItems->chunk(4);
            $items = $chunks->first(function ($chunk) use ($billItem) {
                return $chunk->contains('id', $billItem->id);
            }) ?? collect([$billItem]);
        }

        $reportTitle = $this->resolveReportTitle($billItem);
        $isUltrasonogramReport = $this->isUltrasonogramBillItem($billItem, $reportTitle);

        return view('backend.reporting.print', [
            'items' => $items,
            'primaryItem' => $billItem,
            'reportTitle' => $reportTitle,
            'isUltrasonogramReport' => $isUltrasonogramReport,
            'billing' => $billing,
            'patientName' => $patientName,
            'headerHtml' => $headerHtml,
            'footerHtml' => $footerHtml,
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $footerContent,
            'hasHeader' => $hasHeader,
            'hasFooter' => $hasFooter,
            'reportDateTime' => $reportDateTime,
            'age' => $age,
            'contact_no' => $contactNo,
            'gender' => $gender,
            'refd_by' => $refdBy,
            'signatureMarginTop' => $signatureMarginTop,
            'signatureMarginLeft' => $signatureMarginLeft,
            'pageMarginTop' => $pageMarginTop,
            'reportHeaderHeight' => $reportHeaderHeight,
            'reportFooterHeight' => $reportFooterHeight,
            'technologistSignature' => $technologistSignature,
            'sampleCollectedBySignature' => $sampleCollectedBySignature,
            'pathologistSignature' => $pathologistSignature,
            'pathologistName' => $pathologistName !== '' ? $pathologistName : 'N/A',
            'pathologistDesignation' => $pathologistDesignation !== '' ? $pathologistDesignation : 'Pathologist',
            'technologistNameSetting' => $technologistNameSetting,
            'technologistDesignationSetting' => $technologistDesignationSetting,
            'sampleCollectedByNameSetting' => $sampleCollectedByNameSetting,
            'sampleCollectedByDesignationSetting' => $sampleCollectedByDesignationSetting,
            // raw values for blade logic (empty string when not set)
            'pathologistNameRaw' => $pathologistName,
            'pathologistDesignationRaw' => $pathologistDesignation,
        ]);
    }

    public function downloadReport(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'module' => ['nullable', 'string', 'in:reporting,pathology,radiology'],
        ]);

        $requestedId = (int) $validated['id'];
        $module = strtolower((string) ($validated['module'] ?? 'reporting'));

        // Formula support: id can be either bill_items.id or billings.id.
        $billItem = BillItem::query()->find($requestedId);

        if (!$billItem) {
            $billItem = BillItem::query()
                ->where('billing_id', $requestedId)
                ->when($module === 'pathology', fn ($query) => $query->where('category', 'Pathology'))
                ->when($module === 'radiology', fn ($query) => $query->where('category', 'Radiology'))
                ->whereNotNull('reported_at')
                ->orderBy('id')
                ->first();
        }

        if (!$billItem) {
            return redirect()->route('backend.reporting.index')
                ->with('warning', 'No report found for this id.');
        }

        return redirect()->route('backend.reporting.print', $billItem->id);
    }

    private function resolveReportTitle(BillItem $billItem): string
    {
        $categoryName = '';

        if ($billItem->category === 'Pathology' && !empty($billItem->item_id)) {
            $test = Test::query()
                ->with('pathologyCategory')
                ->find($billItem->item_id);

            $categoryName = trim((string) ($test?->pathologyCategory?->name ?? ''));
        } elseif ($billItem->category === 'Radiology' && !empty($billItem->item_id)) {
            $radiologyTest = RadiologyTest::query()
                ->with('test.pathologyCategory')
                ->find($billItem->item_id);

            $categoryName = trim((string) ($radiologyTest?->test?->pathologyCategory?->name ?? ''));
        }

        if ($categoryName === '') {
            $fallback = trim((string) $billItem->category);
            if ($fallback === '') {
                return 'Test Report';
            }
            return $fallback . ' Report';
        }

        return $categoryName . ' Report';
    }

    private function isUltrasonogramBillItem(BillItem $billItem, ?string $reportTitle = null): bool
    {
        $keywords = ['ultrasonogram', 'ultrasonography', 'usg'];

        $matchesKeywords = function (?string $value) use ($keywords): bool {
            $text = strtolower(trim((string) $value));
            if ($text === '') {
                return false;
            }

            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return true;
                }
            }

            return false;
        };

        if (
            $matchesKeywords((string) $billItem->category)
            || $matchesKeywords((string) $billItem->item_name)
            || $matchesKeywords((string) $reportTitle)
        ) {
            return true;
        }

        if ($billItem->category !== 'Radiology' || empty($billItem->item_id)) {
            return false;
        }

        $radiologyTest = RadiologyTest::query()
            ->with('test.pathologyCategory')
            ->find($billItem->item_id);

        if (!$radiologyTest) {
            return false;
        }

        $lookupTexts = [
            data_get($radiologyTest, 'test.test_name'),
            data_get($radiologyTest, 'test.test_short_name'),
            data_get($radiologyTest, 'test.pathologyCategory.name'),
        ];

        foreach ($lookupTexts as $text) {
            if ($matchesKeywords($text)) {
                return true;
            }
        }

        return false;
    }

    private function suggestNormalRangeByTestName(BillItem $item): ?string
    {
        $name = strtolower(trim((string) $item->item_name));
        $category = strtolower(trim((string) $item->category));
        $context = trim($name . ' ' . $category);

        if ($context === '') {
            return null;
        }

        // Curated quick suggestions for common tests.
        $rules = [
            ['keywords' => ['hemoglobin', 'hb'], 'range' => 'Male: 13 - 17 g/dL | Female: 12 - 15 g/dL'],
            ['keywords' => ['wbc', 'white blood cell'], 'range' => '4,000 - 11,000 /uL'],
            ['keywords' => ['rbc', 'red blood cell'], 'range' => 'Male: 4.5 - 5.9 M/uL | Female: 4.1 - 5.1 M/uL'],
            ['keywords' => ['platelet'], 'range' => '150,000 - 450,000 /uL'],
            ['keywords' => ['esr'], 'range' => 'Male: 0 - 15 mm/hr | Female: 0 - 20 mm/hr'],
            ['keywords' => ['glucose', 'fbs', 'fasting blood sugar'], 'range' => '70 - 99 mg/dL'],
            ['keywords' => ['rbs', 'random blood sugar'], 'range' => '70 - 140 mg/dL'],
            ['keywords' => ['hba1c'], 'range' => '4.0 - 5.6 %'],
            ['keywords' => ['creatinine'], 'range' => '0.6 - 1.2 mg/dL'],
            ['keywords' => ['urea'], 'range' => '15 - 40 mg/dL'],
            ['keywords' => ['alt', 'sgpt'], 'range' => '7 - 56 U/L'],
            ['keywords' => ['ast', 'sgot'], 'range' => '10 - 40 U/L'],
            ['keywords' => ['bilirubin'], 'range' => '0.2 - 1.2 mg/dL'],
            ['keywords' => ['cholesterol'], 'range' => '< 200 mg/dL'],
            ['keywords' => ['triglyceride', 'triglycerides'], 'range' => '< 150 mg/dL'],
            ['keywords' => ['ldl'], 'range' => '< 100 mg/dL'],
            ['keywords' => ['hdl'], 'range' => 'Male: > 40 mg/dL | Female: > 50 mg/dL'],
            ['keywords' => ['tsh'], 'range' => '0.4 - 4.0 mIU/L'],
            ['keywords' => ['t3'], 'range' => '0.8 - 2.0 ng/mL'],
            ['keywords' => ['t4'], 'range' => '5.0 - 12.0 ug/dL'],
            ['keywords' => ['ecg'], 'range' => 'Heart Rate: 60 - 100 bpm'],
            ['keywords' => ['ultrasonogram', 'ultrasonography', 'usg'], 'range' => 'Impression based on clinical and sonographic findings'],
        ];

        foreach ($rules as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if (str_contains($context, $keyword)) {
                    return $rule['range'];
                }
            }
        }

        return null;
    }

    private function resolveDepartmentCategories(): array
    {
        $departmentName = strtolower(trim((string) data_get(auth('admin')->user(), 'details.department.name', '')));
        $designationName = strtolower(trim((string) data_get(auth('admin')->user(), 'details.designation.name', '')));
        $scopeText = trim($departmentName . ' ' . $designationName);

        if (str_contains($scopeText, 'pathology') || str_contains($scopeText, 'patholog')) {
            return ['Pathology'];
        }

        if (str_contains($scopeText, 'radiology') || str_contains($scopeText, 'radiolog')) {
            return ['Radiology'];
        }

        if (
            str_contains($scopeText, 'ultrasonogram')
            || str_contains($scopeText, 'ultrasonography')
            || str_contains($scopeText, 'usg')
        ) {
            return ['Ultrasonogram', 'Ultrasonography'];
        }

        if (str_contains($scopeText, 'ecg') || str_contains($scopeText, 'e.c.g')) {
            return ['ECG'];
        }

        return ['Pathology', 'Radiology', 'Ultrasonogram', 'Ultrasonography', 'ECG'];
    }

    private function resolvePublicStorageImageDataUri(?string $path): string
    {
        $rawPath = trim((string) $path);
        if ($rawPath === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $rawPath);
        $normalized = ltrim($normalized, '/');

        $candidates = array_values(array_unique(array_filter([
            $normalized,
            preg_replace('#^storage/#i', '', $normalized),
            preg_replace('#^public/#i', '', $normalized),
            preg_replace('#^public/storage/#i', '', $normalized),
        ])));

        $resolvedPath = null;
        foreach ($candidates as $candidate) {
            $fullPath = storage_path('app/public/' . ltrim($candidate, '/'));
            if (file_exists($fullPath)) {
                $resolvedPath = $fullPath;
                break;
            }
        }

        if ($resolvedPath === null) {
            return '';
        }

        $extension = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($resolvedPath));
    }
}
