<?php
// Usage: php scripts/fetch_ipd_print.php [id]
// If no id provided, script picks latest IpdPatient id.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$consoleKernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$consoleKernel->bootstrap();

// Determine IPD id
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

// Create an HTTP request to the print route
$request = Illuminate\Http\Request::create('/print/ipd/invoice', 'GET', ['id' => $id]);
/** @var \Illuminate\Contracts\Http\Kernel $httpKernel */
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $httpKernel->handle($request);

$content = $response->getContent();

$dir = __DIR__ . '/../storage/app/temp-invoice';
if (!is_dir($dir)) mkdir($dir, 0777, true);
$file = $dir . '/ipd_print_' . $id . '.html';
file_put_contents($file, $content);

// Properly terminate kernel
$httpKernel->terminate($request, $response);

echo $file . PHP_EOL;
