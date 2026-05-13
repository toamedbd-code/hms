<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Menu;

$parent = Menu::whereNull('parent_id')->where('name', 'like', '%Pharma%')->first();
if (!$parent) {
    echo 'Pharmacy parent not found' . PHP_EOL;
    // list all parents
    $parents = Menu::whereNull('parent_id')->orderBy('sorting')->get(['id', 'name', 'permission_name']);
    foreach ($parents as $p) {
        echo 'parent:id=' . $p->id . ',name=' . $p->name . ',perm=' . ($p->permission_name ?? 'null') . PHP_EOL;
    }
    exit(0);
}

echo 'parent:id=' . $parent->id . ',name=' . $parent->name . ',perm=' . ($parent->permission_name ?? 'null') . PHP_EOL;

$children = Menu::where('parent_id', $parent->id)->orderBy('sorting')->get(['id', 'name', 'permission_name', 'sorting']);
foreach ($children as $c) {
    echo 'child:id=' . $c->id . ',name=' . $c->name . ',perm=' . ($c->permission_name ?? 'null') . ',sort=' . $c->sorting . PHP_EOL;
}
