<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
// Boot the kernel so app() and facades work
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
// Ensure facades have application instance
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
// Bootstrap the kernel (register config, providers, etc.)
$kernel->bootstrap();

// Allow passing mode/q/from/to/numOfData via CLI args: php scripts/debug_doctor_summary.php technologist rbs 2026-04-28 2026-04-28 10
$cli = $_SERVER['argv'] ?? [];
$modeArg = $cli[1] ?? 'test';
$qArg = $cli[2] ?? 'cbc';
$fromArg = $cli[3] ?? '2026-04-28';
$toArg = $cli[4] ?? '2026-04-28';
$numArg = isset($cli[5]) ? (int) $cli[5] : 10;

// Create a Request similar to the AJAX call
$request = Illuminate\Http\Request::create('/doctor-summary', 'GET', [
    'q' => $qArg,
    'mode' => $modeArg,
    'from' => $fromArg,
    'to' => $toArg,
    'numOfData' => $numArg,
]);

// Bind the request instance into the container so pagination and other
// services that resolve 'request' from the container work in CLI.
$app->instance('request', $request);

try {
    // Call the controller method directly (bypasses route middleware)
    $result = app()->call('App\\Http\\Controllers\\Backend\\DoctorSummaryController@index', ['request' => $request]);

    echo "Result type: " . gettype($result) . PHP_EOL;
    if (is_object($result)) {
        echo "Object class: " . get_class($result) . PHP_EOL;
        // If it's a Response, print status and a short body
        if (method_exists($result, 'getStatusCode')) {
            echo "Status: " . $result->getStatusCode() . PHP_EOL;
            $body = $result->getContent();
            echo "Body length: " . strlen($body) . PHP_EOL;
            echo "Body (first 800 chars):\n" . substr($body, 0, 800) . PHP_EOL;
        } else {
            // Dump the object
            var_dump($result);
        }
    } else {
        var_dump($result);
    }
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

// Terminate kernel (not strictly necessary here)
// $kernel->terminate($request, new \Illuminate\Http\Response());
