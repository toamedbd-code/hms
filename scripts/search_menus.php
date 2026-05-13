<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

$terms = ['journal', 'asset', 'fixed', 'bom', 'mrp', 'currency', 'exchange', 'journal-entry', 'account'];
foreach ($terms as $t) {
    $q = Menu::whereRaw('LOWER(name) LIKE ?', ["%{$t}%"])->orWhereRaw('LOWER(route) LIKE ?', ["%{$t}%"])->get();
    echo "Term: {$t} -> Found: " . $q->count() . "\n";
    foreach ($q as $m) {
        echo "  {$m->id} | {$m->name} | perm:{$m->permission_name} | route:{$m->route} | parent:{$m->parent_id} | module:{$m->module_slug}\n";
    }
}
