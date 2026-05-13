<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Permission;

echo "== Permissions ==\n";
$permissions = Permission::query()
    ->where(function ($q) {
        $q->where('name', 'like', 'user-management%')
            ->orWhere('name', 'like', 'hospital-test%')
            ->orWhere('name', 'like', 'human-resource%')
            ->orWhere('name', 'like', 'item-charge%');
    })
    ->orderBy('id')
    ->get(['id', 'name', 'parent_id', 'sorting']);

foreach ($permissions as $p) {
    echo implode('|', [
        $p->id,
        $p->name,
        $p->parent_id ?? 'null',
        $p->sorting ?? 'null',
    ]) . "\n";
}

echo "\n== Menus ==\n";
$menus = Menu::query()
    ->whereIn('permission_name', ['user-management', 'hospital-test', 'human-resource', 'item-charge'])
    ->orderBy('id')
    ->get(['id', 'name', 'parent_id', 'permission_name', 'status']);

foreach ($menus as $m) {
    echo implode('|', [
        $m->id,
        $m->name,
        $m->parent_id ?? 'null',
        $m->permission_name,
        $m->status,
    ]) . "\n";
}
