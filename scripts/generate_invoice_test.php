<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Http\Request;
use Illuminate\Contracts\Http\Kernel;

// Bootstrap the application so validator & other services are available
$consoleKernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$consoleKernel->bootstrap();

// adjust id if needed
$id = $argv[1] ?? 10;

// Call controller method directly to avoid auth middleware redirects
$controller = $app->make(\App\Http\Controllers\Backend\InvoiceController::class);
$request = Request::create('/', 'GET', ['id' => $id]);

try {
	// Ensure Request has validate() macro available in this script context
	if (! method_exists(Request::class, 'validate')) {
		Request::macro('validate', function ($rules, $messages = []) {
			return app('validator')->make($this->all(), $rules, $messages)->validate();
		});
	}

	$response = $controller->downloadInvoice($request);
} catch (\Throwable $e) {
	echo "Controller call failed: " . $e->getMessage() . PHP_EOL;
	exit(1);
}

$content = is_object($response) && method_exists($response, 'getContent') ? $response->getContent() : (is_string($response) ? $response : '');
$contentType = is_object($response) && method_exists($response, 'headers') ? $response->headers->get('content-type') : 'unknown';

$outDir = __DIR__ . '/../storage/app/public';
@mkdir($outDir, 0777, true);
$outFile = $outDir . '/test_invoice_' . $id . '.pdf';

file_put_contents($outFile, $content);

echo "Wrote: " . $outFile . PHP_EOL;
echo "Content-Type: " . ($contentType ?: 'unknown') . PHP_EOL;
echo file_exists($outFile) ? 'yes' : 'no';
