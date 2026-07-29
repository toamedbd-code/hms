<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')->updateOrInsert(
            ['route' => 'backend.cash-counter.index'],
            [
                'name' => 'Cash Counter',
                'icon' => 'credit-card',
                'description' => 'Cash counter input, handover, and close workflow',
                'sorting' => 120,
                'permission_name' => 'cash-counter',
                'status' => 'Active',
                'deleted_at' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('menus')->where('route', 'backend.cash-counter.index')->delete();
    }
};
