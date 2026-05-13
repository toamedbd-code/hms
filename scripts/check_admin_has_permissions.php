<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = \App\Models\Admin::first();
if (!$admin) { echo "No admin user\n"; exit(1); }
$names = ['account-management','chart-of-accounts','ledger','account-balances','activity-log-view','currency-list','exchange-rate-list','journal-entry'];
foreach ($names as $n) {
    try {
        $has = $admin->hasPermissionTo($n);
        echo $n . ': ' . ($has ? 'YES' : 'NO') . "\n";
    } catch (\Throwable $e) {
        echo $n . ': ERROR (' . $e->getMessage() . ')\n';
    }
}
