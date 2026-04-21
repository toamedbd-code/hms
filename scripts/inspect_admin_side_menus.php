<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Admin;

$adminId = isset($argv[1]) ? (int) $argv[1] : 1;
$admin = Admin::find($adminId);

if (!$admin) {
    echo json_encode(['error' => 'Admin not found', 'admin_id' => $adminId], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(0);
}

try {
    $menus = Menu::select('id', 'name', 'route', 'parent_id', 'sorting', 'module_slug', 'permission_name', 'status', 'icon', 'description', 'created_at', 'updated_at')
        ->orderBy('sorting', 'asc')
        ->get()
        ->toArray();
} catch (Throwable $e) {
    $menus = ['error' => (string) $e];
}

try {
    $side = getSideMenus($admin);
    if ($side instanceof \Illuminate\Support\Collection) {
        $side = $side->toArray();
    }
} catch (Throwable $e) {
    $side = ['error' => (string) $e];
}

$output = [
    'admin_id' => $adminId,
    'admin_email' => $admin->email ?? null,
    'menus' => $menus,
    'sideMenus' => $side,
];

// Find specific suspicious menus by keywords to help debugging ordering
$keywords = ['attendance', 'hr', 'cms', 'inbox', 'doctor', 'portal'];
$matches = [];
if (is_array($menus)) {
    foreach ($menus as $m) {
        $name = strtolower((string) ($m['name'] ?? ''));
        foreach ($keywords as $k) {
            if (mb_stripos($name, $k) !== false) {
                $matches[] = $m;
                break;
            }
        }
    }
}

$output['matchedMenus'] = array_values($matches);

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
