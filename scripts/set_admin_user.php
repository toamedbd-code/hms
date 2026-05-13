<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = 'admin@gmail.com';
$password = 'asdasd';

try {
    $userModel = '\\App\\Models\\User';
    $roleModel = '\\Spatie\\Permission\\Models\\Role';

    $user = $userModel::where('email', $email)->first();

    if (! $user) {
        $user = $userModel::create([
            'name' => 'Administrator',
            'email' => $email,
            'password' => $app['hash']->make($password),
            'email_verified_at' => now(),
        ]);
        echo "Created user: {$email}\n";
    } else {
        $user->password = $app['hash']->make($password);
        $user->email_verified_at = now();
        $user->save();
        echo "Updated password for existing user: {$email}\n";
    }

    if (class_exists($roleModel)) {
        $role = $roleModel::firstOrCreate(['name' => 'admin']);
        if (method_exists($user, 'assignRole')) {
            $user->assignRole($role->name);
            echo "Assigned role 'admin'.\n";
        } else {
            echo "User model missing assignRole; skipped role assignment.\n";
        }
    } else {
        echo "Spatie Role model not found; skipped role assignment.\n";
    }

    echo "\nLogin: {$email}\nPassword: {$password}\n";

    exit(0);
} catch (\Throwable $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
