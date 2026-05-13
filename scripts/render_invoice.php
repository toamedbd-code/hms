<?php
// Usage: php scripts/render_invoice.php [billing_id]
$billingId = isset($argv[1]) ? intval($argv[1]) : 17;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Billing;
use App\Http\Controllers\Backend\InvoiceController;
use Illuminate\Http\Request;

$billing = Billing::find($billingId);
if (! $billing) {
    echo "Billing {$billingId} not found\n";
    exit(1);
}

$request = Request::create('/', 'GET', ['id' => $billingId, 'module' => 'billing']);

$controller = $app->make(InvoiceController::class);

try {
    $response = $controller->downloadInvoice($request);
    if (is_object($response) && method_exists($response, 'getContent')) {
        $content = $response->getContent();
    } else {
        $content = (string) $response;
    }

    $out = storage_path('app/invoice_' . $billingId . '.pdf');
    file_put_contents($out, $content);
    echo "WROTE: {$out}\n";
    exit(0);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(2);
}
