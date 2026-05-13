<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

$parent = Menu::whereRaw("LOWER(name) LIKE ?", ['%account%'])->whereNull('parent_id')->first();
if (!$parent) { echo "Account parent menu not found\n"; exit(1); }

echo "Parent ID: {$parent->id} | Name: {$parent->name}\n\n";
$children = Menu::where('parent_id', $parent->id)->orderBy('sorting')->orderBy('id')->get();
foreach ($children as $c) {
    echo "ID: {$c->id} | Name: {$c->name} | perm:" . ($c->permission_name ?? '') . " | route:{$c->route} | module:{$c->module_slug} | status:{$c->status} | sorting:{$c->sorting}\n";
}

echo "\nTotal children in DB: " . $children->count() . "\n";
