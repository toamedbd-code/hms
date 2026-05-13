<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;

class ReparentItemChargeChildrenSeeder extends Seeder
{
    public function run()
    {
        $routes = [
            'backend.itemcharge.index',
            'backend.pathologycategory.index',
            'backend.pathologyunit.index',
            'backend.parameterofpathology.index',
        ];

        // Choose canonical Item Charge parent (lowest id)
        $target = Menu::where('name', 'Item Charge')->orderBy('id')->first();
        if (! $target) {
            $this->command->info('No Item Charge parent found; skipping.');
            return;
        }

        // Update parent_id for pathology-related menu items
        Menu::whereIn('route', $routes)->update(['parent_id' => $target->id]);

        // Deactivate any other duplicate Item Charge parents (except target)
        Menu::where('name', 'Item Charge')->where('id', '!=', $target->id)->update(['status' => 'Inactive']);

        $this->command->info('Reparented pathology items to Item Charge id=' . $target->id);
    }
}
