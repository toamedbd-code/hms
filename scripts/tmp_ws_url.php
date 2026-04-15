<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$ws = App\Models\WebSetting::query()->orderByDesc('id')->first();
if (!$ws) { echo "no-websetting\n"; exit; }
echo "logo=" . ($ws->logo ?? '') . "\n";
echo "icon=" . ($ws->icon ?? '') . "\n";
