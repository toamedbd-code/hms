<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\Route;
$names = ['backend.dashboard','dashboard'];
foreach ($names as $name) {
    echo $name . ' has? ' . (Route::has($name) ? 'yes' : 'no') . "\n";
}
