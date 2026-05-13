<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Menu;

// Check table columns
$cols = DB::select("SHOW COLUMNS FROM menus");
echo '=== COLUMNS ===' . PHP_EOL;
foreach ($cols as $c) {
    echo $c->Field . ' (' . $c->Type . ')' . PHP_EOL;
}

echo PHP_EOL . '=== PHARMACY MENU ===' . PHP_EOL;
$row = DB::table('menus')->where('id', 28)->first();
echo json_encode((array)$row, JSON_PRETTY_PRINT) . PHP_EOL;

echo PHP_EOL . '=== CHILDREN OF 28 ===' . PHP_EOL;
$children = DB::table('menus')->where('parent_id', 28)->get();
foreach ($children as $c) {
    echo json_encode((array)$c) . PHP_EOL;
}

// Also check total menu count
$total = DB::table('menus')->count();
echo PHP_EOL . 'Total menus: ' . $total . PHP_EOL;
