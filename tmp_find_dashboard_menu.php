<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$found = App\Models\Menu::query()->where(function ($q) {
    $q->where('name', 'Dashboard')
      ->orWhere('route', 'backend.dashboard');
})->get();
echo 'count=' . $found->count() . "\n";
foreach ($found as $m) {
    echo $m->id . ' name=' . $m->name . ' route=' . ($m->route ?? 'NULL') . ' status=' . ($m->status ?? 'NULL') . ' deleted=' . ($m->deleted_at ? 'yes' : 'no') . "\n";
}
