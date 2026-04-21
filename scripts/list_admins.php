<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;

try {
    $admins = Admin::select('id', 'first_name', 'last_name', 'email', 'role_id')->orderBy('id', 'asc')->limit(100)->get()->toArray();
    echo json_encode(['count' => count($admins), 'admins' => $admins], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => (string) $e], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
