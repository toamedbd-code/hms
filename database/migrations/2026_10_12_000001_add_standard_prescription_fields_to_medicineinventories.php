<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicineinventories', function (Blueprint $table) {
            $table->string('standard_dose')->nullable();
            $table->string('standard_frequency')->nullable();
            $table->string('standard_duration')->nullable();
            $table->string('standard_instructions')->nullable();
            $table->boolean('standard_prescription_verified')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('medicineinventories', function (Blueprint $table) {
            $table->dropColumn([
                'standard_dose',
                'standard_frequency',
                'standard_duration',
                'standard_instructions',
                'standard_prescription_verified',
            ]);
        });
    }
};
