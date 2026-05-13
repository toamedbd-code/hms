<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use App\Models\Menu;
use App\Models\Module;
use App\Models\Permission;

$email = $argv[1] ?? 'toamedbd@gmail.com';

try {
    $admin = Admin::where('email', $email)->first();
    if (! $admin) {
        echo "Admin not found: {$email}\n";
        exit(1);
    }

    $permissionSlugs = Permission::query()
        ->whereNotNull('module_slug')
        ->pluck('module_slug')
        ->map(function ($slug) {
            return strtolower(trim((string) $slug));
        })
        ->filter(function ($slug) {
            return $slug !== '';
        });

    $menuSlugs = Menu::query()
        ->whereNotNull('module_slug')
        ->pluck('module_slug')
        ->map(function ($slug) {
            return strtolower(trim((string) $slug));
        })
        ->filter(function ($slug) {
            return $slug !== '';
        });

    $allSlugs = $permissionSlugs
        ->merge($menuSlugs)
        ->unique()
        ->values();

    if ($allSlugs->isEmpty()) {
        echo "No module_slug data found in permissions/menus; modules table not changed.\n";
        echo "Keeping sidebar stable via permission-based fallback.\n";
        exit(0);
    }

    $created = 0;
    foreach ($allSlugs as $slug) {
        $name = ucwords(str_replace(['-', '_'], ' ', $slug));

        $module = Module::firstOrNew(['slug' => $slug]);
        $wasNew = ! $module->exists;

        $module->name = $name;
        if (! $module->description) {
            $module->description = "Auto-generated from permission/menu module_slug: {$slug}";
        }
        $module->save();

        if ($wasNew) {
            $created++;
        }
    }

    $moduleIds = Module::query()->pluck('id')->toArray();
    $admin->modules()->sync($moduleIds);

    echo "Module slugs found: " . $allSlugs->count() . "\n";
    echo "Modules created this run: {$created}\n";
    echo "Total modules in table: " . Module::count() . "\n";
    echo "Assigned modules to admin {$email}: " . count($moduleIds) . "\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
