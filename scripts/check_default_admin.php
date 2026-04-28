<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = \App\Models\Admin::where('email','toamedbd@gmail.com')->first();
if (! $admin) {
    echo "NOT FOUND\n";
    exit(1);
}
echo "FOUND: " . $admin->email . "\n";
$roles = $admin->getRoleNames()->toArray();
echo "Roles: " . (count($roles) ? implode(',', $roles) : '(none)') . "\n";
$perms = $admin->getAllPermissions()->pluck('name')->toArray();
echo "Permissions: " . (count($perms) ? implode(',', $perms) : '(none)') . "\n";
