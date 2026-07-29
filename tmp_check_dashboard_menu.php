<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$menus = App\Models\Menu::query()->where(function($q){$q->where('name','Dashboard')->orWhere('route','backend.dashboard');})->withTrashed()->get();
echo "dashboard menu count=" . $menus->count() . "\n";
foreach ($menus as $m) {
    echo $m->id . ' ' . $m->name . ' route=' . ($m->route ?? 'NULL') . ' status=' . ($m->status ?? 'NULL') . ' deleted=' . ($m->deleted_at ? 'yes' : 'no') . "\n";
}
