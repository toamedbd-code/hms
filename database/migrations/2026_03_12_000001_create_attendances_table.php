<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // If the `attendances` table already exists (older install), skip creating it.
        if (Schema::hasTable('attendances')) {
            return;
        }

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('employee_code')->index();
            $table->enum('type', ['in', 'out'])->default('in');
            $table->dateTime('recorded_at');
            $table->dateTime('recorded_out')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('late_minutes')->nullable();
            $table->integer('overtime_minutes')->nullable();
            $table->decimal('deduction_amount', 10, 2)->nullable();
            $table->decimal('overtime_amount', 10, 2)->nullable();
            $table->string('source')->nullable()->default('device');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('attendance_devices')->nullOnDelete();

            // avoid duplicates from device: device_id + employee_code + recorded_at
            $table->unique(['device_id', 'employee_code', 'recorded_at'], 'attendance_unique_device_emp_ts');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendances');
    }
};
