<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipd_bed_charges', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ipd_patient_id');
            $table->foreign('ipd_patient_id')->references('id')->on('ipdpatients')->onDelete('cascade');

            $table->unsignedBigInteger('bed_id')->nullable();
            $table->foreign('bed_id')->references('id')->on('beds')->nullOnDelete();

            // Charge period (service will calculate days based on started_at/ended_at)
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();

            $table->decimal('rate_per_day', 10, 2)->default(0);

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->enum('status', ['Active', 'Inactive', 'Deleted'])->default('Active');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['ipd_patient_id', 'status']);
            $table->index(['started_at', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipd_bed_charges');
    }
};
