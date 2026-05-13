<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$names = ['bom-list','production-order-list','work-order-list','fixed-asset-list','currency-list','exchange-rate-list','manufacturing-management','fixed-assets-management'];
foreach ($names as $n) {
    $exists = \App\Models\Permission::where('name', $n)->exists();
    echo $n . ': ' . ($exists ? 'FOUND' : 'MISSING') . "\n";
}
