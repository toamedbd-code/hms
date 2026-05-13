<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

$m = Menu::where('name', 'Item Charge')->first();
if (! $m) {
    echo "Item Charge menu not found\n";
    exit(0);
}

echo "Parent: id={$m->id} name={$m->name} route={$m->route} permission={$m->permission_name}\n";
$children = Menu::where('parent_id', $m->id)->orderBy('sorting')->get();
if ($children->isEmpty()) {
    echo "No children found under Item Charge\n";
} else {
    foreach ($children as $c) {
        echo "{$c->id} | {$c->name} | {$c->route} | {$c->permission_name}\n";
    }
}

// Also show any menus still named 'Hospital Test'
$old = Menu::where('name', 'Hospital Test')->get();
if ($old->isNotEmpty()) {
    echo "\nFound menus still named 'Hospital Test':\n";
    foreach ($old as $o) {
        echo "{$o->id} | {$o->name} | parent_id={$o->parent_id} | route={$o->route}\n";
    }
}

echo "\nDone.\n";

// List likely pathology-related menu items and their parent ids
$routes = [
    'itemcharge.index',
    'backend.pathologycategory.index',
    'backend.pathologyunit.index',
    'backend.parameterofpathology.index',
];
echo "\nPathology-related menu entries:\n";
foreach ($routes as $r) {
    $item = Menu::where('route', $r)->first();
    if ($item) {
        echo "{$item->id} | {$item->name} | route={$item->route} | parent_id={$item->parent_id}\n";
    } else {
        echo "- not found: {$r}\n";
    }
}

// Show parent records for the pathology items
$parents = Menu::whereIn('id', Menu::whereIn('route', $routes)->pluck('parent_id')->filter()->unique())->get();
if ($parents->isNotEmpty()) {
    echo "\nParent menu records for the above items:\n";
    foreach ($parents as $p) {
        echo "{$p->id} | {$p->name} | route={$p->route} | permission={$p->permission_name}\n";
    }
}

