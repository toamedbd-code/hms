<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$kernel->bootstrap();

$cli = $_SERVER['argv'] ?? [];
$modeArg = $cli[1] ?? 'test';
$qArg = $cli[2] ?? 'cbc';
$fromArg = $cli[3] ?? '2020-01-01';
$toArg = $cli[4] ?? date('Y-m-d');
$numArg = isset($cli[5]) ? (int) $cli[5] : 10;

$params = [
    'q' => $qArg,
    'mode' => $modeArg,
    'from' => $fromArg,
    'to' => $toArg,
    'numOfData' => $numArg,
];

// Create request with Inertia / AJAX headers so controller returns JSON props
$request = Illuminate\Http\Request::create('/doctor-summary', 'GET', $params, [], [], [
    'HTTP_X_INERTIA' => 'true',
    'HTTP_ACCEPT' => 'application/json',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
]);

// Bind request
$app->instance('request', $request);

try {
    $result = app()->call('App\\Http\\Controllers\\Backend\\DoctorSummaryController@index', ['request' => $request]);

    if (is_object($result) && method_exists($result, 'toResponse')) {
        $response = $result->toResponse($request);
        echo $response->getContent();
    } elseif (is_object($result) && method_exists($result, 'getContent')) {
        echo $result->getContent();
    } else {
        var_dump($result);
    }
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

