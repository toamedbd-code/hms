<?php
chdir(__DIR__ . '/..');
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Payment;

$payments = Payment::latest()->take(10)->get()->toArray();
echo json_encode($payments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
