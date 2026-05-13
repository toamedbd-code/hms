<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Before update:\n";
$rows = DB::table('menus')->where('route', 'backend.doctor-summary.index')->get();
foreach ($rows as $r) {
    echo "id={$r->id} name={$r->name} route={$r->route}\n";
}

$updated = DB::table('menus')->where('route', 'backend.doctor-summary.index')->update(['name' => 'Report Summary']);

echo "Updated rows: $updated\n";

echo "After update:\n";
$rows = DB::table('menus')->where('route', 'backend.doctor-summary.index')->get();
foreach ($rows as $r) {
    echo "id={$r->id} name={$r->name} route={$r->route}\n";
}
