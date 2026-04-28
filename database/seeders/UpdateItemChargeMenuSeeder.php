<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateItemChargeMenuSeeder extends Seeder
{
    public function run()
    {
        // Update any existing menu entries that reference the old Hospital Test
        DB::table('menus')
            ->where('permission_name', 'hospital-test')
            ->orWhere('name', 'Hospital Test')
            ->update([
                'name' => 'Item Charge',
                'route' => 'backend.Itemcharge.index',
                'status' => 'Active',
            ]);

        // Also update any existing child menu labels under the Item Charge parent
        $parent = DB::table('menus')->where('name', 'Item Charge')->first();
        if ($parent) {
            // Replace occurrences of 'Test' with 'Item' in child menu names
            DB::table('menus')
                ->where('parent_id', $parent->id)
                ->where('name', 'like', '%Test%')
                ->update(['name' => DB::raw("REPLACE(name, 'Test', 'Item')")]);
        }
    }
}
