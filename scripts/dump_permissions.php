<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $cols = DB::select('SHOW COLUMNS FROM permissions');
    $perms = DB::table('permissions')->orderBy('id','asc')->limit(30)->get()->toArray();
    echo json_encode(['columns' => $cols, 'rows' => $perms], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => (string)$e], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
