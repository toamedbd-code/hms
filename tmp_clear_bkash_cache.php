<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ok = app('cache')->forget('bkash_test_last');
if ($ok) {
    echo "OK: bkash_test_last cache cleared\n";
} else {
    echo "Notice: bkash_test_last cache key not present or could not be cleared\n";
}
