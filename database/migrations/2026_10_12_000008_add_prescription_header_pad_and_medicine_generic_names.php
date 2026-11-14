<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prescription_settings')) {
            Schema::table('prescription_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('prescription_settings', 'prescription_header_path')) {
                    $table->string('prescription_header_path')->nullable();
                }
                if (!Schema::hasColumn('prescription_settings', 'institution_pad_path')) {
                    $table->string('institution_pad_path')->nullable();
                }
            });
        }

        foreach (['medicineinventories', 'prescription_medicines'] as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'generic_name')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('generic_name')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('prescription_settings')) {
            Schema::table('prescription_settings', function (Blueprint $table) {
                $columns = [];
                foreach (['prescription_header_path', 'institution_pad_path'] as $column) {
                    if (Schema::hasColumn('prescription_settings', $column)) {
                        $columns[] = $column;
                    }
                }
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        foreach (['medicineinventories', 'prescription_medicines'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'generic_name')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('generic_name');
                });
            }
        }
    }
};
