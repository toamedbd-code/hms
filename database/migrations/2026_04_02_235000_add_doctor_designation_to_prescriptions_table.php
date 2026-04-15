<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opd_prescriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('opd_prescriptions', 'doctor_designation')) {
                $table->string('doctor_designation', 255)->nullable()->after('doctor_signature_path');
            }
        });

        Schema::table('ipd_prescriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('ipd_prescriptions', 'doctor_designation')) {
                $table->string('doctor_designation', 255)->nullable()->after('doctor_signature_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opd_prescriptions', function (Blueprint $table) {
            if (Schema::hasColumn('opd_prescriptions', 'doctor_designation')) {
                $table->dropColumn('doctor_designation');
            }
        });

        Schema::table('ipd_prescriptions', function (Blueprint $table) {
            if (Schema::hasColumn('ipd_prescriptions', 'doctor_designation')) {
                $table->dropColumn('doctor_designation');
            }
        });
    }
};
