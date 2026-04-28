<?php
// Usage: php scripts/relocate_management_leftovers.php
// Moves remaining items under 'Management' to proper parent menus and
// normalizes their route names where appropriate.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

echo "Locating 'Management' parent...\n";

$management = Menu::where('name', 'Management')->whereNull('parent_id')->first();
if (! $management) {
    echo "No 'Management' parent found. Exiting.\n";
    exit(0);
}

echo "Found Management (id={$management->id}).\n";

$mapping = [
    'Permission List' => [
        'target_key' => ['permission_name' => 'role-management', 'name' => 'Role Management'],
        'normalize_route' => null,
    ],
    'System Settings' => [
        'target_key' => ['permission_name' => 'settings-management', 'name' => 'Settings'],
        // normalize to canonical route used by Settings seeders
        'normalize_route' => 'backend.websetting.create',
    ],
    'Journal Entries' => [
        'target_key' => ['permission_name' => 'account-management', 'name' => 'Account Management'],
        // keep existing route if already correct
        'normalize_route' => null,
    ],
];

$moved = [];
$skipped = [];

foreach ($mapping as $name => $spec) {
    $child = Menu::where('parent_id', $management->id)
        ->where('name', $name)
        ->whereNull('deleted_at')
        ->first();

    if (! $child) {
        $skipped[] = "$name (not found under Management)";
        continue;
    }

    // find target parent
    $target = null;
    if (! empty($spec['target_key']['permission_name'])) {
        $target = Menu::where('permission_name', $spec['target_key']['permission_name'])->whereNull('deleted_at')->first();
    }
    if (! $target && ! empty($spec['target_key']['name'])) {
        $target = Menu::where('name', $spec['target_key']['name'])->whereNull('deleted_at')->first();
    }

    if (! $target) {
        $skipped[] = "$name (no target parent found)";
        continue;
    }

    // move the child
    $oldParent = $child->parent_id;
    $child->parent_id = $target->id;

    if (! empty($spec['normalize_route'])) {
        $child->route = $spec['normalize_route'];
    }

    $child->save();

    $moved[] = "$name => parent '{$target->name}' (id={$target->id})";
    echo "Moved '{$name}' from parent_id={$oldParent} to parent_id={$target->id}.\n";
}

// If Management has no active children left, remove it
$remaining = Menu::where('parent_id', $management->id)->whereNull('deleted_at')->count();
if ($remaining === 0) {
    $management->deleted_at = date('Y-m-d H:i:s');
    $management->status = 'Deleted';
    $management->save();
    echo "Management parent had no remaining children and was removed.\n";
}

echo "\nSummary:\n";
echo "Moved: " . count($moved) . "\n";
foreach ($moved as $m) {
    echo " - $m\n";
}

echo "Skipped: " . count($skipped) . "\n";
foreach ($skipped as $s) {
    echo " - $s\n";
}

echo "Done. Run php scripts/test_management_pages.php to verify.\n";
