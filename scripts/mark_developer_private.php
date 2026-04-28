<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use App\Models\Permission;
use Spatie\Permission\Models\Role;

echo "Marking developer role private and assigning to default admins...\n";

try {
    $dev = Role::where('name', 'developer')->where('guard_name', 'admin')->first();
    if (! $dev) {
        $dev = Role::create(['name' => 'developer', 'guard_name' => 'admin']);
        echo "Created developer role\n";
    }

    $dev->is_private = true;
    $dev->save();
    echo "Developer role marked private (id={$dev->id}).\n";

    // Give developer all permissions so the seed semantics are preserved
    try {
        $allPermissions = Permission::pluck('name')->toArray();
        if (!empty($allPermissions)) {
            $dev->syncPermissions($allPermissions);
            echo "Developer role synced with all permissions.\n";
        }
    } catch (Throwable $e) {
        echo "Failed to sync permissions: " . $e->getMessage() . "\n";
    }

    $emails = ['toamedbd@gmail.com', 'admin@gmail.com'];
    foreach ($emails as $email) {
        $admin = Admin::where('email', $email)->first();
        if ($admin) {
            $admin->assignRole($dev);
            echo "Assigned developer role to {$email} (admin id={$admin->id}).\n";
        } else {
            echo "Admin with email {$email} not found.\n";
        }
    }

    echo "Done.\n";
} catch (Throwable $th) {
    echo "Error: " . $th->getMessage() . "\n";
    exit(1);
}
