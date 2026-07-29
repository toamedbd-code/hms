<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use App\Models\Menu;

echo "Creating permissions and sidebar menu items for Radiology reporting...\n";

// Create permissions if they don't exist
$permissions = [
    'ultrasound-reporting' => 'Ultrasound Reporting',
    'xray-reporting' => 'X-ray Reporting',
    'pathology-reporting' => 'Pathology Reporting',
];

foreach ($permissions as $name => $label) {
    $p = Permission::firstOrCreate(['name' => $name]);
    if ($p->wasRecentlyCreated) {
        echo "Created permission: $name\n";
    } else {
        echo "Permission exists: $name\n";
    }
}

// Find parent menu 'Reporting' under 'Sample Collection' or top-level 'Reporting'
$parent = Menu::where('name', 'Reporting')
    ->whereNull('parent_id')
    ->orWhereHas('parent', function ($q) {
        $q->where('name', 'Sample Collection');
    })->first();

if (!$parent) {
    // fallback: create a Reporting parent under root
    $parent = Menu::firstOrCreate([
        'name' => 'Reporting',
        'route' => 'backend.reporting.index',
    ], [
        'icon' => 'report',
        'status' => 'Active',
        'sorting' => 500,
    ]);
    echo "Created parent Reporting menu.\n";
}

$existing = Menu::where('parent_id', $parent->id)->pluck('name')->map(fn($s) => strtolower($s))->all();

$items = [
    ['name' => 'Ultrasound Reporting', 'route' => 'backend.reporting.ultrasound', 'permission_name' => 'ultrasound-reporting'],
    ['name' => 'X-ray Reporting', 'route' => 'backend.reporting.xray', 'permission_name' => 'xray-reporting'],
    ['name' => 'Pathology Reporting', 'route' => 'backend.reporting.pathology', 'permission_name' => 'pathology-reporting'],
];

$i = 1;
foreach ($items as $it) {
    if (in_array(strtolower($it['name']), $existing)) {
        echo "Menu exists: {$it['name']}\n";
        continue;
    }

    $m = new Menu();
    $m->name = $it['name'];
    $m->route = $it['route'];
    $m->permission_name = $it['permission_name'];
    $m->parent_id = $parent->id;
    $m->icon = '';
    $m->status = 'Active';
    $m->sorting = 1000 + $i;
    $m->save();
    echo "Created menu: {$it['name']}\n";
    $i++;
}

echo "Done. Assign permissions to roles as needed via the admin panel.\n";
