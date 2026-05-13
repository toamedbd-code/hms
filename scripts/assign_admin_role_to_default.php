<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = \App\Models\Admin::where('email', 'toamedbd@gmail.com')->first();
if (!$admin) {
    echo "Default admin not found\n";
    exit(1);
}
try {
    $admin->assignRole('Admin');
    echo "Assigned Admin role to {$admin->email}\n";
} catch (\Throwable $e) {
    echo "Error assigning role: " . $e->getMessage() . "\n";
}
