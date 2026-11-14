<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_settings', function (Blueprint $table) {
            $table->id();
            $table->string('doctor_designation')->nullable();
            $table->string('doctor_seal_path')->nullable();
            $table->string('doctor_signature_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_settings');
    }
};
