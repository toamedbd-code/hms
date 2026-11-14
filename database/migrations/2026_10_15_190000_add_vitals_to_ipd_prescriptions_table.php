<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipd_prescriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('ipd_prescriptions', 'nibp')) {
                $table->string('nibp')->nullable();
            }
            if (!Schema::hasColumn('ipd_prescriptions', 'weight')) {
                $table->string('weight')->nullable();
            }
            if (!Schema::hasColumn('ipd_prescriptions', 'pulse')) {
                $table->string('pulse')->nullable();
            }
            if (!Schema::hasColumn('ipd_prescriptions', 'temperature')) {
                $table->string('temperature')->nullable();
            }
            if (!Schema::hasColumn('ipd_prescriptions', 'examinations')) {
                $table->text('examinations')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ipd_prescriptions', function (Blueprint $table) {
            $columns = ['nibp', 'weight', 'pulse', 'temperature', 'examinations'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('ipd_prescriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
