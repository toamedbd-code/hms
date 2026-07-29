<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$roleId = $argv[1] ?? null;
if (!$roleId) {
    echo "Usage: php scripts/role_permissions.php <roleId>\n";
    exit(1);
}

try {
    $role = \Spatie\Permission\Models\Role::find($roleId);
    if (!$role) {
        echo "Role {$roleId} not found\n";
        exit(0);
    }
    echo "Role: {$role->name} (id={$role->id})\n";
    $perms = $role->permissions->map(function($p){ return $p->id.":".$p->name; })->toArray();
    if (count($perms) === 0) {
        echo "No permissions assigned\n";
    } else {
        foreach ($perms as $p) echo $p . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
