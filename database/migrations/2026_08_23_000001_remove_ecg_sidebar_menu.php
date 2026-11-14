<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ecgMenuIds = DB::table('menus')
            ->where('name', 'ECG')
            ->pluck('id');

        if ($ecgMenuIds->isEmpty()) {
            return;
        }

        DB::table('menus')
            ->whereIn('parent_id', $ecgMenuIds)
            ->delete();

        DB::table('menus')
            ->whereIn('id', $ecgMenuIds)
            ->delete();
    }

    public function down(): void
    {
        // The ECG menu can be restored by running the MenuSeeder.
    }
};
