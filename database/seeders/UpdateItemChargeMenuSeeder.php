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
                'route' => null,
                'permission_name' => 'item-charge',
                'status' => 'Active',
            ]);

        // Also update any existing child menu labels under the Item Charge parent
        $parent = DB::table('menus')->where('name', 'Item Charge')->first();
        if ($parent) {
            DB::table('menus')->updateOrInsert(
                [
                    'parent_id' => $parent->id,
                    'route' => 'backend.maincategory.index',
                ],
                [
                    'name' => 'Main Category',
                    'icon' => 'list',
                    'description' => null,
                    'sorting' => 2,
                    'permission_name' => 'main-category-list',
                    'status' => 'Active',
                    'deleted_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $mainCategoryMenus = DB::table('menus')
                ->where('parent_id', $parent->id)
                ->where('route', 'backend.maincategory.index')
                ->orderBy('id')
                ->get();
            foreach ($mainCategoryMenus->skip(1) as $duplicate) {
                DB::table('menus')->where('id', $duplicate->id)->delete();
            }

            // Replace occurrences of 'Test' with 'Item' in child menu names
            DB::table('menus')
                ->where('parent_id', $parent->id)
                ->where('name', 'like', '%Test%')
                ->update(['name' => DB::raw("REPLACE(name, 'Test', 'Item')")]);

            // Keep the Item List pointed at the pathology catalog, not the legacy charge list.
            DB::table('menus')
                ->where('parent_id', $parent->id)
                ->where(function ($query) {
                    $query->whereIn('permission_name', ['test-list', 'itemcharge-list'])
                        ->orWhere('name', 'Item List');
                })
                ->update([
                    'permission_name' => 'itemcharge-list',
                    'name' => 'Item List',
                    'route' => 'backend.itemcharge.index',
                    'status' => 'Active',
                ]);
        }
    }
}
