<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$roleName = $argv[1] ?? 'developer';

try {
    $role = Role::where('name', $roleName)->first();
    if (! $role) {
        echo json_encode(['ok' => false, 'error' => 'role_not_found', 'role' => $roleName], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    }

    $perms = $role->permissions()->pluck('name')->toArray();

    echo json_encode(['ok' => true, 'role' => $roleName, 'permissions' => $perms], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => (string) $e], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}
