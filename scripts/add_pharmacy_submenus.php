<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// 1. Keep Pharmacy parent as direct route entry to avoid duplicate child route rows.
DB::table('menus')->where('id', 28)->update([
    'route' => 'backend.pharmacybill.index',
    'updated_at' => now(),
]);
echo 'Pharmacy parent route ensured' . PHP_EOL;

// 2. Add "Pharmacy Bills" child (original pharmacy route) with sorting=1
$existing = DB::table('menus')->where('parent_id', 28)->where('permission_name', 'pharmacy-bill-list')->first();
if (!$existing) {
    DB::table('menus')->insert([
        'name'            => 'Pharmacy Bills',
        'icon'            => 'file-text',
        'route'           => 'backend.pharmacybill.index',
        'module_slug'     => null,
        'description'     => null,
        'sorting'         => 1,
        'parent_id'       => 28,
        'permission_name' => 'pharmacy-bill-list',
        'status'          => 'Active',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
    echo 'Pharmacy Bills child created' . PHP_EOL;
} else {
    echo 'Pharmacy Bills child already exists (id=' . $existing->id . ')' . PHP_EOL;
}

// 3. Do not add account menus under Pharmacy (causes duplicate routes/permissions).
echo 'Audit Log/Supplier Payments child insertion skipped intentionally' . PHP_EOL;

// 4. Ensure role 5 has pharmacy-bill-list permission (it may not have it yet)
$role = Role::findById(5, 'admin');
$toAssign = ['pharmacy-bill-list'];
foreach ($toAssign as $permName) {
    $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'admin']);
    if (!$role->hasPermissionTo($perm)) {
        $role->givePermissionTo($perm);
        echo $permName . ':NEWLY_ASSIGNED' . PHP_EOL;
    } else {
        echo $permName . ':ALREADY_HAS' . PHP_EOL;
    }
}

// 5. Clear permission cache
app()['cache']->forget('spatie.permission.cache');
echo 'cache cleared' . PHP_EOL;
