<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;
use App\Models\Admin;

$roles = Role::all(['id','name','is_private'])->toArray();
$admins = Admin::orderByDesc('id')->limit(10)->get(['id','email','role_id','created_at'])->toArray();

echo json_encode(['roles' => $roles, 'admins' => $admins], JSON_PRETTY_PRINT);
