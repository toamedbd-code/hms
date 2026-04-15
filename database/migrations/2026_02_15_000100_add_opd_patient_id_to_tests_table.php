<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            if (!Schema::hasColumn('tests', 'opd_patient_id')) {
                $table->unsignedBigInteger('opd_patient_id')->nullable()->after('id');
                $table->index('opd_patient_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            if (Schema::hasColumn('tests', 'opd_patient_id')) {
                $table->dropIndex(['opd_patient_id']);
                $table->dropColumn('opd_patient_id');
            }
        });
    }
};
