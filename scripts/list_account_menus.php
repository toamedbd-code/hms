<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

// Find Account Management parent menu
$parent = Menu::query()
    ->whereNull('parent_id')
    ->where(function ($q) {
        $q->where('name', 'like', '%Account%')
          ->orWhere('name', 'like', '%account%');
    })
    ->first();

if (!$parent) {
    $parents = Menu::query()->whereNull('parent_id')->get(['id', 'name', 'permission_name']);
    foreach ($parents as $p) {
        echo 'parent:id=' . $p->id . ',name=' . $p->name . ',perm=' . ($p->permission_name ?? 'null') . PHP_EOL;
    }
    exit(0);
}

echo 'parent:id=' . $parent->id . ',name=' . $parent->name . PHP_EOL;

$children = Menu::query()->where('parent_id', $parent->id)->orderBy('sorting')->get(['id', 'name', 'permission_name']);
foreach ($children as $c) {
    echo 'child:id=' . $c->id . ',name=' . $c->name . ',perm=' . ($c->permission_name ?? 'null') . PHP_EOL;
}
