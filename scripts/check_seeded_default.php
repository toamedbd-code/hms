<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;

$email = 'toamedbd@gmail.com';
try {
    $admin = Admin::where('email', $email)->first();
    if (! $admin) {
        echo "Default admin not found: {$email}\n";
        exit(1);
    }

    echo "Found admin: id={$admin->id}, email={$admin->email}\n";
    try {
        $roles = $admin->getRoleNames()->toArray();
        echo "Roles: " . implode(',', $roles) . "\n";
    } catch (Throwable $e) {
        echo "Could not read roles: " . $e->getMessage() . "\n";
    }

    echo "Login credentials (seeded): {$email} / zxczxc\n";
    exit(0);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
