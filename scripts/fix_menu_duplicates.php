<?php
// Usage: php scripts/fix_menu_duplicates.php
// Removes duplicate menu children created under the 'Management' parent when
// the same route/name exists elsewhere (Settings, Account Management, etc.).

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

echo "Scanning for 'Management' parent...\n";

$management = Menu::where('name', 'Management')->whereNull('parent_id')->first();
if (! $management) {
    echo "No 'Management' parent menu found. Nothing to do.\n";
    exit(0);
}

echo "Found Management (id={$management->id}). Checking children...\n";

$children = Menu::where('parent_id', $management->id)->whereNull('deleted_at')->get();
$deleted = [];
$skipped = [];

foreach ($children as $child) {
    $route = trim((string) $child->route);
    $name = $child->name;

    $duplicate = null;

    if ($route !== '') {
        $duplicate = Menu::where('route', $route)
            ->where('id', '!=', $child->id)
            ->whereNull('deleted_at')
            ->first();
    }

    if (! $duplicate) {
        // fallback: check by name if route not helpful
        $duplicate = Menu::where('name', $name)
            ->where('id', '!=', $child->id)
            ->whereNull('deleted_at')
            ->first();
    }

    if ($duplicate) {
        // If a duplicate exists and it's not the same parent, remove the
        // child under Management to avoid duplication.
        if ($duplicate->parent_id !== $management->id) {
            $child->deleted_at = date('Y-m-d H:i:s');
            $child->status = 'Deleted';
            $child->save();
            $deleted[] = $name . ' (' . ($route ?: 'no-route') . ') => kept under parent_id=' . $duplicate->parent_id;
            echo "Removed duplicate '{$name}' (route={$route}) from Management.\n";
            continue;
        }
    }

    $skipped[] = $name;
}

// If Management has no active children left, remove it as well
$remaining = Menu::where('parent_id', $management->id)->whereNull('deleted_at')->count();
if ($remaining === 0) {
    $management->deleted_at = date('Y-m-d H:i:s');
    $management->status = 'Deleted';
    $management->save();
    echo "Management parent had no remaining children; it was removed.\n";
}

echo "\nSummary:\n";
echo "Removed duplicates: " . count($deleted) . "\n";
foreach ($deleted as $d) {
    echo " - $d\n";
}
echo "Skipped (left in Management): " . count($skipped) . "\n";
foreach ($skipped as $s) {
    echo " - $s\n";
}

echo "Done. Re-run Menu tests or open the admin UI to verify.\n";
