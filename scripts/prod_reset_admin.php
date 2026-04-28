<?php
// Usage: php scripts/prod_reset_admin.php email@example.com NewPassword
// NOTE: This script is intended to be used via a protected CI SSH action or manually by an operator.
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;

if (! $email || ! $password) {
    echo "Usage: php scripts/prod_reset_admin.php email@example.com NewPassword\n";
    exit(1);
}

$admin = Admin::where('email', $email)->first();
if (! $admin) {
    echo "Admin not found: {$email}\n";
    exit(2);
}

$admin->password = $password; // model mutator handles bcrypt
$admin->status = 'Active';
$admin->save();

echo "Admin password updated for {$email}\n";
exit(0);
