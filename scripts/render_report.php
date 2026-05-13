<?php
// Usage: php scripts/render_report.php [bill_item_id]
$billItemId = isset($argv[1]) ? intval($argv[1]) : 55;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BillItem;

$billItem = BillItem::find($billItemId);
if (! $billItem) {
    echo "BillItem {$billItemId} not found\n";
    exit(1);
}

try {
    $controller = $app->make(\App\Http\Controllers\Backend\ReportingController::class);
    $response = $controller->print($billItem);

    if (is_object($response) && method_exists($response, 'render')) {
        $html = $response->render();
    } elseif ($response instanceof Illuminate\Http\Response || $response instanceof Illuminate\Http\JsonResponse) {
        $html = $response->getContent();
    } else {
        $html = (string) $response;
    }

    $out = storage_path('app/report_' . $billItemId . '.html');
    file_put_contents($out, $html);
    echo "WROTE: {$out}\n";
    exit(0);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(2);
}
