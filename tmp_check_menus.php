<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$menus = App\Models\Menu::query()->whereNull('parent_id')->whereNull('deleted_at')->where('status','Active')->orderBy('sorting')->orderBy('id')->get();
echo "active root menus: " . $menus->count() . "\n";
foreach ($menus as $m) {
    echo $m->id . ' ' . $m->name . ' route=' . ($m->route ?? 'NULL') . ' perm=' . ($m->permission_name ?? 'NULL') . "\n";
}
