<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;

$email = $argv[1] ?? env('SINGLE_DEV_EMAIL', 'toamedbd@gmail.com');
$admin = Admin::where('email', $email)->first();
if (!$admin) {
    echo "No user with email {$email} found.\n";
    exit;
}

echo "User: " . ($admin->email ?? $admin->name ?? 'unknown') . " (id={$admin->id})\n";

try {
    $direct = $admin->permissions()->pluck('name')->toArray();
} catch (\Throwable $e) {
    $direct = [];
}

try {
    $roles = $admin->roles()->pluck('name')->toArray();
} catch (\Throwable $e) {
    $roles = [];
}

try {
    $all = $admin->getAllPermissions()->pluck('name')->toArray();
} catch (\Throwable $e) {
    $all = [];
}

echo "Direct permissions assigned to user:\n";
print_r($direct);

echo "Roles assigned to user:\n";
print_r($roles);

echo "All effective permissions (getAllPermissions):\n";
print_r($all);

echo "\nPermissions contributed by each role:\n";
try {
    foreach ($admin->roles as $r) {
        echo "- Role: " . ($r->name ?? $r->id) . "\n";
        $rp = $r->permissions()->pluck('name')->toArray();
        print_r($rp);
    }
} catch (\Throwable $_) {
    // ignore
}

echo "Done.\n";
