<?php
// Usage: php scripts/generate_ipd_prescription_cli.php <id> [output_filename]
// Example: php scripts/generate_ipd_prescription_cli.php 2 ipd_prescription_2.pdf

if ($argc < 2) {
    echo "Usage: php scripts/generate_ipd_prescription_cli.php <id> [output_filename]\n";
    exit(1);
}

$id = $argv[1];
$outName = $argv[2] ?? ('ipd_prescription_' . $id . '.pdf');
$outPath = __DIR__ . '/../storage/app/' . $outName;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $controller = $app->make(App\Http\Controllers\Backend\IpdPatientController::class);
    $response = $controller->downloadPrescriptionPdf($id);

    if (!method_exists($response, 'getContent')) {
        echo "Unexpected response object; cannot extract content.\n";
        exit(2);
    }

    $content = $response->getContent();
    if ($content === null || $content === '') {
        echo "Response content empty. Possibly an error occurred.\n";
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
