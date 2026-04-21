<?php
// Usage: php scripts/generate_invoice_cli.php <id> [module] [output_filename]
// Example: php scripts/generate_invoice_cli.php 2 opd invoice_2_opd.pdf

if ($argc < 2) {
    echo "Usage: php scripts/generate_invoice_cli.php <id> [module] [output_filename]\n";
    exit(1);
}

$id = $argv[1];
$module = $argv[2] ?? 'billing';
$outName = $argv[3] ?? ('invoice_' . $id . '_' . $module . '.pdf');
$outPath = __DIR__ . '/../storage/app/' . $outName;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Str;

try {
    // Create a Request similar to browser call
    $request = Request::create('/download-invoice', 'GET', ['id' => $id, 'module' => $module]);

    // Resolve controller(s) from the container (constructor injections will be handled)
    $mod = strtolower(trim((string) $module));

    if ($mod === 'prescription' || $mod === 'opd-prescription') {
        $opdController = $app->make(App\Http\Controllers\Backend\OpdPatientController::class);
        // downloadPrescriptionPdf expects the $id parameter
        $response = $opdController->downloadPrescriptionPdf($id);
    } else {
        $controller = $app->make(App\Http\Controllers\Backend\InvoiceController::class);
        // Call appropriate method depending on module
        if ($mod === 'opd') {
            $response = $controller->downloadOpdInvoice($request);
        } elseif ($mod === 'appointment') {
            $response = $controller->downloadAppointmentInvoice($request);
        } else {
            $response = $controller->downloadInvoice($request);
        }
    }

    if (!method_exists($response, 'getContent')) {
        echo "Unexpected response object; cannot extract content.\n";
        exit(2);
    }

    $content = $response->getContent();
    if ($content === null || $content === '') {
        echo "Response content empty. Possibly an error occurred.\n";
        // Dump response for debugging
        if (method_exists($response, 'getStatusCode')) {
            echo "Status: " . $response->getStatusCode() . "\n";
        }
        exit(3);
    }

    file_put_contents($outPath, $content);
    echo "OK: Saved to " . $outPath . "\n";
    exit(0);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(4);
}
