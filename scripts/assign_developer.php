<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $admin = \App\Models\Admin::find(3);
    if (! $admin) {
        echo "Admin not found\n";
        exit(1);
    }

    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'admin']);
    $admin->assignRole($role);

    echo "Assigned developer role to admin: " . $admin->id . "\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
