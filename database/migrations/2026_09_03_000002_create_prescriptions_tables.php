<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prescriptions')) {
            Schema::create('prescriptions', function (Blueprint $table) {
                $table->id();
                $table->string('prescription_no')->nullable()->index();
                $table->unsignedBigInteger('patient_id');
                $table->unsignedBigInteger('doctor_id')->nullable();
                $table->string('doctor_designation')->nullable();
                $table->text('complaints')->nullable();
                $table->text('diagnosis')->nullable();
                $table->text('advice')->nullable();
                $table->date('follow_up_date')->nullable();
                $table->string('doctor_signature_path')->nullable();
                $table->string('doctor_seal_path')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['patient_id']);
                $table->index(['doctor_id']);
            });
        }

        if (!Schema::hasTable('prescription_medicines')) {
            Schema::create('prescription_medicines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('prescription_id');
                $table->string('medicine_name');
                $table->string('dose')->nullable();
                $table->string('frequency')->nullable();
                $table->string('duration')->nullable();
                $table->string('instructions')->nullable();
                $table->timestamps();

                $table->foreign('prescription_id')
                    ->references('id')
                    ->on('prescriptions')
                    ->onDelete('cascade');
                $table->index(['prescription_id']);
            });
        }

        if (!Schema::hasTable('prescription_tests')) {
            Schema::create('prescription_tests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('prescription_id');
                $table->string('test_name');
                $table->timestamps();

                $table->foreign('prescription_id')
                    ->references('id')
                    ->on('prescriptions')
                    ->onDelete('cascade');
                $table->index(['prescription_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_tests');
        Schema::dropIfExists('prescription_medicines');
        Schema::dropIfExists('prescriptions');
    }
};
