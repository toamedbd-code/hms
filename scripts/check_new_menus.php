<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$names = ['Manufacturing', 'Fixed Assets', 'Currency', 'Exchange Rates', 'Bill of Materials'];
foreach ($names as $n) {
    $m = \App\Models\Menu::where('name', $n)->first();
    if ($m) {
        echo "FOUND: $n => id={$m->id}, route={$m->route}, permission={$m->permission_name}\n";
    } else {
        echo "MISSING: $n\n";
    }
}
