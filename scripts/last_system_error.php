<?php
// scripts/last_system_error.php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$e = App\Models\SystemErrorLog::query()->orderByDesc('id')->first();

if (!$e) {
    echo "NO_SYSTEM_ERROR_LOG\n";
    exit(0);
}

echo "ID: {$e->id}\n";
echo "Controller: {$e->controller}\n";
echo "Function: {$e->function}\n";
echo "CreatedAt: {$e->created_at}\n";
echo "Log: " . substr((string) $e->log, 0, 2000) . "\n";
