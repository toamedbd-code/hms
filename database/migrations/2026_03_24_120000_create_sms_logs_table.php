<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 64)->index();
            $table->string('phone', 30)->index();
            $table->text('message');
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedInteger('provider_status_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedBigInteger('sent_by_admin_id')->nullable()->index();
            $table->timestamps();

            $table->index(['batch_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
