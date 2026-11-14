<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prescription_settings') || Schema::hasColumn('prescription_settings', 'doctor_pad_path')) {
            return;
        }

        Schema::table('prescription_settings', function (Blueprint $table) {
            $table->string('doctor_pad_path')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('prescription_settings') || !Schema::hasColumn('prescription_settings', 'doctor_pad_path')) {
            return;
        }

        Schema::table('prescription_settings', function (Blueprint $table) {
            $table->dropColumn('doctor_pad_path');
        });
    }
};
