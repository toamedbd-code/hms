<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('CREATE FULLTEXT INDEX medinv_name_fulltext_idx ON medicineinventories (medicine_name)');
            DB::statement('CREATE INDEX medinv_name_prefix_idx ON medicineinventories (medicine_name(191))');
            return;
        }

        Schema::table('medicineinventories', function ($table) {
            $table->index('medicine_name', 'medinv_name_prefix_idx');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('DROP INDEX medinv_name_fulltext_idx ON medicineinventories');
            DB::statement('DROP INDEX medinv_name_prefix_idx ON medicineinventories');
            return;
        }

        Schema::table('medicineinventories', function ($table) {
            $table->dropIndex('medinv_name_prefix_idx');
        });
    }
};
