<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pathology_machine_integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 64)->nullable()->index();
            $table->string('event', 80)->index();
            $table->string('level', 20)->default('info')->index();
            $table->string('source_format', 20)->nullable()->index();
            $table->string('communication_mode', 30)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pathology_machine_integration_logs');
    }
};
