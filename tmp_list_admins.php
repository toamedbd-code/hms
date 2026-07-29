<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
foreach (App\Models\Admin::limit(20)->get() as $a) {
    echo $a->id . ' ' . ($a->email ?? '(no email)') . ' ' . ($a->name ?? '(no name)') . "\n";
}
