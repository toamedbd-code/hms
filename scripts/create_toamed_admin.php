<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;

$email = 'toamedbd@gmail.com';
$password = 'zxczxc';

try {
    $admin = Admin::where('email', $email)->first();

    if (! $admin) {
        $admin = Admin::create([
            'first_name' => 'Toamed',
            'last_name' => 'Admin',
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

    echo "\nLogin: {$email}\nPassword: {$password}\n";
    exit(0);
} catch (\Throwable $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
