<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;

$email = $argv[1] ?? 'toamedbd@gmail.com';
$admin = Admin::where('email', $email)->first();
if (! $admin) {
    echo "Admin not found: $email\n";
    exit(1);
}

echo "Admin ID: {$admin->id}\n";
echo "Email: {$admin->email}\n";
echo "Roles: " . implode(',', $admin->getRoleNames()->toArray()) . "\n";
echo "Has role developer: " . (method_exists($admin, 'hasRole') && $admin->hasRole('developer') ? 'true' : 'false') . "\n";

$perms = $admin->getAllPermissions()->pluck('name')->toArray();
echo "Permission count: " . count($perms) . "\n";
echo "Sample permissions: " . implode(',', array_slice($perms, 0, 40)) . "\n";

try {
    if (method_exists($admin, 'modules')) {
        $mod = $admin->modules()->pluck('slug')->map(function($s){return trim(strtolower((string)$s));})->filter()->values()->toArray();
        echo "Module slugs: " . implode(',', $mod) . "\n";
    }
} catch (\Throwable $e) {
    // ignore
}
