<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

try {
    $menus = Menu::where('route', 'like', '%testpathology%')->get();
    if ($menus->isEmpty()) {
        echo "No menu routes containing 'testpathology' found.\n";
        exit(0);
    }

    foreach ($menus as $menu) {
        $old = $menu->route;
        $new = str_replace('testpathology', 'itemcharge', $old);
        $menu->route = $new;
        $menu->save();
        echo "Updated menu id={$menu->id} route: {$old} -> {$new}\n";
    }

    // Also update any orphaned parent names if necessary
    $itemChargeParents = Menu::where('name', 'Item Charge')->get();
    if ($itemChargeParents->count() > 1) {
        $target = $itemChargeParents->sortBy('id')->first();
        Menu::where('name', 'Item Charge')->where('id', '!=', $target->id)->update(['status' => 'Inactive']);
        echo "Normalized Item Charge parents, kept id={$target->id} active.\n";
    }

    echo "Done.\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
