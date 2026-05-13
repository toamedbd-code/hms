<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$roleName = $argv[1] ?? 'developer';

try {
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'admin']);
    $all = Permission::pluck('name')->toArray();
    if (!empty($all)) {
        $role->givePermissionTo($all);
    }

    echo json_encode(['ok' => true, 'role' => $roleName, 'granted_count' => count($all)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => (string) $e], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}
