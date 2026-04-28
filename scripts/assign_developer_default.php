<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use Spatie\Permission\Models\Role;

$email = 'admin@gmail.com';
$password = 'asdasd';

try {
    $admin = Admin::where('email', $email)->first();

    if (! $admin) {
        $admin = Admin::create([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => $email,
            'password' => $password,
            'status' => 'Active',
        ]);
        echo "Created admin: {$email}\n";
    } else {
        $admin->password = $password;
        $admin->save();
        echo "Updated admin password: {$email}\n";
    }

    $role = Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'admin']);
    $admin->assignRole($role);

    echo "Assigned developer role to admin: " . $admin->id . "\n";
    echo "Roles: " . implode(',', $admin->getRoleNames()->toArray()) . "\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
