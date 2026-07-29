<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$admin = App\Models\Admin::where('email', 'toamedbd@gmail.com')->first();
if (!$admin) {
    echo "not found\n";
    exit(1);
}
echo "roles:";
foreach ($admin->roles as $role) {
    echo " [{$role->name}]";
}
echo "\npermissions:";
foreach ($admin->getAllPermissions() as $p) {
    echo " [{$p->name}]";
}
echo "\n";
