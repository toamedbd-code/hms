<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;

$sub = Subscription::first();
if (! $sub) {
    echo json_encode(null);
} else {
    echo json_encode($sub->toArray(), JSON_PRETTY_PRINT);
}
