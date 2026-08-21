<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'APP_KEY=' . (config('bkash.app_key') ?: 'NULL') . PHP_EOL;
echo 'APP_SECRET=' . (config('bkash.app_secret') ?: 'NULL') . PHP_EOL;
echo 'USERNAME=' . (config('bkash.username') ?: 'NULL') . PHP_EOL;
echo 'PASSWORD=' . (config('bkash.password') ?: 'NULL') . PHP_EOL;
echo 'USE_PRODUCTION=' . var_export(config('bkash.use_production'), true) . PHP_EOL;
