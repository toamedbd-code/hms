<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SystemErrorLog;

$errors = SystemErrorLog::query()->orderByDesc('id')->limit(50)->get(['id','namespace','controller','function','log','created_at']);

echo json_encode($errors->toArray(), JSON_PRETTY_PRINT);
