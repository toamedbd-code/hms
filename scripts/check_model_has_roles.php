<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('model_has_roles')->where('model_type', 'App\\Models\\Admin')->where('model_id', 3)->get();
if ($rows->count() === 0) {
    echo "No model_has_roles rows for Admin id=3\n";
} else {
    foreach ($rows as $r) {
        echo "role_id={$r->role_id} model_type={$r->model_type} model_id={$r->model_id}\n";
    }
}

$roles = DB::table('roles')->whereIn('id', $rows->pluck('role_id')->toArray())->get();
foreach ($roles as $role) {
    echo "role id={$role->id} name={$role->name} guard_name={$role->guard_name}\n";
}
