<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

$menus = Menu::whereNull('deleted_at')->orderBy('id','asc')->get();

echo "Total menus: " . $menus->count() . "\n\n";

foreach ($menus as $m) {
    echo "ID: {$m->id} | Parent: {$m->parent_id} | Name: {$m->name} | Route: {$m->route} | Permission: {$m->permission_name} | Status: {$m->status}\n";
}

echo "\nTop-level active parents:\n";
$parents = Menu::whereNull('parent_id')->whereNull('deleted_at')->where('status','Active')->orderBy('sorting','asc')->get();
foreach ($parents as $p) {
    echo "ID: {$p->id} | Name: {$p->name} | Route: {$p->route} | Permission: {$p->permission_name} | Child Count: " . $p->childrens()->count() . "\n";
}

echo "\nDone\n";
