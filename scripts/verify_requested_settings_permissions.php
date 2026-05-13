<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Permission;

function to_slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = str_replace(['&', '/', '\\'], ' ', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
    return trim($text, '-');
}

$permissionLabels = [
    'Attendance Module Setting',
    'Machine Integration Setting',
    'Payroll Module Setting',
    'Reporting Module Setting',
    'InvoiceDesign List',
    'BillingDoctor List',
    'Dashboard Settings',
    'Report Settings',
    'CMS Setting',
    'Attendance Settings',
    'Activity Logs',
    'Activity Logs Print',
    'General Setting',
    'Prefix Setting',
    'SMS Setting',
    'Other Setting',
    'bKash Settings',
];

$parent = Permission::query()->where('guard_name', 'admin')->where('name', 'settings-management')->first();
if (!$parent) {
    echo 'settings-management parent not found' . PHP_EOL;
    exit(1);
}

$missing = 0;

foreach ($permissionLabels as $index => $label) {
    $slug = to_slug($label);
    $expectedSorting = $index + 1;

    $permission = Permission::query()
        ->where('guard_name', 'admin')
        ->where('name', $slug)
        ->first();

    if (!$permission) {
        echo "[MISSING] {$label} => {$slug}" . PHP_EOL;
        $missing++;
        continue;
    }

    $okParent = ((int) ($permission->parent_id ?? 0) === (int) $parent->id);
    $okSorting = ((int) ($permission->sorting ?? 0) === $expectedSorting);

    echo sprintf(
        '[OK=%s] %s => %s | parent=%s | sorting=%d',
        ($okParent && $okSorting) ? 'yes' : 'partial',
        $label,
        $slug,
        $okParent ? 'settings-management' : ('id:' . ((int) ($permission->parent_id ?? 0))),
        (int) ($permission->sorting ?? 0)
    ) . PHP_EOL;
}

echo PHP_EOL;
echo 'Missing count: ' . $missing . PHP_EOL;
echo 'Done' . PHP_EOL;
