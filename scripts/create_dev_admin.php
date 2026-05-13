<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use Spatie\Permission\Models\Role;

$email = $argv[1] ?? 'dev@example.com';
$password = $argv[2] ?? 'DevPass123!';
$first = $argv[3] ?? 'Dev';
$last = $argv[4] ?? 'User';
$status = $argv[5] ?? 'Active';

try {
    $existing = Admin::where('email', $email)->first();

    if ($existing) {
        $existing->first_name = $first;
        $existing->last_name = $last;
        $existing->password = $password;
        $existing->status = $status;
        $existing->save();
        $admin = $existing;
        $action = 'updated';
    } else {
        $admin = Admin::create([
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'password' => $password,
            'status' => $status,
        ]);
        $action = 'created';
    }

    $role = Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'admin']);
    $admin->assignRole($role);

    echo json_encode([
        'ok' => true,
        'action' => $action,
        'id' => $admin->id,
        'email' => $admin->email,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => (string) $e], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}
