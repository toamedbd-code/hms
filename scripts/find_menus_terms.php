<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

$keywords = ['attendance', 'hr', 'cms', 'inbox', 'doctor', 'portal', 'dashboard'];

try {
    $query = Menu::query();
    foreach ($keywords as $k) {
        $query->orWhere('name', 'LIKE', '%' . $k . '%');
    }

    $matches = $query->orderBy('sorting', 'asc')->get(['id', 'name', 'route', 'parent_id', 'sorting', 'module_slug', 'permission_name', 'status'])->toArray();

    // Also output the top-level menus ordered by sorting, limit to first 20 for context
    $topMenus = Menu::whereNull('parent_id')->orderBy('sorting', 'asc')->limit(40)->get(['id','name','route','sorting','module_slug','permission_name'])->toArray();

    echo json_encode(['matches' => $matches, 'topMenus' => $topMenus], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => (string)$e], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
