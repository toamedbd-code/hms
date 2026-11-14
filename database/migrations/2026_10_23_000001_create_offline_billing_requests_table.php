<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_billing_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id');
            $table->unsignedBigInteger('admin_id');
            $table->string('request_hash', 64);
            $table->string('route_name', 150)->nullable();
            $table->unsignedSmallInteger('response_status')->default(0);
            $table->longText('response_content')->nullable();
            $table->string('response_content_type', 150)->nullable();
            $table->string('response_location', 2048)->nullable();
            $table->timestamps();

            $table->unique(['request_id', 'admin_id']);
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_billing_requests');
    }
};
