<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use Spatie\Permission\Models\Permission as SpatiePermission;

$actor = Admin::find(3);
if (! $actor) {
    echo "Actor not found\n";
    exit(1);
}

echo "Actor ID: {$actor->id}\n";
echo "Roles: " . implode(',', $actor->getRoleNames()->toArray()) . "\n";
echo "hasRole('developer'): " . ($actor->hasRole('developer') ? 'true' : 'false') . "\n";

$permIds = $actor->getAllPermissions()->pluck('id')->toArray();
echo "Actor permission count: " . count($permIds) . "\n";
echo "Sample actor permission ids: " . implode(',', array_slice($permIds,0,20)) . "\n";

$moduleSlugs = [];
try {
    if (method_exists($actor, 'modules')) {
        $moduleSlugs = $actor->modules()->pluck('slug')->map(function($s){return trim(strtolower((string)$s));})->filter()->values()->toArray();
    }
} catch (\Throwable $e) {
    $moduleSlugs = [];
}
echo "Module slugs: " . implode(',', $moduleSlugs) . "\n";

// compute allowed permissions when submitting ALL permissions
$allSubmitted = SpatiePermission::where('guard_name', 'admin')->pluck('id')->toArray();
$permissionQuery = SpatiePermission::whereIn('id', $allSubmitted)->where('guard_name', 'admin');

if (!($actor && method_exists($actor, 'hasRole') && $actor->hasRole('developer'))) {
    $actorPermissionIds = collect();
    try {
        $actorPermissionIds = collect($permIds);
    } catch (\Throwable $e) {
        $actorPermissionIds = collect();
    }

    $allowedModuleSlugs = collect($moduleSlugs);

    if (!($allowedModuleSlugs->count() || $actorPermissionIds->count())) {
        $allowedPermissionIds = [];
    } else {
        $permissionQuery->where(function($q) use ($allowedModuleSlugs, $actorPermissionIds) {
            if ($allowedModuleSlugs->count()) {
                $q->whereIn('module_slug', $allowedModuleSlugs->toArray());
            }
            if ($actorPermissionIds->count()) {
                $q->orWhereIn('id', $actorPermissionIds->toArray());
            }
        });

        $allowedPermissionIds = $permissionQuery->pluck('id')->toArray();
    }
} else {
    $allowedPermissionIds = $permissionQuery->pluck('id')->toArray();
}

echo "Allowed permission count (computed): " . count($allowedPermissionIds) . "\n";
echo "Sample allowed ids: " . implode(',', array_slice($allowedPermissionIds,0,20)) . "\n";

// show if any ids are missing comparing allSubmitted to allowed
$diff = array_values(array_diff($allSubmitted, $allowedPermissionIds));
if (count($diff) > 0) {
    echo "Missing from allowed (count " . count($diff) . "): " . implode(',', array_slice($diff,0,50)) . "\n";
} else {
    echo "All submitted present in allowed.\n";
}
