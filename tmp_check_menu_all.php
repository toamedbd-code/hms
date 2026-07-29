<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$all = App\Models\Menu::query()->whereNull('parent_id')->orderBy('sorting')->orderBy('id')->get();
echo 'total root menus: '.$all->count()."\n";
foreach ($all as $m) {
    echo $m->id . ' ' . $m->name . ' route=' . ($m->route ?? 'NULL') . ' status=' . ($m->status ?? 'NULL') . ' deleted=' . ($m->deleted_at ? 'yes' : 'no') . "\n";
}
