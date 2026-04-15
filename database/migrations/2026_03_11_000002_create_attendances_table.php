<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->enum('type', ['in','out'])->default('in');
            $table->timestamp('recorded_at')->nullable();
            $table->string('source')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('employee_code');
            $table->index('employee_id');
            $table->foreign('device_id')->references('id')->on('attendance_devices')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendances');
    }
};
