<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

$menu = Menu::where('route', 'like', '%chargeunittype%')->orWhere('name', 'like', '%Charge%Unit%')->orWhere('permission_name', 'like', '%charge%unit%')->first();
var_dump($menu ? $menu->toArray() : null);
