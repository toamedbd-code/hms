<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Permission;
use Illuminate\Support\Facades\DB;

function to_slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = str_replace(['&', '/', '\\'], ' ', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
    $text = trim($text, '-');

    return $text;
}

$permissionLabels = [
    'Store Item Setup',
    'Stock Management',
    'Department Requisitions',
    'GRN Receive',
    'Store Adjustments',
    'Stock In/Out Entry',
    'Low Stock Report',
    'Stock Movement Report',
    'Monthly Closing',
];

DB::beginTransaction();

try {
    $parent = Permission::query()->firstOrCreate(
        ['name' => 'store-management', 'guard_name' => 'admin'],
        ['parent_id' => null, 'sorting' => 1]
    );

    $created = 0;
    $updated = 0;
    $exists = 0;

    foreach ($permissionLabels as $index => $label) {
        $slug = to_slug($label);
        $sorting = $index + 1;

        $permission = Permission::query()->where('guard_name', 'admin')->where('name', $slug)->first();

        if (!$permission) {
            Permission::query()->create([
                'name' => $slug,
                'guard_name' => 'admin',
                'parent_id' => $parent->id,
                'sorting' => $sorting,
            ]);
            $created++;
            echo "[CREATED] {$label} => {$slug}" . PHP_EOL;
            continue;
        }

        $needsUpdate = ((int) ($permission->parent_id ?? 0) !== (int) $parent->id)
            || ((int) ($permission->sorting ?? 0) !== $sorting);

        if ($needsUpdate) {
            $permission->parent_id = $parent->id;
            $permission->sorting = $sorting;
            $permission->save();
            $updated++;
            echo "[UPDATED] {$label} => {$slug}" . PHP_EOL;
        } else {
            $exists++;
            echo "[EXISTS]  {$label} => {$slug}" . PHP_EOL;
        }
    }

    DB::commit();

    try {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    } catch (\Throwable $e) {
        // ignore cache reset failures
    }

    echo PHP_EOL;
    echo 'Parent: store-management' . PHP_EOL;
    echo 'Created: ' . $created . PHP_EOL;
    echo 'Updated: ' . $updated . PHP_EOL;
    echo 'Already aligned: ' . $exists . PHP_EOL;
    echo 'Total ensured: ' . count($permissionLabels) . PHP_EOL;
    echo 'Done' . PHP_EOL;
} catch (Throwable $e) {
    DB::rollBack();
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
