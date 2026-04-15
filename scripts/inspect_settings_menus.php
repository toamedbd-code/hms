<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = App\Models\Menu::query()
    ->whereNull('deleted_at')
    ->where(function ($q) {
        $q->where('name', 'like', '%Setting%')
          ->orWhere('name', 'like', '%Settings%')
          ->orWhere('name', 'like', '%Activity%');
    })
    ->orderBy('parent_id')
    ->orderBy('sorting')
    ->orderBy('id')
    ->get(['id', 'parent_id', 'name', 'route', 'permission_name', 'sorting']);

foreach ($rows as $row) {
    echo json_encode($row->toArray(), JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
