<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;

$email = 'playwright@test.local';
$password = 'Password123!';

$existing = Admin::where('email', $email)->first();
if ($existing) {
    echo "Admin already exists: $email\n";
    exit(0);
}

$admin = new Admin();
$admin->first_name = 'Playwright';
$admin->last_name = 'Tester';
$admin->email = $email;
$admin->phone = '0000000000';
$admin->status = 'Active';
$admin->password = $password; // mutator will bcrypt
$admin->save();

echo "Created admin: $email with password: $password\n";
