<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

$rows = Menu::selectRaw('route, COUNT(*) as cnt')
    ->whereNotNull('route')
    ->whereRaw('TRIM(route) <> ""')
    ->groupBy('route')
    ->having('cnt', '>', 1)
    ->get()
    ->toArray();

echo json_encode($rows);
