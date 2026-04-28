<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Billing;

// Change the ID if you want to inspect a different billing record
$billingId = $argv[1] ?? 1;

$billing = Billing::with('billItems')->find((int) $billingId);

if (!$billing) {
    echo json_encode(null, JSON_PRETTY_PRINT);
    exit(0);
}

echo json_encode($billing->toArray(), JSON_PRETTY_PRINT);
