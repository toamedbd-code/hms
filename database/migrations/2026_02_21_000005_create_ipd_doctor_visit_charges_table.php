<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipd_doctor_visit_charges', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ipd_patient_id');
            $table->foreign('ipd_patient_id')->references('id')->on('ipdpatients')->onDelete('cascade');

            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->foreign('doctor_id')->references('id')->on('admins')->nullOnDelete();

            $table->string('doctor_name')->nullable();

            $table->dateTime('visited_at')->nullable();

            $table->decimal('fee_per_visit', 10, 2)->default(0);
            $table->decimal('visit_count', 10, 3)->default(1);
            $table->decimal('total_amount', 10, 2)->default(0);

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['ipd_patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipd_doctor_visit_charges');
    }
};
