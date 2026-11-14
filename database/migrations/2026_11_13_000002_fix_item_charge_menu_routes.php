<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parentIds = DB::table('menus')
            ->whereNull('parent_id')
            ->whereIn('permission_name', ['item-charge', 'hospital-test'])
            ->pluck('id');

        if ($parentIds->isEmpty()) {
            $parentIds = DB::table('menus')
                ->whereNull('parent_id')
                ->whereIn('name', ['Item Charge', 'Hospital Test'])
                ->pluck('id');
        }

        if ($parentIds->isEmpty()) {
            return;
        }

        DB::table('menus')
            ->whereIn('id', $parentIds)
            ->update([
                'name' => 'Item Charge',
                'route' => null,
                'permission_name' => 'item-charge',
                'updated_at' => now(),
            ]);

        DB::table('menus')
            ->whereIn('parent_id', $parentIds)
            ->where(function ($query) {
                $query->whereIn('permission_name', ['test-list', 'itemcharge-list'])
                    ->orWhere('name', 'Item List')
                    ->orWhere('route', 'backend.Itemcharge.index');
            })
            ->update([
                'name' => 'Item List',
                'route' => 'backend.itemcharge.index',
                'permission_name' => 'itemcharge-list',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
    }
};
