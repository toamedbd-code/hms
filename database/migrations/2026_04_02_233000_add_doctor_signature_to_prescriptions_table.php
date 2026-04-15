<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opd_prescriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('opd_prescriptions', 'doctor_signature_path')) {
                $table->string('doctor_signature_path')->nullable()->after('notes');
            }
        });

        Schema::table('ipd_prescriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('ipd_prescriptions', 'doctor_signature_path')) {
                $table->string('doctor_signature_path')->nullable()->after('follow_up_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opd_prescriptions', function (Blueprint $table) {
            if (Schema::hasColumn('opd_prescriptions', 'doctor_signature_path')) {
                $table->dropColumn('doctor_signature_path');
            }
        });

        Schema::table('ipd_prescriptions', function (Blueprint $table) {
            if (Schema::hasColumn('ipd_prescriptions', 'doctor_signature_path')) {
                $table->dropColumn('doctor_signature_path');
            }
        });
    }
};
