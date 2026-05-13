<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$roles = \Spatie\Permission\Models\Role::all()->map(function($r){ return [$r->id, $r->name, $r->guard_name]; })->toArray();
foreach ($roles as $r) echo "id={$r[0]} name={$r[1]} guard={$r[2]}\n";
