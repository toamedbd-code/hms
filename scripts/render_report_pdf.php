<?php
// Usage: php scripts/render_report_pdf.php [bill_item_id]
$billItemId = isset($argv[1]) ? intval($argv[1]) : 55;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$htmlPath = storage_path('app/report_' . $billItemId . '.html');
if (!file_exists($htmlPath)) {
    // Try generating HTML using existing script
    $cmd = escapeshellcmd((defined('PHP_BINARY') ? PHP_BINARY : 'php') . ' "' . __DIR__ . '/render_report.php" ' . $billItemId);
    passthru($cmd, $ret);
    if ($ret !== 0 || !file_exists($htmlPath)) {
        echo "Failed to generate HTML via render_report.php\n";
        exit(2);
    }
}

$html = file_get_contents($htmlPath);

$headerPx = 115;
$footerPx = 70;
$pageMarginTopPx = 0;
if (preg_match('/--report-header-height:\s*([0-9]+)px/i', $html, $m)) {
    $headerPx = (int) $m[1];
}
if (preg_match('/--report-footer-height:\s*([0-9]+)px/i', $html, $m)) {
    $footerPx = (int) $m[1];
}
if (preg_match('/--report-page-top-margin:\s*([0-9]+)px/i', $html, $m)) {
    $pageMarginTopPx = (int) $m[1];
}

$pxToMm = function ($px) {
    return round(((float) $px) * 25.4 / 96, 2);
};

$marginHeaderMm = $headerPx > 0 ? ($pxToMm($headerPx)) : 0;
$marginFooterMm = $footerPx > 0 ? ($pxToMm($footerPx)) : 0;

$tempDir = storage_path('app/mpdf-temp');
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0775, true);
}

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

try {
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'tempDir' => $tempDir,
        'default_font' => 'dejavusans',
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => max(0, (int) $pageMarginTopPx),
        'margin_bottom' => 12,
        'margin_header' => $marginHeaderMm,
        'margin_footer' => $marginFooterMm,
    ]);

    $mpdf->WriteHTML($html);
    $outPath = storage_path('app/report_' . $billItemId . '.pdf');
    $mpdf->Output($outPath, Destination::FILE);
    echo "WROTE: {$outPath}\n";
    exit(0);
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(3);
}
