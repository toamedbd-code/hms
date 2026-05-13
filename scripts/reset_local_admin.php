<?php
// Usage: php scripts/reset_local_admin.php email@example.com NewPassword
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

$email = $argv[1] ?? 'admin@gmail.com';
$password = $argv[2] ?? 'DevPass123!';

$admin = Admin::where('email', $email)->first();
if (! $admin) {
    echo "NOT FOUND: {$email}\n";
    exit(1);
}

$admin->password = $password;
$admin->status = 'Active';
$admin->save();

$ok = Hash::check($password, $admin->password) ? 'HASH OK' : 'HASH FAIL';
echo "UPDATED: {$email}\n";
echo "{$ok}\n";

exit(0);
