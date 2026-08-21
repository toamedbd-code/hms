<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = App\Models\Admin::query()->first();
echo 'admin=' . ($admin ? $admin->email : 'none') . PHP_EOL;
echo 'role=' . ($admin && $admin->role ? $admin->role->name : 'none') . PHP_EOL;
if ($admin) {
    $perms = $admin->getAllPermissions()->pluck('name')->sort()->values();
    echo 'permissions=' . ($perms->isNotEmpty() ? $perms->implode(',') : 'none') . PHP_EOL;
}
echo 'menu_count=' . DB::table('menus')->count() . PHP_EOL;
foreach (DB::table('menus')->orderBy('sorting')->get() as $menu) {
    echo $menu->id . '|' . $menu->name . '|parent=' . ($menu->parent_id ?? 'root') . '|status=' . $menu->status . '|route=' . ($menu->route ?? '') . '|perm=' . ($menu->permission_name ?? '') . PHP_EOL;
}
