<?php
// Usage: php scripts/fetch_ipd_direct.php [id]
// This script boots the app, resolves InvoiceController from container and
// calls printIpdInvoice() directly to render the HTML view (bypassing route middleware).

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$consoleKernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$consoleKernel->bootstrap();

$id = $argv[1] ?? null;
if (!$id) {
    try {
        $id = \App\Models\IpdPatient::query()->latest()->value('id');
    } catch (Throwable $e) {
        echo "ERROR: could not query IpdPatient: " . $e->getMessage() . PHP_EOL;
        exit(2);
    }
}

if (empty($id)) {
    echo "NO_IPD" . PHP_EOL;
    exit(1);
}

// Build a simple request object with id
$request = Illuminate\Http\Request::create('/', 'GET', ['id' => $id]);

// Resolve controller from container so dependencies are injected
$controller = $app->make(\App\Http\Controllers\Backend\InvoiceController::class);

try {
    $response = $controller->printIpdInvoice($request);
    $content = '';

    if (is_string($response)) {
        $content = $response;
    } elseif (method_exists($response, 'getContent')) {
        $content = $response->getContent();
    } elseif (method_exists($response, 'render')) {
        $content = $response->render();
    } else {
        $content = (string) $response;
    }

    $dir = __DIR__ . '/../storage/app/temp-invoice';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $file = $dir . '/ipd_print_direct_' . $id . '.html';
    file_put_contents($file, $content);
    echo $file . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(3);
}
