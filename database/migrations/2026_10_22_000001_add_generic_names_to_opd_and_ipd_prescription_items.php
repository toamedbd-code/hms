<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['opd_prescription_items', 'ipd_prescription_medicines'] as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'generic_name')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('generic_name')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['opd_prescription_items', 'ipd_prescription_medicines'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'generic_name')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('generic_name');
                });
            }
        }
    }
};
