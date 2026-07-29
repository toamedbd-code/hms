<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateItemChargeMenuSeeder extends Seeder
{
    public function run()
    {
        // Normalize Item Charge label and permission slug.
        DB::table('menus')
            ->whereIn('permission_name', ['hospital-test', 'item-charge'])
            ->orWhere('name', 'Hospital Test')
            ->update([
                'name' => 'Item Charge',
                'route' => 'backend.Itemcharge.index',
                'permission_name' => 'item-charge',
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

            // Fix legacy Item List permission slug for existing installations.
            DB::table('menus')
                ->where('parent_id', $parent->id)
                ->where('permission_name', 'test-list')
                ->update([
                    'permission_name' => 'itemcharge-list',
                    'name' => 'Item List',
                    'route' => 'backend.itemcharge.index',
                    'status' => 'Active',
                ]);
        }
    }
}
