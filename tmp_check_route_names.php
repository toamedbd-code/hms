<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$names = [];
foreach (app('router')->getRoutes() as $route) {
    $name = $route->getName();
    if ($name) {
        $names[] = $name;
    }
}
sort($names);
foreach ($names as $name) {
    if (preg_match('/backend\.billing\.Page|billing\.Page|backend\.sitepurchase\.index|backend\.accounts\.vendor-payment\.index|backend\.cash-counter\.index|backend\.staffattendance\.salary-sheet|backend\.staffattendance\.duty-roster|backend\.dashboard/', $name)) {
        echo $name . "\n";
    }
}
