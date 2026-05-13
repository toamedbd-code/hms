<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$role = Role::findById(5, 'admin');
echo 'role=' . $role->name . PHP_EOL;

$permNames = [
    'chart-of-accounts',
    'ledger',
    'account-balances',
    'currency-list',
    'exchange-rate-list',
    'activity-log-view',
    'supplier-payment-list',
];

foreach ($permNames as $name) {
    $perm = Permission::firstOrCreate(
        ['name' => $name, 'guard_name' => 'admin']
    );
    if (!$role->hasPermissionTo($perm)) {
        $role->givePermissionTo($perm);
        echo $name . ':NEWLY_ASSIGNED' . PHP_EOL;
    } else {
        echo $name . ':ALREADY_HAS' . PHP_EOL;
    }
}

app()['cache']->forget('spatie.permission.cache');
echo 'cache cleared' . PHP_EOL;
