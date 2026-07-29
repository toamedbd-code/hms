<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ids = [514, 515];
foreach ($ids as $id) {
    try {
        $perm = \Spatie\Permission\Models\Permission::find($id);
        echo $id . ': ' . ($perm ? $perm->name : '<not found>') . PHP_EOL;
    } catch (Throwable $e) {
        echo $id . ': error - ' . $e->getMessage() . PHP_EOL;
    }
}
