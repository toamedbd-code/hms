<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$role = \Spatie\Permission\Models\Role::where('name','developer')->first();
if (!$role) { echo "developer role not found\n"; exit(1); }
$perms = \App\Models\Permission::pluck('name')->toArray();
$role->syncPermissions($perms);
echo "Synced developer role with " . count($perms) . " permissions\n";
