<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "ENV DB_CONNECTION=" . getenv('DB_CONNECTION') . PHP_EOL;
echo "ENV DB_DATABASE=" . getenv('DB_DATABASE') . PHP_EOL;
echo "config default=" . config('database.default') . PHP_EOL;
echo "config db=" . config('database.connections.' . config('database.default') . '.database') . PHP_EOL;
echo "config host=" . config('database.connections.' . config('database.default') . '.host') . PHP_EOL;
