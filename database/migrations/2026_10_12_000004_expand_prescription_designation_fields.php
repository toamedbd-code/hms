<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['prescriptions', 'prescription_settings'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'doctor_designation')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->text('doctor_designation')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['prescriptions', 'prescription_settings'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'doctor_designation')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('doctor_designation', 255)->nullable()->change();
                });
            }
        }
    }
};
