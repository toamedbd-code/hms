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
    $side = getSideMenus($admin);
    if ($side instanceof \Illuminate\Support\Collection) {
        $side = $side->toArray();
    }

    $sideSummary = [];
    foreach ($side as $s) {
        $sideSummary[] = [
            'id' => $s['id'] ?? ($s->id ?? null),
            'name' => $s['name'] ?? ($s->name ?? null),
            'sorting' => $s['sorting'] ?? ($s->sorting ?? null),
        ];
    }

    // Mirror getSideMenus ordering: sorting then id
    $topMenus = Menu::whereNull('parent_id')->orderBy('sorting','asc')->orderBy('id','asc')->get(['id','name','sorting'])->toArray();

    echo json_encode(['admin_id' => $adminId, 'admin_email' => $admin->email ?? null, 'sideSummary' => $sideSummary, 'topMenus' => $topMenus], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => (string)$e], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
