<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
$dev = Role::where('name','developer')->get();
if ($dev->count() === 0) {
    echo "No developer role found\n";
} else {
    foreach ($dev as $d) {
        echo "role id={$d->id} name={$d->name} guard_name={$d->guard_name}\n";
    }
}

$all = Role::all();
echo "Total roles: " . $all->count() . "\n";
foreach ($all as $r) {
    echo "id={$r->id} name={$r->name} guard={$r->guard_name}\n";
}
